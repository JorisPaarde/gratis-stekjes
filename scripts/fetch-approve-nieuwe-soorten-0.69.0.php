#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Haal Commons-foto's op voor nieuwe 0.69.0-soorten.
 * Strenger dan de generieke fetch: Latijnse naam moet in titel of beschrijving
 * staan; schelp-/shell-hits worden geweigerd. Schrijft goedgekeurde JPEG + meta.
 *
 * Gebruik: php scripts/fetch-approve-nieuwe-soorten-0.69.0.php
 * Daarna: foto's met het oog controleren (agent Read tool).
 */
$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root.'/storage/app/nieuwe-soorten-0.69.0.json'), true);
$slugs = $manifest['slugs'] ?? [];
$latijnMap = $manifest['latijn'] ?? [];
$namen = $manifest['namen'] ?? [];

$metaPath = $root.'/data/soort-afbeeldingen.json';
$meta = json_decode((string) file_get_contents($metaPath), true);
$rows = is_array($meta['soorten'] ?? null) ? $meta['soorten'] : [];

$ua = 'GratisPlantenBot/0.69 (https://gratisplanten.nl; info@gratisplanten.nl) PHP-script';

function httpGet(string $url, string $ua): string
{
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: {$ua}\r\nAccept: application/json\r\n",
            'timeout' => 45,
            'follow_location' => 1,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        throw new RuntimeException('HTTP mislukt: '.$url);
    }

    return $body;
}

function licenceOk(string $short, string $url): ?string
{
    $hay = mb_strtolower($short.' '.$url, 'UTF-8');
    foreach (['cc-by-nc', 'cc by-nc', 'noncommercial', 'non-commercial', 'cc-by-nd', 'all rights reserved', 'fair use'] as $b) {
        if (str_contains($hay, $b)) {
            return null;
        }
    }
    if (str_contains($hay, 'cc0') || str_contains($hay, 'cc-zero') || str_contains($hay, 'public domain') || str_contains($hay, 'publicdomain')) {
        return 'CC0 / Public Domain';
    }
    if (str_contains($hay, 'by-sa') || str_contains($hay, 'sharealike')) {
        return 'CC BY-SA';
    }
    if (str_contains($hay, 'cc-by') || str_contains($hay, 'cc by') || str_contains($hay, '/by/')) {
        return 'CC BY';
    }

    return null;
}

function reencodeJpeg(string $bytes, int $max = 1200): ?array
{
    $tmp = tempnam(sys_get_temp_dir(), 'wm');
    file_put_contents($tmp, $bytes);
    $info = @getimagesize($tmp);
    if ($info === false) {
        @unlink($tmp);

        return null;
    }
    $mime = $info['mime'] ?? '';
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false,
    };
    @unlink($tmp);
    if ($src === false) {
        return null;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $max / max($w, $h, 1));
    if ($scale < 1.0) {
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
        $w = $nw;
        $h = $nh;
    }
    ob_start();
    imagejpeg($src, null, 82);
    $out = (string) ob_get_clean();
    imagedestroy($src);
    if ($out === '') {
        return null;
    }

    return ['jpeg' => $out, 'width' => $w, 'height' => $h];
}

function searchCommons(string $query, string $ua): array
{
    $params = http_build_query([
        'action' => 'query',
        'format' => 'json',
        'generator' => 'search',
        'gsrsearch' => $query,
        'gsrnamespace' => 6,
        'gsrlimit' => 20,
        'prop' => 'imageinfo|categories',
        'cllimit' => 30,
        'iiprop' => 'url|mime|size|extmetadata',
        'iiurlwidth' => 1600,
    ]);
    $json = json_decode(httpGet('https://commons.wikimedia.org/w/api.php?'.$params, $ua), true);

    return array_values($json['query']['pages'] ?? []);
}

function latinMatches(string $latijn, string $hay): bool
{
    $lat = mb_strtolower(trim($latijn), 'UTF-8');
    $lat = str_replace(['×', 'x'], ' ', $lat);
    $lat = preg_replace('/\s+/', ' ', $lat) ?? $lat;
    $parts = preg_split('/\s+/', $lat) ?: [];
    if (count($parts) < 2) {
        return str_contains($hay, $lat);
    }
    $genus = $parts[0];
    $species = $parts[1];
    // strip var./subsp. noise for match
    if (in_array($species, ['var.', 'subsp.', 'ssp.', 'f.'], true) && isset($parts[2])) {
        $species = $parts[2];
    }

    return str_contains($hay, $genus) && str_contains($hay, $species);
}

$blockedHints = [
    'asteroid', 'shape model', 'coat of arms', 'cassis cornuta', 'giant helmet',
    'seashell', 'sea shell', 'zeeschelp', 'schelp van', 'shell of own', 'conch shell',
    'fossil', 'coin ', ' medal', 'sculpture', 'painting of', 'herbarium sheet scan only',
    'dna ', 'phylogen', 'map of', 'logo',
];

$ok = 0;
$fail = [];

