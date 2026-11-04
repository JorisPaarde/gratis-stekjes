#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Vervang problematische 0.69.0-foto's (herbarium, verkeerde cultivar, te insect-gericht)
 * door expliciete Commons-bestanden. Latijn + licentie worden opnieuw gecontroleerd.
 */
$root = dirname(__DIR__);
$ua = 'GratisPlantenBot/0.69 (https://gratisplanten.nl; info@gratisplanten.nl) PHP-script';

// slug => Commons File: titel (zonder File: prefix mag ook)
$wanted = [
    // Geen kohlrabi: echte bladkool / perennial kale
    'eeuwig-moes' => 'Brassica oleracea var. acephala (Ostfriesische Palme).jpg',
    // Levende planten i.p.v. herbarium
    'sleedoorn' => 'Prunus spinosa 006.jpg',
    'zevenblad' => 'Aegopodium podagraria 001.JPG',
    'lijsterbes' => 'Sorbus aucuparia 001.jpg',
    'bosaardbei' => 'Fragaria vesca 002.JPG',
    'bosbes' => 'Vaccinium myrtillus 002.JPG',
    'brandnetel' => 'Urtica dioica 004.JPG',
    // Roos duidelijker dan kever-macro
    'hondsroos' => 'Rosa canina 003.JPG',
    // Kattenstaart zonder dominant insect
    'grote-kattenstaart' => 'Lythrum salicaria 002.JPG',
];

// Fallbacks if first title 404s
$fallbacks = [
    'eeuwig-moes' => [
        'Brassica oleracea var. sabellica - Hamburg-Poppenbüttel - Poppenbütteler Hauptstraße 26. Juni 2011.jpg',
        'Grünkohl (Brassica oleracea var. sabellica) 01.jpg',
        'Brassica oleracea Acephala Group.jpg',
    ],
    'sleedoorn' => [
        'Prunus spinosa flowers.jpg',
        'Blackthorn (Prunus spinosa).jpg',
        'Prunus spinosa Sturm56.jpg',
    ],
    'zevenblad' => [
        'Aegopodium podagraria 20050520 3486.jpg',
        'Aegopodium podagraria 002.JPG',
        'Giersch (Aegopodium podagraria).jpg',
    ],
    'lijsterbes' => [
        'Sorbus aucuparia 002.JPG',
        'Vogelbeere (Sorbus aucuparia).jpg',
        'Sorbus aucuparia fruit.jpg',
    ],
    'bosaardbei' => [
        'Fragaria vesca 001.JPG',
        'Fragaria vesca fruits.jpg',
        'Wald-Erdbeere (Fragaria vesca).jpg',
    ],
    'bosbes' => [
        'Vaccinium myrtillus 001.JPG',
        'Heidelbeere (Vaccinium myrtillus).jpg',
        'Vaccinium myrtillus berries.jpg',
    ],
    'brandnetel' => [
        'Urtica dioica 001.JPG',
        'Große Brennnessel (Urtica dioica).jpg',
        'Urtica dioica flower.jpg',
    ],
    'hondsroos' => [
        'Rosa canina 001.JPG',
        'Rosa canina flower.jpg',
        'Hundsrose (Rosa canina).jpg',
    ],
    'grote-kattenstaart' => [
        'Lythrum salicaria 001.JPG',
        'Blutweiderich (Lythrum salicaria).jpg',
        'Lythrum salicaria flowers.jpg',
    ],
];

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
    foreach (['cc-by-nc', 'noncommercial', 'cc-by-nd', 'all rights reserved', 'fair use'] as $b) {
        if (str_contains($hay, $b)) {
            return null;
        }
    }
    if (str_contains($hay, 'cc0') || str_contains($hay, 'public domain') || str_contains($hay, 'publicdomain')) {
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

    return $out !== '' ? ['jpeg' => $out, 'width' => $w, 'height' => $h] : null;
}

function fetchFileInfo(string $title, string $ua): ?array
{
    if (! str_starts_with($title, 'File:')) {
        $title = 'File:'.$title;
    }
    $params = http_build_query([
        'action' => 'query',
        'format' => 'json',
        'titles' => $title,
        'prop' => 'imageinfo|categories',
        'cllimit' => 40,
        'iiprop' => 'url|mime|size|extmetadata',
        'iiurlwidth' => 1600,
    ]);
    $json = json_decode(httpGet('https://commons.wikimedia.org/w/api.php?'.$params, $ua), true);
    $pages = $json['query']['pages'] ?? [];
    $page = array_values($pages)[0] ?? null;
    if (! is_array($page) || isset($page['missing'])) {
        return null;
    }

    return $page;
}

