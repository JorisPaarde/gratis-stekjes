<?php

declare(strict_types=1);

function stekjes_current_user(): ?array
{
    stekjes_start_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return null;
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function stekjes_require_user(): array
{
    $user = stekjes_current_user();
    if (!$user) {
        stekjes_redirect('/account/inloggen.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/account/'));
    }
    if (empty($user['email_confirmed_at'])) {
        stekjes_redirect('/account/bevestiging-nodig.php');
    }
    return $user;
}

function stekjes_login_user(int $userId): void
{
    stekjes_start_session();
    if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;
}

function stekjes_logout_user(): void
{
    stekjes_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function stekjes_validate_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254;
}

function stekjes_validate_password(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Kies een wachtwoord van minstens 10 tekens.';
    }
    if (strlen($password) > 200) {
        return 'Wachtwoord is te lang.';
    }
    return null;
}

/**
 * @return array{ok:bool,error?:string,user_id?:int}
 */
function stekjes_register(string $email, string $password, string $voornaam, array $post): array
{
    if (stekjes_honeypot_filled($post)) {
        return ['ok' => true]; // stil succes voor bots
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

    $email = stekjes_clean($email, 254);
    $voornaam = stekjes_clean($voornaam, 80);
    if (!stekjes_validate_email($email)) {
        return ['ok' => false, 'error' => 'Vul een geldig e-mailadres in.'];
    }
    if (stekjes_is_disposable_email($email)) {
        return ['ok' => false, 'error' => 'Gebruik geen wegwerpmailadres.'];
    }
    if ($voornaam === '') {
        return ['ok' => false, 'error' => 'Vul een voornaam of bijnaam in.'];
    }
    if (empty($post['gratis_akkoord'])) {
        return ['ok' => false, 'error' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.'];
    }
    $pwErr = stekjes_validate_password($password);
    if ($pwErr) {
        return ['ok' => false, 'error' => $pwErr];
    }

    $pdo = stekjes_db();
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        stekjes_rate_limit_record('register');
        // Geen enumeratie: zelfde boodschap
        return ['ok' => true];
    }

    $token = stekjes_random_token();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, voornaam, confirm_token, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $voornaam, $token, stekjes_now()]);
    $id = (int) $pdo->lastInsertId();
    stekjes_rate_limit_record('register');
    stekjes_mail_confirm($email, $token);
    return ['ok' => true, 'user_id' => $id];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_confirm_email(string $token): array
{
    $token = stekjes_clean($token, 128);
    if ($token === '') {
        return ['ok' => false, 'error' => 'Ongeldige link.'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE confirm_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Deze bevestigingslink werkt niet (meer).'];
    }
    $upd = $pdo->prepare('UPDATE users SET email_confirmed_at = ?, confirm_token = NULL WHERE id = ?');
    $upd->execute([stekjes_now(), (int) $row['id']]);
    stekjes_login_user((int) $row['id']);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_login(string $email, string $password, array $post): array
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
    if (stekjes_rate_limit_hit('login')) {
        return ['ok' => false, 'error' => 'Te veel pogingen. Probeer later opnieuw.'];
    }

    $email = stekjes_clean($email, 254);
    stekjes_rate_limit_record('login');
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return ['ok' => false, 'error' => 'E-mail of wachtwoord klopt niet.'];
    }
    if (empty($user['email_confirmed_at'])) {
        return ['ok' => false, 'error' => 'Bevestig eerst je e-mail via de link in je inbox.'];
    }
    stekjes_login_user((int) $user['id']);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_request_password_reset(string $email, array $post): array
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
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = stekjes_random_token();
        $expires = gmdate('c', time() + 3600);
        $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
            ->execute([$token, $expires, (int) $user['id']]);
        stekjes_mail_reset($email, $token);
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_reset_password(string $token, string $password, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $token = stekjes_clean($token, 128);
    $pwErr = stekjes_validate_password($password);
    if ($pwErr) {
        return ['ok' => false, 'error' => $pwErr];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT id, reset_expires FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'Deze resetlink werkt niet (meer).'];
    }
    if (strtotime((string) $user['reset_expires']) < time()) {
        return ['ok' => false, 'error' => 'Deze resetlink is verlopen.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
        ->execute([$hash, (int) $user['id']]);
    stekjes_login_user((int) $user['id']);
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_update_profile(int $userId, array $fields, array $post, bool $allowNetworkGeo = true): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $voornaam = stekjes_clean((string) ($fields['voornaam'] ?? ''), 80);
    $telefoon = stekjes_clean((string) ($fields['telefoon'] ?? ''), 40);
    $locatie = stekjes_clean((string) ($fields['locatie'] ?? ''), 120);
    if ($voornaam === '') {
        return ['ok' => false, 'error' => 'Vul een voornaam of bijnaam in.'];
    }
    if ($telefoon !== '' && !preg_match('/^[0-9+\s().-]{7,40}$/', $telefoon)) {
        return ['ok' => false, 'error' => 'Telefoonnummer ziet er niet geldig uit.'];
    }
    $lat = null;
    $lon = null;
    $label = '';
    if ($locatie !== '') {
        $geo = stekjes_geocode($locatie, $allowNetworkGeo);
        if (!$geo) {
            return ['ok' => false, 'error' => 'Locatie niet gevonden. Gebruik een postcode (4 cijfers) of plaatsnaam in Nederland.'];
        }
        $lat = $geo['lat'];
        $lon = $geo['lon'];
        // Publiek: postcode-4 of plaats — niet het volledige PDOK-adres
        if (preg_match('/^\d{4}/', $locatie, $m)) {
            $label = substr(preg_replace('/\D/', '', $locatie) ?? '', 0, 4);
        } else {
            $label = stekjes_clean(explode(',', $geo['label'])[0], 80);
        }
    }
    $pdo = stekjes_db();
    $pdo->prepare('UPDATE users SET voornaam = ?, telefoon = ?, locatie_label = ?, lat = ?, lon = ? WHERE id = ?')
        ->execute([$voornaam, $telefoon, $label, $lat, $lon, $userId]);
    return ['ok' => true];
}
