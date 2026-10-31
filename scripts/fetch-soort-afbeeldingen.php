#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Eenmalig: haal per stektip-soort een vrij gelicenseerde foto van Wikimedia Commons.
 * Alleen CC0 / Public Domain / CC-BY / CC-BY-SA. Self-host na download + herschrijven (GD).
 *
 * Gebruik: php scripts/fetch-soort-afbeeldingen.php
 */
$root = dirname(__DIR__);
$stektips = json_decode((string) file_get_contents($root.'/data/stektips.json'), true);
$soorten = $stektips['soorten'] ?? [];
$outDir = $root.'/public/assets/images/soorten';
@mkdir($outDir, 0755, true);
$metaPath = $root.'/data/soort-afbeeldingen.json';

$allowedLicenceHints = [
    'cc0',
    'cc-zero',
    'public domain',
    'pd-',
    'creativecommons.org/publicdomain',
    'cc-by',
    'cc by',
    'attribution',
    'creativecommons.org/licenses/by',
];

$blockedHints = [
    'cc-by-nc',
    'cc by-nc',
    'noncommercial',
    'non-commercial',
    'cc-by-nd',
    'noDerivatives',
    'all rights reserved',
    'fair use',
];

$ua = 'GratisPlantenBot/0.25 (https://gratisplanten.nl; info@gratisplanten.nl) PHP-script';

function httpGet(string $url, string $ua): string
{
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: {$ua}\r\nAccept: application/json\r\n",
            'timeout' => 30,
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

function licenceOk(string $short, string $url, array $allowed, array $blocked): ?string
{
    $hay = mb_strtolower($short.' '.$url, 'UTF-8');
    foreach ($blocked as $b) {
        if (str_contains($hay, mb_strtolower($b, 'UTF-8'))) {
            return null;
        }
    }
    foreach ($allowed as $a) {
        if (str_contains($hay, mb_strtolower($a, 'UTF-8'))) {
            // Normaliseer label
            if (str_contains($hay, 'cc0') || str_contains($hay, 'cc-zero') || str_contains($hay, 'public domain') || str_contains($hay, 'publicdomain')) {
                return 'CC0 / Public Domain';
            }
            if (str_contains($hay, 'by-sa') || str_contains($hay, 'sharealike')) {
                return 'CC BY-SA';
            }
            if (str_contains($hay, 'cc-by') || str_contains($hay, 'cc by') || str_contains($hay, '/by/')) {
                return 'CC BY';
            }

            return trim($short) !== '' ? trim($short) : 'Vrije licentie';
        }
    }

    return null;
}

function reencodeJpeg(string $bytes, int $max = 1200): ?string
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
    }
    ob_start();
    imagejpeg($src, null, 82);
    $out = (string) ob_get_clean();
    imagedestroy($src);

    return $out !== '' ? $out : null;
}

function searchCommons(string $query, string $ua): array
{
    $params = http_build_query([
        'action' => 'query',
        'format' => 'json',
        'generator' => 'search',
        'gsrsearch' => $query,
        'gsrnamespace' => 6,
        'gsrlimit' => 12,
        'prop' => 'imageinfo',
        'iiprop' => 'url|mime|size|extmetadata',
        'iiurlwidth' => 1600,
    ]);
    $url = 'https://commons.wikimedia.org/w/api.php?'.$params;
    $json = json_decode(httpGet($url, $ua), true);
    $pages = $json['query']['pages'] ?? [];

    return is_array($pages) ? array_values($pages) : [];
}

$results = [
    'generated_at' => gmdate('c'),
    'source' => 'Wikimedia Commons (per-file licence verified)',
    'soorten' => [],
];

$ok = 0;
$skip = 0;

