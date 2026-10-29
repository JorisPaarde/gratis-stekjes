<?php

declare(strict_types=1);

/**
 * Kern-webapp tests (SQLite, geen netwerk). PHP 8.2+.
 * Draai: STEKJES_TEST_DB=/tmp/t.sqlite STEKJES_MAIL_CAPTURE=/tmp/m.ndjson php tests/webapp.php
 */

$root = dirname(__DIR__);
$db = getenv('STEKJES_TEST_DB') ?: sys_get_temp_dir() . '/stekjes-webapp-test.sqlite';
@unlink($db);
putenv('STEKJES_TEST_DB=' . $db);
putenv('STEKJES_MAIL_CAPTURE=' . (getenv('STEKJES_MAIL_CAPTURE') ?: sys_get_temp_dir() . '/stekjes-mail.ndjson'));
@unlink(getenv('STEKJES_MAIL_CAPTURE'));

require_once $root . '/lib/bootstrap.php';

// migrate
passthru('php ' . escapeshellarg($root . '/scripts/migrate.php') . ' up', $code);
if ($code !== 0) {
    fwrite(STDERR, "migrate failed\n");
    exit(1);
}

$failed = 0;
$passed = 0;

function ok(bool $cond, string $msg): void
{
    global $failed, $passed;
    if ($cond) {
        echo "OK  $msg\n";
        $passed++;
    } else {
        echo "FAIL $msg\n";
        $failed++;
    }
}

// --- haversine / sort ---
$d = stekjes_haversine_km(52.37, 4.89, 52.09, 5.12);
ok($d > 30 && $d < 45, "haversine Amsterdam-Utrecht ~37km got $d");

$sorted = stekjes_sort_by_distance([
    ['id' => 1, 'lat' => 52.09, 'lon' => 5.12],
    ['id' => 2, 'lat' => 52.37, 'lon' => 4.89],
], 52.37, 4.89);
ok((int) $sorted[0]['id'] === 2, 'sort_by_distance nearest first');

// --- disposable ---
ok(stekjes_is_disposable_email('a@mailinator.com'), 'mailinator disposable');
ok(!stekjes_is_disposable_email('a@example.com'), 'example.com not disposable');

// --- privacy public item ---
$pub = stekjes_public_aanbod_item([
    'id' => 1,
    'user_id' => 9,
    'type' => 'uitdelen',
    'tekst' => 'besjes',
    'voornaam' => 'Ada',
    'locatie_label' => '2011',
    'lat' => 52.3801234,
    'lon' => 4.6369876,
    'email' => 'secret@example.com',
]);
ok(!isset($pub['email']), 'public item has no email');
ok($pub['lat'] === stekjes_round_coord(52.3801234), 'lat rounded');
try {
    stekjes_assert_no_sensitive($pub, false);
    ok(true, 'assert_no_sensitive on public item');
} catch (Throwable $e) {
    ok(false, 'assert_no_sensitive: ' . $e->getMessage());
}

// --- register + confirm + messages + mutual consent ---
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
stekjes_start_session();
$_SESSION['csrf_token'] = 'test-csrf-token-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$post = [
    'csrf' => $_SESSION['csrf_token'],
    'form_started' => (string) (time() - 10),
    'website' => '',
    'gratis_akkoord' => '1',
];

$r1 = stekjes_register('alice@example.com', 'wachtwoord123', 'Alice', $post);
ok(!empty($r1['ok']), 'register alice');
$r2 = stekjes_register('bob@example.com', 'wachtwoord123', 'Bob', $post);
ok(!empty($r2['ok']), 'register bob');

$pdo = stekjes_db();
$alice = $pdo->query("SELECT * FROM users WHERE email='alice@example.com'")->fetch();
$bob = $pdo->query("SELECT * FROM users WHERE email='bob@example.com'")->fetch();
ok($alice && $bob, 'users in db');

stekjes_confirm_email((string) $alice['confirm_token']);
stekjes_confirm_email((string) $bob['confirm_token']);
$alice = $pdo->query("SELECT * FROM users WHERE email='alice@example.com'")->fetch();
$bob = $pdo->query("SELECT * FROM users WHERE email='bob@example.com'")->fetch();
ok(!empty($alice['email_confirmed_at']) && !empty($bob['email_confirmed_at']), 'emails confirmed');