function searchBetter(string $query, string $ua, array $blockTitle): ?array
{
    $params = http_build_query([
        'action' => 'query',
        'format' => 'json',
        'generator' => 'search',
        'gsrsearch' => $query,
        'gsrnamespace' => 6,
        'gsrlimit' => 25,
        'prop' => 'imageinfo|categories',
        'cllimit' => 30,
        'iiprop' => 'url|mime|size|extmetadata',
        'iiurlwidth' => 1600,
    ]);
    $json = json_decode(httpGet('https://commons.wikimedia.org/w/api.php?'.$params, $ua), true);
    foreach (array_values($json['query']['pages'] ?? []) as $page) {
        $title = mb_strtolower((string) ($page['title'] ?? ''), 'UTF-8');
        $bad = false;
        foreach ($blockTitle as $b) {
            if (str_contains($title, $b)) {
                $bad = true;
                break;
            }
        }
        if ($bad) {
            continue;
        }
        $info = $page['imageinfo'][0] ?? null;
        if (! is_array($info)) {
            continue;
        }
        $mime = (string) ($info['mime'] ?? '');
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            continue;
        }
        $meta = $info['extmetadata'] ?? [];
        $lic = licenceOk(
            (string) ($meta['LicenseShortName']['value'] ?? ''),
            html_entity_decode(strip_tags((string) ($meta['LicenseUrl']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        if ($lic === null) {
            continue;
        }

        return $page;
    }

    return null;
}

$metaPath = $root.'/data/soort-afbeeldingen.json';
$meta = json_decode((string) file_get_contents($metaPath), true);
$rows = $meta['soorten'];
$manifest = json_decode((string) file_get_contents($root.'/storage/app/nieuwe-soorten-0.69.0.json'), true);
$latijnMap = $manifest['latijn'];
$namen = $manifest['namen'];

foreach ($wanted as $slug => $primary) {
    $candidates = array_merge([$primary], $fallbacks[$slug] ?? []);
    $page = null;
    foreach ($candidates as $title) {
        echo "Probeer {$slug}: {$title}\n";
        try {
            $page = fetchFileInfo($title, $ua);
        } catch (Throwable $e) {
            echo '  fout '.$e->getMessage()."\n";
            $page = null;
        }
        usleep(250000);
        if ($page !== null) {
            break;
        }
    }
    if ($page === null) {
        $q = ($latijnMap[$slug] ?? $slug).' -herbarium';
        if ($slug === 'eeuwig-moes') {
            $q = 'Brassica oleracea sabellica OR "acephala" kale -gongylodes -kohlrabi';
        }
        echo "  zoek fallback: {$q}\n";
        $page = searchBetter($q, $ua, ['herbarium', 'gongylodes', 'kohlrabi', 'trichius', 'gonepteryx']);
        usleep(300000);
    }
    if ($page === null) {
        echo "  MISLUKT {$slug}\n";

        continue;
    }

    $info = $page['imageinfo'][0];
    $metaEi = $info['extmetadata'] ?? [];
    $licShort = (string) ($metaEi['LicenseShortName']['value'] ?? '');
    $licUrl = html_entity_decode(strip_tags((string) ($metaEi['LicenseUrl']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $norm = licenceOk($licShort, $licUrl);
    if ($norm === null) {
        echo "  licentie geweigerd {$slug}\n";

        continue;
    }
    $artist = trim(html_entity_decode(strip_tags((string) ($metaEi['Artist']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($artist === '') {
        $artist = trim(html_entity_decode(strip_tags((string) ($metaEi['Credit']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($artist === '') {
        $artist = 'Onbekende auteur (Wikimedia Commons)';
    }
    $artist = mb_substr(preg_replace('/\s+/', ' ', $artist) ?? $artist, 0, 160);
    $desc = trim(html_entity_decode(strip_tags((string) ($metaEi['ImageDescription']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $dl = (string) ($info['thumburl'] ?? $info['url'] ?? '');
    $bytes = httpGet($dl, $ua);
    $enc = reencodeJpeg($bytes, 1200);
    if ($enc === null) {
        echo "  re-encode mislukt {$slug}\n";

        continue;
    }
    $path = $root.'/public/assets/images/soorten/'.$slug.'.jpg';
    file_put_contents($path, $enc['jpeg']);
    $sha = hash('sha256', $enc['jpeg']);
    $naam = $namen[$slug] ?? $slug;
    $latijn = $latijnMap[$slug] ?? '';
    $rows[$slug] = [
        'slug' => $slug,
        'naam' => $naam,
        'latijn' => $latijn,
        'auteur' => $artist,
        'licentie' => $norm,
        'licentie_url' => $licUrl !== '' ? $licUrl : 'https://commons.wikimedia.org/wiki/Commons:Licensing',
        'bron_url' => (string) ($info['descriptionurl'] ?? ''),
        'bron_titel' => (string) ($page['title'] ?? ''),
        'originele_mime' => (string) ($info['mime'] ?? 'image/jpeg'),
        'alt' => $naam.($latijn !== '' ? ' ('.$latijn.')' : ''),
        'bestand' => 'assets/images/soorten/'.$slug.'.jpg',
        'notitie' => $desc !== '' ? mb_substr($desc, 0, 200) : null,
        'status' => 'goedgekeurd',
        'sha256' => $sha,
        'bytes' => strlen($enc['jpeg']),
        'width_max' => max($enc['width'], $enc['height']),
    ];
    echo "  OK {$page['title']} sha=".substr($sha, 0, 12)."\n";
}

ksort($rows);
$meta['soorten'] = $rows;
$meta['generated_at'] = gmdate('c');
file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
echo "Done\n";
