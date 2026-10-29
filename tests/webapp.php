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

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