// seed geo without network
$pdo->prepare('INSERT OR REPLACE INTO geocode_cache (query_key, lat, lon, label, cached_at) VALUES (?,?,?,?,?)')
    ->execute(['haarlem', 52.38, 4.64, 'Haarlem', stekjes_now()]);
$pdo->prepare('UPDATE users SET locatie_label=?, lat=?, lon=? WHERE id=?')
    ->execute(['Haarlem', 52.38, 4.64, (int) $alice['id']]);
$pdo->prepare('UPDATE users SET locatie_label=?, lat=?, lon=? WHERE id=?')
    ->execute(['Haarlem', 52.381, 4.641, (int) $bob['id']]);

$post['csrf'] = stekjes_csrf_token();
$aan = stekjes_create_aanbod((int) $alice['id'], 'uitdelen', 'Zwarte bes stekken gratis', $post);
ok(!empty($aan['ok']), 'create aanbod');

$conv = stekjes_start_or_get_conversation((int) $bob['id'], (int) $alice['id'], (int) ($aan['id'] ?? 0));
ok(!empty($conv['ok']), 'start conversation');
$cid = (int) $conv['conversation_id'];

$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) (time() - 10);
$sm = stekjes_send_message((int) $bob['id'], $cid, 'Hoi, mag ik ophalen?', $post);
ok(!empty($sm['ok']), 'send message');

$view = stekjes_conversation_view((int) $bob['id'], $cid);
ok($view !== null, 'conversation view');
ok(empty($view['andere']['email']), 'no email before mutual consent');
ok(!empty($view['veiligheidstips']), 'safety tips present');
try {
    stekjes_assert_no_sensitive(['andere' => $view['andere'], 'berichten' => $view['berichten']], false);
    ok(true, 'view without mutual has no sensitive leak');
} catch (Throwable $e) {
    ok(false, 'sensitive leak: ' . $e->getMessage());
}

$post['csrf'] = stekjes_csrf_token();
stekjes_set_contact_preference((int) $bob['id'], $cid, 'ja', $post);
$post['csrf'] = stekjes_csrf_token();
stekjes_set_contact_preference((int) $alice['id'], $cid, 'ja', $post);
$view2 = stekjes_conversation_view((int) $bob['id'], $cid);
ok(!empty($view2['wederzijds_contact']), 'mutual contact true');
ok(($view2['andere']['email'] ?? '') === 'alice@example.com', 'email visible after mutual yes');

// block
$post['csrf'] = stekjes_csrf_token();
stekjes_block_user((int) $alice['id'], (int) $bob['id'], $post);
$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) (time() - 10);
$blocked = stekjes_send_message((int) $bob['id'], $cid, 'Nog een bericht', $post);
ok(empty($blocked['ok']), 'blocked user cannot message');

// --- teller ---
$cfg = stekjes_config();
$threshold = (int) ($cfg['meedoen_teller_drempel'] ?? 25);
$t = stekjes_meedoen_teller();
ok($t['count'] === 2, 'teller count = 2 confirmed users');
ok($t['show'] === ($t['count'] >= $threshold), 'teller hidden under threshold');
// force show path: lower threshold via direct check
ok($t['count'] < $threshold ? $t['show'] === false : $t['show'] === true, 'teller show logic');

// helper
$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) (time() - 10);
$h = stekjes_register_helper('Chris', 'chris@example.com', 'Ik wil foto tippen', $post);
ok(!empty($h['ok']), 'helper register');
$token = $pdo->query("SELECT confirm_token FROM helpers WHERE email='chris@example.com'")->fetchColumn();
stekjes_confirm_helper((string) $token);
$t2 = stekjes_meedoen_teller();
ok($t2['count'] === 3, 'teller includes confirmed helper');

// disposable register rejected
$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) (time() - 10);
$bad = stekjes_register('x@mailinator.com', 'wachtwoord123', 'X', $post);
ok(empty($bad['ok']), 'disposable email rejected');

