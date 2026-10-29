<?php

declare(strict_types=1);

/**
 * Lokale audit-crawl: statuscodes + interne/externe links.
 * Gebruik: php scripts/audit-crawl.php [baseUrl]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'audit-cookies');
$report = [
    'base' => $base,
    'started_at' => date('c'),
    'pages' => [],
    'errors_5xx' => [],
    'unexpected_404' => [],
    'broken_internal' => [],
    'broken_assets' => [],
    'external_dead' => [],
    'external_ok' => [],
];

function httpRequest(string $url, string $cookieJar, string $method = 'GET', array $opts = []): array
{
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'GratisPlantenAuditBot/0.17',
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$headers) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))][] = trim($parts[1]);
            }

            return $len;
        },
    ]);
    if (! empty($opts['post'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post']);
    }
    if (! empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = is_string($raw) ? substr($raw, $headerSize) : '';

    return compact('status', 'body', 'headers', 'errno', 'err');
}

function absoluteUrl(string $base, string $href): ?string
{
    $href = trim(html_entity_decode($href));
    if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
        return null;
    }
    if (str_starts_with($href, '//')) {
        return 'https:'.$href;
    }
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    if (str_starts_with($href, '/')) {
        return rtrim($base, '/').$href;
    }

    return rtrim($base, '/').'/'.ltrim($href, '/');
}

function extractLinks(string $html): array
{
    $out = ['href' => [], 'src' => []];
    if (preg_match_all('/\b(?:href|src)=["\']([^"\']+)["\']/i', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $attr = stripos($match[0], 'href=') === 0 || stripos($match[0], 'href=') !== false && preg_match('/\bhref=/i', $match[0]) ? 'href' : 'src';
            if (preg_match('/\bhref=/i', $match[0])) {
                $out['href'][] = $match[1];
            } else {
                $out['src'][] = $match[1];
            }
        }
    }

    return $out;
}

$stektips = app(App\Services\Stektips::class)->all();
$steden = app(App\Services\Steden::class)->all();
$organisaties = app(App\Services\Organisaties::class)->all();

$paths = [
    '/', '/privacy', '/initiatieven', '/deelplekken',
    '/voor-organisaties', '/voor-organisaties/aanmelden', '/organisaties',
    '/stijlgids', '/robots.txt', '/sitemap.xml',
    '/hoe-werkt-het', '/hulpmiddelen', '/over', '/invasieve-soorten', '/stektips', '/gratis-planten',
    '/aanbod', '/api/zoeken?q=Haarlem', '/api/partners', '/api/deelplekken', '/api/nu-te-stekken',
    '/account/registreren', '/account/inloggen', '/account/wachtwoord-vergeten', '/beheer',
];
foreach ($stektips as $s) {
    $paths[] = '/stektips/'.$s['slug'];
}
foreach ($steden as $s) {
    $paths[] = '/gratis-planten/'.$s['slug'];
}
foreach ($organisaties as $o) {
    $paths[] = '/organisaties/'.$o['slug'];
}

$expected404 = [
    '/stektips/onbestaand-slug-xyz',
    '/gratis-planten/onbestaande-stad-xyz',
    '/organisaties/onbestaande-org-xyz',
];
$seenInternal = [];
$seenExternal = [];
$seenAsset = [];

foreach (array_merge($paths, $expected404) as $path) {
    $url = $base.$path;
    $res = httpRequest($url, $cookieJar);
    $status = $res['status'];
    $report['pages'][] = ['path' => $path, 'status' => $status];
    $expect404 = in_array($path, $expected404, true);
    if ($status >= 500) {
        $report['errors_5xx'][] = ['path' => $path, 'status' => $status];
    } elseif ($status === 404 && ! $expect404) {
        $report['unexpected_404'][] = $path;
    } elseif ($expect404 && $status !== 404) {
        $report['unexpected_404'][] = "$path (verwacht 404, kreeg $status)";
    }

    if ($status >= 200 && $status < 400 && is_string($res['body']) && str_contains((string) ($res['headers']['content-type'][0] ?? ''), 'html')) {
        $links = extractLinks($res['body']);
        foreach (array_merge($links['href'], $links['src']) as $raw) {
            $abs = absoluteUrl($base, $raw);
            if ($abs === null) {
                continue;
            }
            $isAsset = in_array($raw, $links['src'], true) || preg_match('#\.(css|js|png|jpe?g|webp|svg|woff2?|ico)(\?|$)#i', $abs);
            if (str_starts_with($abs, $base)) {
                $rel = substr($abs, strlen($base)) ?: '/';
                if (! isset($seenInternal[$rel])) {
                    $seenInternal[$rel] = $path;
                }
            } elseif ($isAsset || str_contains($abs, 'unpkg.com') || str_contains($abs, 'tile.openstreetmap')) {
                $seenAsset[$abs] = $path;
            } else {
                $seenExternal[$abs] = $path;
            }
        }
    }
}

// Login as demo user if present
$demo = App\Models\User::query()->where('email', 'demo1@example.com')->first();
if ($demo) {
    $loginPage = httpRequest($base.'/account/inloggen', $cookieJar);
    if (preg_match('/name="_token" value="([^"]+)"/', $loginPage['body'], $tm)) {
        httpRequest($base.'/account/inloggen', $cookieJar, 'POST', [
            'post' => http_build_query([
                '_token' => $tm[1],
                'email' => 'demo1@example.com',
                'password' => 'password',
                'website' => '',
                'form_started' => time() - 10,
            ]),
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
    }
    foreach (['/account', '/account/profiel', '/account/wachtwoord', '/account/email', '/account/aanbod', '/account/inbox', '/account/export', '/account/verwijderen'] as $path) {
        $res = httpRequest($base.$path, $cookieJar);
        $report['pages'][] = ['path' => $path.' (auth)', 'status' => $res['status']];
        if ($res['status'] >= 500) {
            $report['errors_5xx'][] = ['path' => $path.' (auth)', 'status' => $res['status']];
        }
    }
    $conv = App\Models\Conversation::query()->first();
    if ($conv) {
        $path = '/account/gesprek/'.$conv->id;
        $res = httpRequest($base.$path, $cookieJar);
        $report['pages'][] = ['path' => $path.' (auth)', 'status' => $res['status']];
        if ($res['status'] >= 500) {
            $report['errors_5xx'][] = ['path' => $path.' (auth)', 'status' => $res['status']];
        }
    }
}

// Admin
$adminLogin = httpRequest($base.'/beheer', $cookieJar);
if (preg_match('/name="_token" value="([^"]+)"/', $adminLogin['body'], $tm)) {
    $pass = (string) config('stekjes.admin_password');
    if ($pass !== '') {
        httpRequest($base.'/beheer', $cookieJar, 'POST', [
            'post' => http_build_query([
                '_token' => $tm[1],
                'password' => $pass,
                'website' => '',
                'form_started' => time() - 10,
            ]),
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $res = httpRequest($base.'/beheer/overzicht', $cookieJar);
        $report['pages'][] = ['path' => '/beheer/overzicht (admin)', 'status' => $res['status']];
        if ($res['status'] >= 500) {
            $report['errors_5xx'][] = ['path' => '/beheer/overzicht', 'status' => $res['status']];
        }
    }
}

foreach ($seenInternal as $rel => $from) {
    if (isset($seenInternal[$rel]) && str_contains($rel, '/storage/')) {
        continue;
    }
    $res = httpRequest($base.$rel, $cookieJar, 'HEAD');
    if ($res['status'] === 405 || $res['status'] === 501) {
        $res = httpRequest($base.$rel, $cookieJar, 'GET');
    }
    if ($res['status'] >= 400 && $res['status'] !== 302 && $res['status'] !== 301) {
        // GET forms / signed routes may 403/404 when unauthenticated — skip auth-only
        if (str_starts_with($rel, '/account/') && ! str_contains($rel, 'inloggen') && ! str_contains($rel, 'registreren') && ! str_contains($rel, 'wachtwoord')) {
            continue;
        }
        if (str_starts_with($rel, '/beheer/') && $rel !== '/beheer') {
            continue;
        }
        $report['broken_internal'][] = ['url' => $rel, 'status' => $res['status'], 'from' => $from];
    }
}

foreach ($seenAsset as $url => $from) {
    $res = httpRequest($url, $cookieJar, 'HEAD');
    if ($res['status'] === 405 || $res['status'] === 0) {
        $res = httpRequest($url, $cookieJar, 'GET');
    }
    if ($res['status'] >= 400 || $res['errno']) {
        $report['broken_assets'][] = ['url' => $url, 'status' => $res['status'], 'err' => $res['err'], 'from' => $from];
    }
}

foreach ($seenExternal as $url => $from) {
    $res = httpRequest($url, $cookieJar, 'HEAD');
    if ($res['status'] === 405 || $res['status'] === 403 || $res['status'] === 0) {
        $res = httpRequest($url, $cookieJar, 'GET');
    }
    if ($res['status'] >= 400 || $res['errno'] || $res['status'] === 0) {
        $report['external_dead'][] = ['url' => $url, 'status' => $res['status'], 'err' => $res['err'], 'from' => $from];
    } else {
        $report['external_ok'][] = $url;
    }
}

$outPath = __DIR__.'/../storage/app/audit-crawl-report.json';
@mkdir(dirname($outPath), 0775, true);
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Pages: ".count($report['pages'])."\n";
echo "5xx: ".count($report['errors_5xx'])."\n";
echo "unexpected 404: ".count($report['unexpected_404'])."\n";
echo "broken internal: ".count($report['broken_internal'])."\n";
echo "broken assets: ".count($report['broken_assets'])."\n";
echo "external dead: ".count($report['external_dead'])."\n";
echo "Wrote $outPath\n";
if ($report['errors_5xx'] || $report['broken_internal'] || $report['broken_assets']) {
    echo json_encode([
        'errors_5xx' => $report['errors_5xx'],
        'broken_internal' => $report['broken_internal'],
        'broken_assets' => $report['broken_assets'],
        'external_dead' => $report['external_dead'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    exit(1);
}
exit(0);