foreach ($soorten as $soort) {
    $slug = (string) ($soort['slug'] ?? '');
    $naam = (string) ($soort['naam'] ?? '');
    $latijn = trim((string) ($soort['latijn'] ?? ''));
    if ($slug === '') {
        continue;
    }

    $queries = array_values(array_filter([
        $latijn !== '' ? $latijn : null,
        $latijn !== '' ? $latijn.' plant' : null,
        $naam,
    ]));

    $chosen = null;
    foreach ($queries as $q) {
        echo "Zoek {$slug}: {$q}\n";
        try {
            $pages = searchCommons($q, $ua);
        } catch (Throwable $e) {
            echo "  fout: {$e->getMessage()}\n";
            usleep(400000);

            continue;
        }
        usleep(350000);

        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            if (! is_array($info)) {
                continue;
            }
            $mime = (string) ($info['mime'] ?? '');
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }
            $title = mb_strtolower((string) ($page['title'] ?? ''), 'UTF-8');
            // Skip duidelijke niet-planttreffers (bijv. planetoïde “Begonia”).
            if (str_contains($title, 'asteroid') || str_contains($title, 'shape model') || str_contains($title, 'coat of arms')) {
                continue;
            }
            $meta = $info['extmetadata'] ?? [];
            $licShort = (string) ($meta['LicenseShortName']['value'] ?? $meta['License']['value'] ?? '');
            $licUrl = html_entity_decode(strip_tags((string) ($meta['LicenseUrl']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $artist = trim(html_entity_decode(strip_tags((string) ($meta['Artist']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $credit = trim(html_entity_decode(strip_tags((string) ($meta['Credit']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $desc = trim(html_entity_decode(strip_tags((string) ($meta['ImageDescription']['value'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $pageUrl = (string) ($info['descriptionurl'] ?? '');
            $fileUrl = (string) ($info['thumburl'] ?? $info['url'] ?? '');

            $norm = licenceOk($licShort, $licUrl, $allowedLicenceHints, $blockedHints);
            if ($norm === null) {
                continue;
            }
            if ($artist === '' && $credit !== '') {
                $artist = $credit;
            }
            if ($artist === '') {
                $artist = 'Onbekende auteur (Wikimedia Commons)';
            }
            // Beperk artist-lengte
            $artist = mb_substr(preg_replace('/\s+/', ' ', $artist) ?? $artist, 0, 160);

            $chosen = [
                'slug' => $slug,
                'naam' => $naam,
                'latijn' => $latijn,
                'auteur' => $artist,
                'licentie' => $norm,
                'licentie_url' => $licUrl !== '' ? $licUrl : 'https://commons.wikimedia.org/wiki/Commons:Licensing',
                'bron_url' => $pageUrl,
                'bron_titel' => (string) ($page['title'] ?? ''),
                'originele_mime' => $mime,
                'alt' => $naam.($latijn !== '' ? ' ('.$latijn.')' : ''),
                'bestand' => 'assets/images/soorten/'.$slug.'.jpg',
                'notitie' => $desc !== '' ? mb_substr($desc, 0, 200) : null,
            ];
            $dlUrl = $fileUrl;
            break 2;
        }
    }

    if ($chosen === null) {
        echo "  → geen vrije foto\n";
        $skip++;

        continue;
    }

    try {
        $bytes = httpGet($dlUrl, $ua);
        $jpeg = reencodeJpeg($bytes, 1200);
        if ($jpeg === null) {
            echo "  → decode/re-encode mislukt\n";
            $skip++;

            continue;
        }
        $dest = $outDir.'/'.$slug.'.jpg';
        file_put_contents($dest, $jpeg);
        $chosen['bytes'] = strlen($jpeg);
        $chosen['width_max'] = 1200;
        $results['soorten'][$slug] = $chosen;
        $ok++;
        echo "  → OK {$chosen['licentie']} — {$chosen['auteur']}\n";
    } catch (Throwable $e) {
        echo "  → download fout: {$e->getMessage()}\n";
        $skip++;
    }
    usleep(250000);
}

ksort($results['soorten']);
$results['count'] = count($results['soorten']);
$results['missing'] = array_values(array_diff(
    array_map(static fn ($s) => (string) $s['slug'], $soorten),
    array_keys($results['soorten'])
));

file_put_contents(
    $metaPath,
    json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo "\nKlaar: {$ok} met beeld, ".count($results['missing'])." zonder (fallback).\n";
echo "Meta: {$metaPath}\n";