// --- aanbod expiry / reminder / renew (fake time) ---
$mailFile = getenv('STEKJES_MAIL_CAPTURE');
$t0 = 1_700_000_000; // vaste epoch voor reproduceerbare tests
stekjes_set_fake_time($t0);
$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) ($t0 - 10);
$post['gratis_akkoord'] = '1';
$post['website'] = '';

$aanExp = stekjes_create_aanbod((int) $alice['id'], 'uitdelen', 'Tomatenstekken voor expiry-test', $post);
ok(!empty($aanExp['ok']) && !empty($aanExp['id']), 'create aanbod with expiry');
$aid = (int) ($aanExp['id'] ?? 0);
$rowExp = $pdo->query('SELECT * FROM aanbod WHERE id=' . $aid)->fetch();
$expectedExp = gmdate('c', $t0 + STEKJES_AANBOD_TTL_DAYS * 86400);
ok(($rowExp['expires_at'] ?? '') === $expectedExp, 'expires_at = now + 60 days');

$searchFresh = stekjes_search_aanbod(null, null, false);
$idsFresh = array_map(static fn ($i) => (int) $i['id'], $searchFresh['items']);
ok(in_array($aid, $idsFresh, true), 'non-expired aanbod in search');

@unlink($mailFile);
$early = stekjes_job_expiry_reminders();
ok($early['sent'] === 0, 'no reminder more than 7 days before expiry');

stekjes_set_fake_time($t0 + (STEKJES_AANBOD_TTL_DAYS - STEKJES_AANBOD_REMINDER_DAYS) * 86400);
@unlink($mailFile);
$rem1 = stekjes_job_expiry_reminders();
ok($rem1['sent'] === 1, 'reminder sent once in window');
$mailRaw = is_file($mailFile) ? file_get_contents($mailFile) : '';
ok($mailRaw !== false && $mailRaw !== '', 'reminder mail captured');
$mailLines = array_values(array_filter(explode("\n", (string) $mailRaw)));
$lastMail = $mailLines ? json_decode((string) end($mailLines), true) : null;
ok(is_array($lastMail) && ($lastMail['to'] ?? '') === 'alice@example.com', 'reminder to owner');
ok(is_string($lastMail['body'] ?? null) && str_contains($lastMail['body'], 'Verleng met één klik'), 'reminder body ik-vorm verlengen');
ok(!str_contains(strtolower($lastMail['body'] ?? ''), 'resend'), 'no resend mention');
preg_match('/token=([a-f0-9]+\.[a-f0-9]+)/', (string) ($lastMail['body'] ?? ''), $tm);
$renewToken = $tm[1] ?? '';
ok($renewToken !== '' && stekjes_verify_renew_token_signature($renewToken), 'signed renew token in mail');

$rowAfterMail = $pdo->query('SELECT expiry_reminder_sent_at, renew_token FROM aanbod WHERE id=' . $aid)->fetch();
ok(!empty($rowAfterMail['expiry_reminder_sent_at']), 'mail-sent recorded on aanbod');
ok(($rowAfterMail['renew_token'] ?? '') === $renewToken, 'renew token stored');

@unlink($mailFile);
$rem2 = stekjes_job_expiry_reminders();
ok($rem2['sent'] === 0 && $rem2['skipped'] === 0, 'idempotent: no second reminder mail');
ok(!is_file($mailFile) || trim((string) file_get_contents($mailFile)) === '', 'no double mail file content');

stekjes_set_fake_time($t0 + (STEKJES_AANBOD_TTL_DAYS + 1) * 86400);
$searchGone = stekjes_search_aanbod(null, null, false);
$idsGone = array_map(static fn ($i) => (int) $i['id'], $searchGone['items']);
ok(!in_array($aid, $idsGone, true), 'expired aanbod hidden from search');
$ownerRow = $pdo->prepare('SELECT * FROM aanbod WHERE id = ? AND user_id = ?');
$ownerRow->execute([$aid, (int) $alice['id']]);
$owned = $ownerRow->fetch();
ok($owned && stekjes_aanbod_is_expired($owned), 'owner still sees expired row');

