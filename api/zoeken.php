<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    stekjes_json_response(['error' => 'Alleen GET'], 405);
}

$q = stekjes_clean((string) ($_GET['q'] ?? ''), 120);
$type = stekjes_clean((string) ($_GET['type'] ?? ''), 20);
if ($type !== 'uitdelen' && $type !== 'ophalen') {
    $type = null;
}

if ($q === '') {
    stekjes_json_response(['error' => 'Vul een postcode of plaats in.'], 400);
}

if (stekjes_rate_limit_hit('search')) {
    stekjes_json_response(['error' => 'Te vaak gezocht. Probeer later opnieuw.'], 429);
}

$result = stekjes_search_aanbod($q, $type, true);
$meta = $result['_meta'] ?? null;
$items = $result['items'] ?? $result;

// Privacy: strip gevoelige velden
$public = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $row = stekjes_public_aanbod_item([
        'id' => $item['id'],
        'user_id' => $item['user_id'],
        'type' => $item['type'],
        'tekst' => $item['tekst'],
        'voornaam' => $item['voornaam'],
        'locatie_label' => $item['locatie_label'] ?? '',
        'lat' => $item['lat'],
        'lon' => $item['lon'],
    ]);
    if (array_key_exists('afstand_km', $item)) {
        $row['afstand_km'] = $item['afstand_km'];
    }
    $public[] = $row;
}

$payload = [
    'meta' => $meta ? [
        'zoek_lat' => $meta['zoek_lat'],
        'zoek_lon' => $meta['zoek_lon'],
        'zoek_label' => $meta['zoek_label'],
    ] : null,
    'items' => $public,
];

try {
    stekjes_assert_no_sensitive($payload, false);
} catch (Throwable $e) {
    stekjes_json_response(['error' => 'Interne privacyfout'], 500);
}

stekjes_json_response($payload);
