<?php

declare(strict_types=1);

/**
 * Helpers-aanmelding zonder volledig account.
 */

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_register_helper(string $naam, string $email, string $wat, array $post): array
{
    if (stekjes_honeypot_filled($post)) {
        return ['ok' => true];
    }
    if (stekjes_form_too_fast($post)) {
        return ['ok' => false, 'error' => 'Even geduld, het formulier ging te snel.'];
    }
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    if (stekjes_rate_limit_hit('register')) {
        return ['ok' => false, 'error' => 'Te veel pogingen. Probeer later opnieuw.'];
    }

    $naam = stekjes_clean($naam, 80);
    $email = stekjes_clean($email, 254);
    $wat = stekjes_clean($wat, 2000);

    if ($naam === '') {
        return ['ok' => false, 'error' => 'Vul je naam of bijnaam in.'];
    }
    if (!stekjes_validate_email($email)) {
        return ['ok' => false, 'error' => 'Vul een geldig e-mailadres in.'];
    }
    if (stekjes_is_disposable_email($email)) {
        return ['ok' => false, 'error' => 'Gebruik geen wegwerpmailadres.'];
    }
    if ($wat === '' || mb_strlen($wat) < 5) {
        return ['ok' => false, 'error' => 'Vertel kort wat je wilt doen (minstens 5 tekens).'];
    }

    stekjes_rate_limit_record('register');
    $pdo = stekjes_db();
    $token = stekjes_random_token();
    // Bestaande onbevestigde opnieuw toestaan
    $existing = $pdo->prepare('SELECT id, confirmed_at FROM helpers WHERE email = ?');
    $existing->execute([$email]);
    $row = $existing->fetch();
    if ($row && !empty($row['confirmed_at'])) {
        return ['ok' => true]; // geen enumeratie
    }
    if ($row) {
        $pdo->prepare('UPDATE helpers SET naam = ?, wat = ?, confirm_token = ?, created_at = ? WHERE id = ?')
            ->execute([$naam, $wat, $token, stekjes_now(), (int) $row['id']]);
    } else {
        $pdo->prepare('INSERT INTO helpers (naam, email, wat, confirm_token, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$naam, $email, $wat, $token, stekjes_now()]);
    }
    stekjes_mail_helper_confirm($email, $token);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_confirm_helper(string $token): array
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
        return ['ok' => false, 'error' => 'Deze bevestigingslink werkt niet (meer).'];
    }
    $pdo->prepare('UPDATE helpers SET confirmed_at = ?, confirm_token = NULL WHERE id = ?')
        ->execute([stekjes_now(), (int) $row['id']]);
    return ['ok' => true];
}

/**
 * Teller: bevestigde accounts + bevestigde helpers.
 * Verborgen onder drempel (default 25).
 * @return array{show:bool,count:int,threshold:int}
 */
function stekjes_meedoen_teller(): array
{
    $threshold = (int) (stekjes_config()['meedoen_teller_drempel'] ?? 25);
    $pdo = stekjes_db();
    $accounts = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email_confirmed_at IS NOT NULL AND active = 1")->fetchColumn();
    $helpers = (int) $pdo->query("SELECT COUNT(*) FROM helpers WHERE confirmed_at IS NOT NULL")->fetchColumn();
    $count = $accounts + $helpers;
    return [
        'show' => $count >= $threshold,
        'count' => $count,
        'threshold' => $threshold,
    ];
}
