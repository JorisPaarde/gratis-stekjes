<?php

declare(strict_types=1);

/**
 * PDOK Locatieserver geocoding + haversine. Alleen benaderde coördinaten.
 */

function stekjes_round_coord(float $value): float
{
    $decimals = (int) (stekjes_config()['coord_decimals'] ?? 3);
    return round($value, $decimals);
}

function stekjes_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371.0;
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lon2 - $lon1);
    $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

function stekjes_normalize_geo_query(string $q): string
{
    $q = stekjes_clean($q, 120);
    $q = preg_replace('/\s+/', ' ', $q) ?? $q;
    return mb_strtolower($q, 'UTF-8');
}

/**
 * @return array{lat: float, lon: float, label: string}|null
 */
function stekjes_geocode(string $query, bool $allowNetwork = true): ?array
{
    $key = stekjes_normalize_geo_query($query);
    if ($key === '') {
        return null;
    }

    $pdo = stekjes_db();
    $stmt = $pdo->prepare('SELECT lat, lon, label FROM geocode_cache WHERE query_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row) {
        return [
            'lat' => stekjes_round_coord((float) $row['lat']),
            'lon' => stekjes_round_coord((float) $row['lon']),
            'label' => (string) $row['label'],
        ];
    }

    if (!$allowNetwork) {
        return null;
    }

    $config = stekjes_config();
    $base = rtrim((string) ($config['pdok_url'] ?? ''), '/');
    if ($base === '') {
        return null;
    }

    // Postcode-4 of woonplaats; vraag PDOK om centrum
    $params = http_build_query([
        'q' => $query,
        'rows' => 1,
        'fl' => 'centroide_ll,weergavenaam,type,score',
    ]);
    $url = $base . '?' . $params;

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "Accept: application/json\r\nUser-Agent: GratisStekjes/" . STEKJES_VERSION . "\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    $docs = $data['response']['docs'] ?? [];
    if (!is_array($docs) || $docs === []) {
        return null;
    }
    $doc = $docs[0];
    $centroid = (string) ($doc['centroide_ll'] ?? '');
    // POINT(lon lat)
    if (!preg_match('/POINT\s*\(\s*([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s*\)/i', $centroid, $m)) {
        return null;
    }
    $lon = stekjes_round_coord((float) $m[1]);
    $lat = stekjes_round_coord((float) $m[2]);
    $label = (string) ($doc['weergavenaam'] ?? $query);
    // Vereenvoudig label: bij postcode alleen 4 cijfers tonen als query dat was
    if (preg_match('/^\d{4}$/', trim($query))) {
        $label = trim($query);
    }

    $ins = $pdo->prepare('INSERT OR REPLACE INTO geocode_cache (query_key, lat, lon, label, cached_at) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$key, $lat, $lon, $label, stekjes_now()]);

    return ['lat' => $lat, 'lon' => $lon, 'label' => $label];
}

/**
 * Sorteer items met lat/lon op afstand tot een punt.
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function stekjes_sort_by_distance(array $items, float $lat, float $lon): array
{
    foreach ($items as &$item) {
        $ilat = isset($item['lat']) ? (float) $item['lat'] : null;
        $ilon = isset($item['lon']) ? (float) $item['lon'] : null;
        if ($ilat === null || $ilon === null) {
            $item['afstand_km'] = null;
        } else {
            $item['afstand_km'] = round(stekjes_haversine_km($lat, $lon, $ilat, $ilon), 1);
        }
    }
    unset($item);

    usort($items, static function ($a, $b) {
        $da = $a['afstand_km'];
        $db = $b['afstand_km'];
        if ($da === null && $db === null) {
            return 0;
        }
        if ($da === null) {
            return 1;
        }
        if ($db === null) {
            return -1;
        }
        return $da <=> $db;
    });

    return $items;
}
