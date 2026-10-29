<?php

declare(strict_types=1);

/**
 * AVG: data-export, zelf wissen, dataminimalisatie / retentie.
 */

/**
 * Export alle gegevens van een gebruiker als array (JSON-serialiseerbaar).
 * Geen password_hash, tokens, of IP's in plaintext.
 *
 * @return array<string,mixed>
 */
function stekjes_export_user_data(int $userId): array
{
    $pdo = stekjes_db();
    $u = $pdo->prepare('SELECT id, email, voornaam, telefoon, locatie_label, lat, lon, email_confirmed_at, created_at, active FROM users WHERE id = ?');
    $u->execute([$userId]);
    $user = $u->fetch();
    if (!$user) {
        throw new RuntimeException('Gebruiker niet gevonden.');
    }

    $aanbod = $pdo->prepare('SELECT id, type, tekst, visible, created_at, updated_at FROM aanbod WHERE user_id = ?');
    $aanbod->execute([$userId]);
    $aanbodRows = $aanbod->fetchAll();

    $convs = $pdo->prepare(
        'SELECT id, aanbod_id, user_a_id, user_b_id, contact_a, contact_b, created_at
         FROM conversations WHERE user_a_id = ? OR user_b_id = ?'
    );
    $convs->execute([$userId, $userId]);
    $convRows = $convs->fetchAll();
    $convIds = array_map(static fn ($r) => (int) $r['id'], $convRows);

    $messages = [];
    if ($convIds !== []) {
        $in = implode(',', array_fill(0, count($convIds), '?'));
        $msg = $pdo->prepare("SELECT id, conversation_id, sender_id, body, created_at, read_at FROM messages WHERE conversation_id IN ($in) ORDER BY created_at");
        $msg->execute($convIds);
        $messages = $msg->fetchAll();
    }

    $blocks = $pdo->prepare('SELECT blocker_id, blocked_id, created_at FROM blocks WHERE blocker_id = ? OR blocked_id = ?');
    $blocks->execute([$userId, $userId]);

    $reports = $pdo->prepare('SELECT id, reported_user_id, conversation_id, reason, created_at, handled FROM reports WHERE reporter_id = ?');
    $reports->execute([$userId]);

    // Geen e-mails van anderen tenzij wederzijds contact in dat gesprek
    $andere = [];
    foreach ($convRows as $c) {
        $otherId = ((int) $c['user_a_id'] === $userId) ? (int) $c['user_b_id'] : (int) $c['user_a_id'];
        $o = $pdo->prepare('SELECT id, email, voornaam, telefoon, locatie_label FROM users WHERE id = ?');
        $o->execute([$otherId]);
        $orow = $o->fetch();
        if (!$orow) {
            continue;
        }
        $view = stekjes_other_user_view($orow, $c, $userId);
        $andere[] = [
            'conversation_id' => (int) $c['id'],
            'andere' => $view,
        ];
    }

    return [
        'exported_at' => stekjes_now(),
        'version' => STEKJES_VERSION,
        'user' => [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'voornaam' => (string) $user['voornaam'],
            'telefoon' => (string) $user['telefoon'],
            'locatie_label' => (string) $user['locatie_label'],
            'lat' => $user['lat'] !== null ? stekjes_round_coord((float) $user['lat']) : null,
            'lon' => $user['lon'] !== null ? stekjes_round_coord((float) $user['lon']) : null,
            'email_confirmed_at' => $user['email_confirmed_at'],
            'created_at' => $user['created_at'],
            'active' => (int) $user['active'],
        ],
        'aanbod' => $aanbodRows,
        'conversations' => $convRows,
        'conversation_partners' => $andere,
        'messages' => $messages,
        'blocks' => $blocks->fetchAll(),
        'reports_filed' => $reports->fetchAll(),
    ];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_delete_user_account(int $userId, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $confirm = stekjes_clean((string) ($post['bevestig'] ?? ''), 40);
    if (mb_strtolower($confirm) !== 'verwijder') {
        return ['ok' => false, 'error' => 'Typ het woord “verwijder” om te bevestigen.'];
    }

    $pdo = stekjes_db();
    $pdo->beginTransaction();
    try {
        $cids = $pdo->prepare('SELECT id FROM conversations WHERE user_a_id = ? OR user_b_id = ?');
        $cids->execute([$userId, $userId]);
        $ids = array_map('intval', $cids->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM messages WHERE conversation_id IN ($in)")->execute($ids);
            $pdo->prepare("DELETE FROM conversations WHERE id IN ($in)")->execute($ids);
        }
        $pdo->prepare('DELETE FROM messages WHERE sender_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM blocks WHERE blocker_id = ? OR blocked_id = ?')->execute([$userId, $userId]);
        $pdo->prepare('DELETE FROM reports WHERE reporter_id = ? OR reported_user_id = ?')->execute([$userId, $userId]);
        $pdo->prepare('DELETE FROM aanbod WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    stekjes_logout_user();
    return ['ok' => true];
}

/**
 * Helper: vraag verwijderlink (double confirm via mail).
 * @return array{ok:bool,error?:string}
 */
function stekjes_request_helper_delete(string $email, array $post): array
{
    if (stekjes_honeypot_filled($post)) {
        return ['ok' => true];
    }
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    if (stekjes_rate_limit_hit('password_reset')) {
        return ['ok' => false, 'error' => 'Te veel pogingen. Probeer later opnieuw.'];
    }
    stekjes_rate_limit_record('password_reset');
    $email = stekjes_clean($email, 254);
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT id FROM helpers WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        $token = stekjes_random_token();
        $pdo->prepare('UPDATE helpers SET confirm_token = ? WHERE id = ?')->execute([$token, (int) $row['id']]);
        stekjes_mail_helper_delete($email, $token);
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_confirm_helper_delete(string $token): array
{
    $token = stekjes_clean($token, 128);
    if ($token === '') {
        return ['ok' => false, 'error' => 'Ongeldige link.'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT id FROM helpers WHERE confirm_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Deze link werkt niet (meer).'];
    }
    $pdo->prepare('DELETE FROM helpers WHERE id = ?')->execute([(int) $row['id']]);
    return ['ok' => true];
}

function stekjes_mail_helper_delete(string $email, string $token): bool
{
    $site = stekjes_site_name();
    $url = stekjes_site_url('/helpers/verwijderen.php?token=' . rawurlencode($token));
    $body = "Hoi,\n\nJe vroeg om je helper-aanmelding bij $site te verwijderen. Bevestig via:\n$url\n\nHeb je dit niet gevraagd? Negeer deze mail.\n\nGroet,\n$site\n";
    return stekjes_send_mail($email, 'Bevestig verwijderen helper-aanmelding', $body);
}

/** Wis verlopen rate-limit rijen volgens config. @return int rows deleted */
function stekjes_purge_rate_limits(): int
{
    $seconds = (int) (stekjes_config()['rate_limit_retention_seconds'] ?? 172800);
    if ($seconds < 3600) {
        $seconds = 3600;
    }
    $before = time() - $seconds;
    $stmt = stekjes_db()->prepare('DELETE FROM rate_limits WHERE created_at < ?');
    $stmt->execute([$before]);
    return $stmt->rowCount();
}

/** Wis oude geocode-cache. @return int */
function stekjes_purge_geocode_cache(): int
{
    $days = (int) (stekjes_config()['geocode_cache_retention_days'] ?? 90);
    if ($days < 1) {
        $days = 90;
    }
    $pdo = stekjes_db();
    $driver = stekjes_db_driver();
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("DELETE FROM geocode_cache WHERE cached_at < datetime('now', ?)");
        $stmt->execute(['-' . $days . ' days']);
    } else {
        $stmt = $pdo->prepare('DELETE FROM geocode_cache WHERE cached_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)');
        $stmt->execute([$days]);
    }
    return $stmt->rowCount();
}