$renewed = stekjes_renew_aanbod_by_token($renewToken);
ok(!empty($renewed['ok']), 'renew by signed token');
$rowRenewed = $pdo->query('SELECT * FROM aanbod WHERE id=' . $aid)->fetch();
$expectedNew = gmdate('c', stekjes_time() + STEKJES_AANBOD_TTL_DAYS * 86400);
ok(($rowRenewed['expires_at'] ?? '') === $expectedNew, 'renew adds 60 days from now');
ok($rowRenewed['renew_token'] === null || $rowRenewed['renew_token'] === '', 'token single-use cleared');
ok($rowRenewed['expiry_reminder_sent_at'] === null || $rowRenewed['expiry_reminder_sent_at'] === '', 'reminder flag reset after renew');

$searchBack = stekjes_search_aanbod(null, null, false);
$idsBack = array_map(static fn ($i) => (int) $i['id'], $searchBack['items']);
ok(in_array($aid, $idsBack, true), 'renewed aanbod back in search');

$reuse = stekjes_renew_aanbod_by_token($renewToken);
ok(empty($reuse['ok']), 'renew token cannot be reused');

$forged = stekjes_renew_aanbod_by_token(preg_replace('/\.[a-f0-9]+$/', '.0000000000000000', $renewToken) ?? '');
ok(empty($forged['ok']), 'forged signature rejected');

stekjes_set_fake_time(stekjes_time() + (STEKJES_AANBOD_TTL_DAYS + 2) * 86400);
ok(stekjes_aanbod_is_expired($pdo->query('SELECT * FROM aanbod WHERE id=' . $aid)->fetch()), 'expired again for owner renew');
$post['csrf'] = stekjes_csrf_token();
$ownerRenew = stekjes_renew_aanbod_as_owner((int) $alice['id'], $aid, $post);
ok(!empty($ownerRenew['ok']), 'owner can renew expired aanbod');
$searchOwner = stekjes_search_aanbod(null, null, false);
ok(in_array($aid, array_map(static fn ($i) => (int) $i['id'], $searchOwner['items']), true), 'owner-renewed visible in search');

ok(hash_equals('test-cron-token', (string) stekjes_config()['cron_token']), 'cron token from test config');

stekjes_set_fake_time(null);

// --- AVG export / delete / purge (na expiry-tests; wist alice) ---
$export = stekjes_export_user_data((int) $alice['id']);
ok(!array_key_exists('password_hash', $export['user']), 'export has no password_hash');
ok(!isset($export['user']['confirm_token']), 'export has no confirm_token');
ok(($export['user']['email'] ?? '') === 'alice@example.com', 'export contains own email');
$json = json_encode($export);
ok(is_string($json) && !str_contains($json, 'password_hash'), 'export JSON has no password_hash string');

$pdo->exec("INSERT INTO rate_limits (ip_hash, action, created_at) VALUES ('abc', 'search', " . (time() - 999999) . ")");
$purged = stekjes_purge_rate_limits();
ok($purged >= 1, 'purge_rate_limits deletes old rows');

$post['csrf'] = stekjes_csrf_token();
$post['bevestig'] = 'verwijder';
$del = stekjes_delete_user_account((int) $alice['id'], $post);
ok(!empty($del['ok']), 'delete account');
$gone = $pdo->query("SELECT COUNT(*) FROM users WHERE email='alice@example.com'")->fetchColumn();
ok((int) $gone === 0, 'user row removed');

$post['csrf'] = stekjes_csrf_token();
$post['form_started'] = (string) (time() - 10);
$hr = stekjes_request_helper_delete('chris@example.com', $post);
ok(!empty($hr['ok']), 'helper delete request');
$ht = $pdo->query("SELECT confirm_token FROM helpers WHERE email='chris@example.com'")->fetchColumn();
ok(is_string($ht) && $ht !== '', 'helper delete token set');
$hd = stekjes_confirm_helper_delete((string) $ht);
ok(!empty($hd['ok']), 'helper delete confirm');
$hc = $pdo->query("SELECT COUNT(*) FROM helpers WHERE email='chris@example.com'")->fetchColumn();
ok((int) $hc === 0, 'helper removed');

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
