<?php

declare(strict_types=1);

/**
 * Aanbod: vrij tekstveld + type uitdelen|ophalen.
 * Verloopt na 60 dagen; 7 dagen van tevoren herinneringsmail met eenmalige verlenglink.
 */

const STEKJES_AANBOD_TTL_DAYS = 60;
const STEKJES_AANBOD_REMINDER_DAYS = 7;
const STEKJES_AANBOD_RENEW_TOKEN_DAYS = 14;

/**
 * @return array{ok:bool,error?:string,id?:int}
 */
function stekjes_create_aanbod(int $userId, string $type, string $tekst, array $post): array
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
    if (empty($post['gratis_akkoord'])) {
        return ['ok' => false, 'error' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.'];
    }
    $type = stekjes_clean($type, 20);
    if (!in_array($type, ['uitdelen', 'ophalen'], true)) {
        return ['ok' => false, 'error' => 'Kies uitdelen of ophalen.'];
    }
    $tekst = stekjes_clean($tekst, 2000);
    if ($tekst === '' || mb_strlen($tekst) < 5) {
        return ['ok' => false, 'error' => 'Schrijf kort wat je hebt of zoekt (minstens 5 tekens).'];
    }

    $user = stekjes_db()->prepare('SELECT locatie_label, lat, lon FROM users WHERE id = ?');
    $user->execute([$userId]);
    $u = $user->fetch();
    if (!$u || $u['lat'] === null || trim((string) $u['locatie_label']) === '') {
        return ['ok' => false, 'error' => 'Vul eerst in je profiel een postcode (4 cijfers) of plaats in.'];
    }

    $now = stekjes_now();
    $expires = stekjes_now_plus_days(STEKJES_AANBOD_TTL_DAYS);
    $stmt = stekjes_db()->prepare(
        'INSERT INTO aanbod (user_id, type, tekst, visible, created_at, updated_at, expires_at)
         VALUES (?, ?, ?, 1, ?, ?, ?)'
    );
    $stmt->execute([$userId, $type, $tekst, $now, $now, $expires]);
    return ['ok' => true, 'id' => (int) stekjes_db()->lastInsertId()];
}

/**
 * Publieke zoekopdracht: alleen zichtbaar én niet verlopen aanbod.
 *
 * @return array{_meta:?array,items:list<array<string,mixed>>}
 */
function stekjes_search_aanbod(?string $query, ?string $typeFilter, bool $allowNetwork = true): array
{
    stekjes_rate_limit_record('search');

    $pdo = stekjes_db();
    $now = stekjes_now();
    $sql = 'SELECT a.id, a.user_id, a.type, a.tekst, u.voornaam, u.locatie_label, u.lat, u.lon
            FROM aanbod a
            JOIN users u ON u.id = a.user_id
            WHERE a.visible = 1 AND u.active = 1 AND u.email_confirmed_at IS NOT NULL
              AND u.lat IS NOT NULL AND u.lon IS NOT NULL
              AND a.expires_at > ?';
    $params = [$now];
    if ($typeFilter === 'uitdelen' || $typeFilter === 'ophalen') {
        $sql .= ' AND a.type = ?';
        $params[] = $typeFilter;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $items = array_map('stekjes_public_aanbod_item', $rows);

    $q = stekjes_clean((string) $query, 120);
    if ($q !== '') {
        $geo = stekjes_geocode($q, $allowNetwork);
        if ($geo) {
            $items = stekjes_sort_by_distance($items, $geo['lat'], $geo['lon']);
            return [
                '_meta' => [
                    'zoek_lat' => $geo['lat'],
                    'zoek_lon' => $geo['lon'],
                    'zoek_label' => $geo['label'],
                ],
                'items' => $items,
            ];
        }
    }

    return ['_meta' => null, 'items' => $items];
}

function stekjes_aanbod_is_expired(array $row): bool
{
    $exp = (string) ($row['expires_at'] ?? '');
    if ($exp === '') {
        return false;
    }
    $ts = strtotime($exp);
    return $ts !== false && $ts <= stekjes_time();
}

/**
 * Verleng aanbod met 60 dagen vanaf nu. Wis eenmalige renew-token en herinneringsflag.
 *
 * @return array{ok:bool,error?:string,expires_at?:string}
 */
function stekjes_renew_aanbod_row(array $row): array
{
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Onbekend aanbod.'];
    }
    $now = stekjes_now();
    $expires = stekjes_now_plus_days(STEKJES_AANBOD_TTL_DAYS);
    $pdo = stekjes_db();
    $pdo->prepare(
        'UPDATE aanbod SET expires_at = ?, updated_at = ?, expiry_reminder_sent_at = NULL,
         renew_token = NULL, renew_token_expires = NULL WHERE id = ?'
    )->execute([$expires, $now, $id]);
    return ['ok' => true, 'expires_at' => $expires];
}

/**
 * Eenklik-verlenging via ondertekend, eenmalig token (uit herinneringsmail).
 *
 * @return array{ok:bool,error?:string,expires_at?:string}
 */
