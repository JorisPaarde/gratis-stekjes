<?php

declare(strict_types=1);

/**
 * Partnermarkers / voedselbossen e.d. op de kaart.
 * Types: partner, voedselbos, plantenasiel, plantenbieb, ruilkast.
 */

/** @return list<string> */
function stekjes_partner_types(): array
{
    return ['partner', 'voedselbos', 'plantenasiel', 'plantenbieb', 'ruilkast'];
}

/** @return array<string,string> */
function stekjes_partner_type_labels(): array
{
    return [
        'partner' => 'Partner',
        'voedselbos' => 'Voedselbos',
        'plantenasiel' => 'Plantenasiel',
        'plantenbieb' => 'Plantenbieb',
        'ruilkast' => 'Ruilkast',
    ];
}

function stekjes_partner_type_valid(string $type): bool
{
    return in_array($type, stekjes_partner_types(), true);
}

/**
 * Publieke, zichtbare markers (afgeronde coördinaten).
 *
 * @param string|null $type Filter op type, of null = alle types
 * @return list<array<string,mixed>>
 */
function stekjes_list_partner_markers(?string $type = null): array
{
    $pdo = stekjes_db();
    if ($type !== null && $type !== '') {
        if (!stekjes_partner_type_valid($type)) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT id, naam, type, plaats, beschrijving, lat, lon, url, visible
             FROM partner_markers WHERE visible = 1 AND type = ? ORDER BY naam'
        );
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, naam, type, plaats, beschrijving, lat, lon, url, visible
             FROM partner_markers WHERE visible = 1 ORDER BY naam'
        );
    }
    $rows = $stmt->fetchAll() ?: [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = stekjes_public_partner_marker($row);
    }
    return $out;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function stekjes_public_partner_marker(array $row): array
{
    $labels = stekjes_partner_type_labels();
    $type = (string) ($row['type'] ?? 'partner');
    return [
        'id' => (int) ($row['id'] ?? 0),
        'naam' => (string) ($row['naam'] ?? ''),
        'type' => $type,
        'type_label' => $labels[$type] ?? $type,
        'plaats' => (string) ($row['plaats'] ?? ''),
        'beschrijving' => (string) ($row['beschrijving'] ?? ''),
        'lat' => stekjes_round_coord((float) ($row['lat'] ?? 0)),
        'lon' => stekjes_round_coord((float) ($row['lon'] ?? 0)),
        'url' => (string) ($row['url'] ?? ''),
        'kind' => 'partner_marker',
    ];
}

/**
 * Admin: alle markers inclusief verborgen.
 *
 * @return list<array<string,mixed>>
 */
function stekjes_admin_list_partner_markers(): array
{
    $pdo = stekjes_db();
    $rows = $pdo->query(
        'SELECT id, naam, type, plaats, beschrijving, lat, lon, url, bron, visible, created_at
         FROM partner_markers ORDER BY naam, id'
    )->fetchAll();
    return $rows ?: [];
}

/**
 * @return array<string,mixed>|null
 */
function stekjes_admin_get_partner_marker(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare(
        'SELECT id, naam, type, plaats, beschrijving, lat, lon, url, bron, visible, created_at
         FROM partner_markers WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Maak of werk een marker bij. Coördinaten via PDOK op basis van plaats
 * (of bestaande lat/lon behouden bij edit zonder nieuwe plaatsgeocode).
 *
 * @param array<string,mixed> $fields
 * @return array{ok:bool,error?:string,id?:int}
 */
function stekjes_save_partner_marker(?int $id, array $fields, bool $allowNetworkGeo = true): array
{
    $naam = stekjes_clean((string) ($fields['naam'] ?? ''), 160);
    $type = stekjes_clean((string) ($fields['type'] ?? ''), 32);
    $plaats = stekjes_clean((string) ($fields['plaats'] ?? ''), 120);
    $beschrijving = stekjes_clean((string) ($fields['beschrijving'] ?? ''), 500);
    $url = stekjes_clean((string) ($fields['url'] ?? ''), 500);
    $bron = stekjes_clean((string) ($fields['bron'] ?? ''), 500);
    $visible = !empty($fields['visible']) ? 1 : 0;

    if ($naam === '') {
        return ['ok' => false, 'error' => 'Vul een naam in.'];
    }
    if (!stekjes_partner_type_valid($type)) {
        return ['ok' => false, 'error' => 'Kies een geldig type.'];
    }
    if ($plaats === '') {
        return ['ok' => false, 'error' => 'Vul een plaats of postcode-4 in voor de benaderde plek.'];
    }
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'error' => 'Website moet met http:// of https:// beginnen (of leeg laten).'];
    }

    $lat = null;
    $lon = null;
    $existing = $id ? stekjes_admin_get_partner_marker($id) : null;
    if ($existing && stekjes_normalize_geo_query($plaats) === stekjes_normalize_geo_query((string) $existing['plaats'])) {
        $lat = (float) $existing['lat'];
        $lon = (float) $existing['lon'];
    } else {
        $geo = stekjes_geocode($plaats, $allowNetworkGeo);
        if ($geo === null) {
            return ['ok' => false, 'error' => 'Plaats niet gevonden. Probeer een postcode-4 of woonplaats (PDOK).'];
        }
        $lat = $geo['lat'];
        $lon = $geo['lon'];
        // Publiek label: korte plaats, niet het volledige PDOK-adres
        if (preg_match('/^\d{4}$/', $plaats)) {
            $plaats = $plaats;
        } else {
            $plaats = stekjes_clean(explode(',', $geo['label'])[0], 120);
        }
    }

    $pdo = stekjes_db();
    if ($existing) {
        $pdo->prepare(
            'UPDATE partner_markers
             SET naam = ?, type = ?, plaats = ?, beschrijving = ?, lat = ?, lon = ?, url = ?, bron = ?, visible = ?
             WHERE id = ?'
        )->execute([$naam, $type, $plaats, $beschrijving, $lat, $lon, $url, $bron, $visible, (int) $existing['id']]);
        return ['ok' => true, 'id' => (int) $existing['id']];
    }

    $pdo->prepare(
        'INSERT INTO partner_markers (naam, type, plaats, beschrijving, lat, lon, url, bron, visible, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$naam, $type, $plaats, $beschrijving, $lat, $lon, $url, $bron, $visible, stekjes_now()]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/**
 * @return array{ok:bool,error?:string}
 */
function stekjes_delete_partner_marker(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Ongeldige marker.'];
    }
    $pdo = stekjes_db();
    $stmt = $pdo->prepare('DELETE FROM partner_markers WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'error' => 'Marker niet gevonden.'];
    }
    return ['ok' => true];
}