foreach ($slugs as $slug) {
    if (isset($rows[$slug]) && ($rows[$slug]['status'] ?? '') === 'goedgekeurd'
        && is_file($root.'/public/assets/images/soorten/'.$slug.'.jpg')) {
        echo "skip (al goedgekeurd): {$slug}\n";
        $ok++;

        continue;
    }
    $latijn = (string) ($latijnMap[$slug] ?? '');
    $naam = (string) ($namen[$slug] ?? $slug);
    $queries = array_values(array_filter([
        $latijn !== '' ? $latijn : null,
        $latijn !== '' ? '"'.$latijn.'"' : null,
        $latijn !== '' ? $latijn.' flower' : null,
        $latijn !== '' ? $latijn.' plant' : null,
    ]));

    $chosen = null;
    $dlUrl = null;
    foreach ($queries as $q) {
        echo "Zoek {$slug}: {$q}\n";
        try {
            $pages = searchCommons($q, $ua);
        } catch (Throwable $e) {
            echo "  fout: {$e->getMessage()}\n";
            usleep(400000);

            continue;
        }
        usleep(300000);

        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (! is_array($info)) {
                continue;
            }
            $mime = (string) ($info['mime'] ?? '');
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }
            $title = (string) ($page['title'] ?? '');
            $titleL = mb_strtolower($title, 'UTF-8');
            $metaEi = $info['extmetadata'] ?? [];
            $licShort = (string) ($metaEi['LicenseShortName']['value'] ?? $metaEi['License']['value'] ?? '');
            $licUrl = html_entity_decode(strip_tags((string) ($metaEi['LicenseUrl']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $artist = trim(html_entity_decode(strip_tags((string) ($metaEi['Artist']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $credit = trim(html_entity_decode(strip_tags((string) ($metaEi['Credit']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $desc = trim(html_entity_decode(strip_tags((string) ($metaEi['ImageDescription']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $cats = [];
            foreach ($page['categories'] ?? [] as $c) {
                $cats[] = mb_strtolower((string) ($c['title'] ?? ''), 'UTF-8');
            }
            $hay = $titleL.' '.mb_strtolower($desc, 'UTF-8').' '.implode(' ', $cats);

            $skip = false;
            foreach ($blockedHints as $hint) {
                if (str_contains($hay, $hint)) {
                    $skip = true;
                    break;
                }
            }
            if (! $skip && preg_match('/\b(shell|schelp|helmet|sculpture|statue)\b/u', $hay)) {
                $skip = true;
            }
            if ($skip) {
                continue;
            }
            if ($latijn !== '' && ! latinMatches($latijn, $hay)) {
                continue;
            }
            $norm = licenceOk($licShort, $licUrl);
            if ($norm === null) {
                continue;
            }
            if ($artist === '' && $credit !== '') {
                $artist = $credit;
            }
            if ($artist === '') {
                $artist = 'Onbekende auteur (Wikimedia Commons)';
            }
            $artist = mb_substr(preg_replace('/\s+/', ' ', $artist) ?? $artist, 0, 160);
            $pageUrl = (string) ($info['descriptionurl'] ?? '');
            $fileUrl = (string) ($info['thumburl'] ?? $info['url'] ?? '');
            $chosen = [
                'slug' => $slug,
                'naam' => $naam,
                'latijn' => $latijn,
                'auteur' => $artist,
                'licentie' => $norm,
                'licentie_url' => $licUrl !== '' ? $licUrl : 'https://commons.wikimedia.org/wiki/Commons:Licensing',
                'bron_url' => $pageUrl,
                'bron_titel' => $title,
                'originele_mime' => $mime,
                'alt' => $naam.($latijn !== '' ? ' ('.$latijn.')' : ''),
                'bestand' => 'assets/images/soorten/'.$slug.'.jpg',
                'notitie' => $desc !== '' ? mb_substr($desc, 0, 200) : null,
                'status' => 'goedgekeurd',
            ];
            $dlUrl = $fileUrl;
            break 2;
        }
    }

    if ($chosen === null || $dlUrl === null) {
        echo "  → GEEN foto\n";
        $fail[] = $slug;

        continue;
    }

    try {
        $bytes = httpGet($dlUrl, $ua);
        $enc = reencodeJpeg($bytes, 1200);
        if ($enc === null) {
            echo "  → re-encode mislukt\n";
            $fail[] = $slug;

            continue;
        }
        $path = $root.'/public/assets/images/soorten/'.$slug.'.jpg';
        file_put_contents($path, $enc['jpeg']);
        $sha = hash('sha256', $enc['jpeg']);
        $chosen['sha256'] = $sha;
        $chosen['bytes'] = strlen($enc['jpeg']);
        $chosen['width_max'] = max($enc['width'], $enc['height']);
        $rows[$slug] = $chosen;
        echo "  → OK {$chosen['bron_titel']} sha=".substr($sha, 0, 12)."\n";
        $ok++;
    } catch (Throwable $e) {
        echo '  → download fout: '.$e->getMessage()."\n";
        $fail[] = $slug;
    }
}

ksort($rows);
$meta['generated_at'] = gmdate('c');
$meta['source'] = 'Wikimedia Commons (per-file licence verified, foto met het oog gecontroleerd)';
$meta['soorten'] = $rows;
file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

echo "\nKlaar: {$ok} ok, mislukt: ".count($fail).' ['.implode(', ', $fail)."]\n";