function stekjes_renew_aanbod_by_token(string $token): array
{
    $token = stekjes_clean($token, 128);
    if ($token === '' || !preg_match('/^[a-f0-9]{16,96}\.[a-f0-9]{16}$/', $token)) {
        return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
    }
    if (!stekjes_verify_renew_token_signature($token)) {
        return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM aanbod WHERE renew_token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
    }
    $exp = strtotime((string) ($row['renew_token_expires'] ?? ''));
    if ($exp === false || $exp <= stekjes_time()) {
        return ['ok' => false, 'error' => 'Deze verlenglink is verlopen.'];
    }
    // Eenmalig: claim token eerst, daarna verlengen
    $claim = $pdo->prepare(
        'UPDATE aanbod SET renew_token = NULL, renew_token_expires = NULL
         WHERE id = ? AND renew_token = ?'
    );
    $claim->execute([(int) $row['id'], $token]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Deze verlenglink werkt niet (meer).'];
    }
    return stekjes_renew_aanbod_row($row);
}

/**
 * Eigenaar verlengt (ingelogd, CSRF).
 *
 * @return array{ok:bool,error?:string,expires_at?:string}
 */
function stekjes_renew_aanbod_as_owner(int $userId, int $aanbodId, array $post): array
{
    if (!stekjes_verify_csrf($post['csrf'] ?? null)) {
        return ['ok' => false, 'error' => 'Sessie verlopen. Probeer opnieuw.'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT * FROM aanbod WHERE id = ? AND user_id = ?');
    $stmt->execute([$aanbodId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Aanbod niet gevonden.'];
    }
    return stekjes_renew_aanbod_row($row);
}

/**
 * Ondertekening van renew-tokens (HMAC over token met config-afgeleide sleutel).
 * De token zelf staat in de DB; de handtekening zit in de URL als tweede deel, of
 * we embedden de hmac in het token zelf. Formaat: {random}.{hmac16}
 */
function stekjes_signing_key(): string
{
    $config = stekjes_config();
    $parts = [
        (string) ($config['cron_token'] ?? ''),
        (string) ($config['site_url'] ?? ''),
        (string) ($config['admin_password'] ?? ''),
        'aanbod-renew',
    ];
    return hash('sha256', implode('|', $parts), true);
}

function stekjes_make_renew_token(): string
{
    $raw = stekjes_random_token(24);
    $sig = hash_hmac('sha256', $raw, stekjes_signing_key());
    return $raw . '.' . substr($sig, 0, 16);
}

function stekjes_verify_renew_token_signature(string $token): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$raw, $sig] = $parts;
    if ($raw === '' || $sig === '' || !ctype_xdigit($raw) || !ctype_xdigit($sig)) {
        return false;
    }
    $expected = substr(hash_hmac('sha256', $raw, stekjes_signing_key()), 0, 16);
    return hash_equals($expected, $sig);
}

/**
 * Cron-job: stuur één herinneringsmail per aanbod, 7 dagen vóór verloop.
 * Idempotent: claimt expiry_reminder_sent_at vóór verzenden.
 *
 * @return array{checked:int,sent:int,skipped:int,failed:int}
 */
function stekjes_job_expiry_reminders(): array
{
    $stats = ['checked' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
    $pdo = stekjes_db();
    $now = stekjes_now();
    $windowEnd = stekjes_now_plus_days(STEKJES_AANBOD_REMINDER_DAYS);

    $stmt = $pdo->prepare(
        'SELECT a.id, a.tekst, a.expires_at, u.email, u.voornaam
         FROM aanbod a
         JOIN users u ON u.id = a.user_id
         WHERE a.visible = 1
           AND u.active = 1
           AND u.email_confirmed_at IS NOT NULL
           AND a.expiry_reminder_sent_at IS NULL
           AND a.expires_at > ?
           AND a.expires_at <= ?
         ORDER BY a.id ASC'
    );
    $stmt->execute([$now, $windowEnd]);
    $rows = $stmt->fetchAll();
    $stats['checked'] = count($rows);

    foreach ($rows as $row) {
        $token = stekjes_make_renew_token();
        $tokenExpires = stekjes_now_plus_days(STEKJES_AANBOD_RENEW_TOKEN_DAYS);
        $claimedAt = stekjes_now();

        $claim = $pdo->prepare(
            'UPDATE aanbod
             SET expiry_reminder_sent_at = ?, renew_token = ?, renew_token_expires = ?
             WHERE id = ? AND expiry_reminder_sent_at IS NULL'
        );
        $claim->execute([$claimedAt, $token, $tokenExpires, (int) $row['id']]);
        if ($claim->rowCount() === 0) {
            $stats['skipped']++;
            continue;
        }

        $ok = stekjes_mail_aanbod_expiry_reminder((string) $row['email'], $token, (string) ($row['tekst'] ?? ''));
        if (!$ok) {
            // Mail mislukt → claim terugzetten zodat de volgende cron opnieuw probeert
            $pdo->prepare(
                'UPDATE aanbod SET expiry_reminder_sent_at = NULL, renew_token = NULL, renew_token_expires = NULL
                 WHERE id = ? AND renew_token = ?'
            )->execute([(int) $row['id'], $token]);
            $stats['failed']++;
            continue;
        }
        $stats['sent']++;
    }

    return $stats;
}
