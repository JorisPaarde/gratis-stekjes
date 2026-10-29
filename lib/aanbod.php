<?php

declare(strict_types=1);

/**
 * Aanbod: vrij tekstveld + type uitdelen|ophalen.
 */

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
    $stmt = stekjes_db()->prepare(
        'INSERT INTO aanbod (user_id, type, tekst, visible, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)'
    );
    $stmt->execute([$userId, $type, $tekst, $now, $now]);
    return ['ok' => true, 'id' => (int) stekjes_db()->lastInsertId()];
}

/**
 * @return array{_meta:?array,items:list<array<string,mixed>>}
 */
function stekjes_search_aanbod(?string $query, ?string $typeFilter, bool $allowNetwork = true): array
{
    stekjes_rate_limit_record('search');

    $pdo = stekjes_db();
    $sql = 'SELECT a.id, a.user_id, a.type, a.tekst, u.voornaam, u.locatie_label, u.lat, u.lon
            FROM aanbod a
            JOIN users u ON u.id = a.user_id
            WHERE a.visible = 1 AND u.active = 1 AND u.email_confirmed_at IS NOT NULL
              AND u.lat IS NOT NULL AND u.lon IS NOT NULL';
    $params = [];
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
