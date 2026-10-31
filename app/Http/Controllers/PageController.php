<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ervaring;
use App\Services\Kennisbank;
use App\Services\NuTeStekken;
use App\Services\Organisaties;
use App\Services\Plantensoorten;
use App\Services\Steden;
use App\Services\Stektips;
use App\Support\CanonicalUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(NuTeStekken $calendar, Plantensoorten $plantensoorten): View
    {
        $site = config('site.name');
        $title = 'Gratis planten en stekjes delen in je buurt';
        $heading = 'Gratis planten en stekjes in je buurt';
        $metaDescription = config('site.description');
        $homeUrl = CanonicalUrl::absolute('/');
        $logoUrl = CanonicalUrl::asset((string) config('site.og_image', '/assets/icons/icon-512.png'));
        $areaServed = [
            ['@type' => 'Country', 'name' => 'NL'],
            ['@type' => 'Country', 'name' => 'BE'],
        ];

        $ervaringenBlok = null;
        if (config('stekjes.ervaringen_home_block_enabled') && Schema::hasTable('ervaringen')) {
            $rows = Ervaring::query()
                ->approved()
                ->with('user:id,name')
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->limit(3)
                ->get();
            if ($rows->isNotEmpty()) {
                $ervaringenBlok = $rows->map(function (Ervaring $e) use ($plantensoorten) {
                    $soort = $e->plantensoort_id ? $plantensoorten->find((int) $e->plantensoort_id) : null;

                    return [
                        'body' => $e->body,
                        'plaatsnaam' => $e->plaatsnaam,
                        'naam' => $e->user?->name ?? 'Lid',
                        'soort' => $soort['naam'] ?? null,
                        'has_photo' => $e->hasPhoto(),
                        'photo_url' => $e->hasPhoto() ? route('ervaringen.foto', $e) : null,
                    ];
                })->all();
            }
        }

        return view('pages.home', [
            'title' => $title,
            'heading' => $heading,
            'metaDescription' => $metaDescription,
            'canonical' => $homeUrl,
            'nuTeStekken' => $calendar->blockForViews(),
            'ervaringenBlok' => $ervaringenBlok,
            'schema' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $homeUrl.'#organization',
                        'name' => 'Gratis planten',
                        'url' => $homeUrl,
                        'logo' => $logoUrl,
                        'areaServed' => $areaServed,
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $homeUrl.'#website',
                        'name' => $site,
                        'url' => $homeUrl,
                        'description' => $metaDescription,
                        'inLanguage' => 'nl',
                        'publisher' => ['@id' => $homeUrl.'#organization'],
                        'areaServed' => $areaServed,
                    ],
                ],
            ],
        ]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', [
            'title' => 'Privacy',
            'metaDescription' => 'Privacyverklaring van '.config('site.name').': welke gegevens ik bewaar, waarom, en hoe je ze kunt inzien of laten wissen.',
            'canonical' => CanonicalUrl::absolute('/privacy'),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'title' => 'Contact',
            'metaDescription' => 'Neem contact op met '.config('site.name').' via e-mail. Vragen over delen, account of de site beantwoord ik persoonlijk.',
            'canonical' => route('contact'),
        ]);
    }

    public function stijlgids(): View
    {
        return view('pages.stijlgids', [
            'title' => 'Stijlgids',
            'metaDescription' => 'Interne stijlgids (kleuren en iconen) van '.config('site.name').'.',
            'canonical' => url('/stijlgids'),
        ]);
    }

    /**
     * Env-aware robots.txt-body (staging/noindex → disallow-all; production → allow + sitemap).
     * Hergebruikt door de /robots.txt-route en door DeployController (statisch bestand in docroot).
     */
    public function robotsBody(): string
    {
        if (config('site.noindex') || app()->environment('staging')) {
            return "User-agent: *\nDisallow: /\n";
        }

        // Canonieke productie-URL (gelijk aan scripts/write-robots-txt.sh).
        return "User-agent: *\nAllow: /\nSitemap: https://gratisplanten.nl/sitemap.xml\n";
    }

    public function robots(): Response
    {
        return response($this->robotsBody(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Web app manifest (PWA). Zelfde inhoud als public/manifest.webmanifest;
     * route zorgt voor juiste Content-Type in tests en als fallback zonder static file.
     */
    public function manifest(): Response
    {
        $name = (string) config('site.name');
        $payload = [
            'id' => '/',
            'name' => $name,
            'short_name' => $name,
            'description' => (string) config('site.description'),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'lang' => 'nl',
            'dir' => 'ltr',
            'background_color' => (string) config('site.background_color'),
            'theme_color' => (string) config('site.theme_color'),
            'icons' => [
                [
                    'src' => '/assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/icons/icon-192-maskable.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/assets/icons/icon-512-maskable.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return response(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n",
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    public function sitemap(Stektips $stektips, Steden $steden, Organisaties $organisaties, Kennisbank $kennisbank): Response
    {
        $staticLastmod = $this->lastmodFromFiles([
            base_path('VERSION'),
            resource_path('views/pages/home.blade.php'),
            resource_path('views/layouts/site.blade.php'),
        ]);

        $entries = [
            ['loc' => CanonicalUrl::absolute('/'), 'lastmod' => $staticLastmod],
            ['loc' => route('hoe-werkt-het'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/hoe-werkt-het.blade.php')])],
            ['loc' => route('aanbod'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/aanbod.blade.php')])],
            ['loc' => route('stektips.index'), 'lastmod' => $this->lastmodFromFiles([base_path('data/stektips.json'), base_path('data/kennisbank')])],
            ['loc' => route('hulpmiddelen'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/hulpmiddelen.blade.php')])],
            ['loc' => route('over'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/over.blade.php')])],
            ['loc' => route('privacy'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/privacy.blade.php')])],
            ['loc' => route('contact'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/contact.blade.php')])],
            ['loc' => route('partners'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/partners.blade.php')])],
            ['loc' => route('deelplekken'), 'lastmod' => $this->lastmodFromFiles([base_path('data/deelplekken.json')])],
            ['loc' => route('voor-organisaties'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/voor-organisaties.blade.php')])],
            ['loc' => route('voor-organisaties.aanmelden'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/voor-organisaties-aanmelden.blade.php')])],
            ['loc' => route('organisaties.index'), 'lastmod' => $this->lastmodFromFiles([base_path('data/organisaties.json')])],
            ['loc' => route('invasieve-soorten'), 'lastmod' => $this->lastmodFromFiles([base_path('data/niet-aanbieden.json')])],
            ['loc' => route('gratis-planten.index'), 'lastmod' => $this->lastmodFromFiles([base_path('data/steden.json')])],
            ['loc' => route('volkstuinen'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/audience/volkstuinen.blade.php')])],
            ['loc' => route('help-mee'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/audience/help-mee.blade.php')])],
            ['loc' => route('natuur'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/audience/natuur.blade.php')])],
            ['loc' => route('verbinding'), 'lastmod' => $this->lastmodFromFiles([resource_path('views/pages/audience/verbinding.blade.php')])],
        ];

        // /ervaringen alleen in sitemap als er goedgekeurde ervaringen zijn (anders noindex/leeg).
        if (Schema::hasTable('ervaringen') && Ervaring::query()->approved()->exists()) {
            $latest = Ervaring::query()->approved()->max('updated_at');
            $entries[] = [
                'loc' => route('ervaringen.index'),
                'lastmod' => $this->formatLastmod($latest ? strtotime((string) $latest) : null) ?? $staticLastmod,
            ];
        }

        $kennisDir = base_path('data/kennisbank');
        foreach ($kennisbank->all() as $artikel) {
            $slug = (string) ($artikel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $file = $kennisDir.'/'.$slug.'.json';
            $entries[] = [
                'loc' => route('stektips.artikel', ['slug' => $slug]),
                'lastmod' => $this->lastmodFromFiles([$file]),
            ];
        }

        $stektipsLastmod = $this->lastmodFromFiles([base_path('data/stektips.json')]);
        foreach ($stektips->all() as $soort) {
            $entries[] = [
                'loc' => route('stektips.show', ['slug' => $soort['slug']]),
                'lastmod' => $stektipsLastmod,
            ];
        }

        $orgLastmod = $this->lastmodFromFiles([base_path('data/organisaties.json')]);
        foreach ($organisaties->all() as $org) {
            $entries[] = [
                'loc' => route('organisaties.show', ['slug' => $org['slug']]),
                'lastmod' => $orgLastmod,
            ];
        }

        $stedenLastmod = $this->lastmodFromFiles([base_path('data/steden.json')]);
        foreach ($steden->all() as $stad) {
            $entries[] = [
                'loc' => route('gratis-planten.show', ['stad' => $stad['slug']]),
                'lastmod' => $stedenLastmod,
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($entries as $entry) {
            $xml .= '  <url><loc>'.e($entry['loc']).'</loc>';
            if (! empty($entry['lastmod'])) {
                $xml .= '<lastmod>'.e($entry['lastmod']).'</lastmod>';
            }
            $xml .= '</url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $paths  Bestanden of directories; mtime van het nieuwste bestaande pad.
     */
    private function lastmodFromFiles(array $paths): ?string
    {
        $mtime = null;
        foreach ($paths as $path) {
            if ($path === '' || ! file_exists($path)) {
                continue;
            }
            if (is_dir($path)) {
                $files = glob(rtrim($path, '/').'/*') ?: [];
                foreach ($files as $file) {
                    if (! is_file($file)) {
                        continue;
                    }
                    $t = @filemtime($file);
                    if ($t !== false && ($mtime === null || $t > $mtime)) {
                        $mtime = $t;
                    }
                }
                continue;
            }
            $t = @filemtime($path);
            if ($t !== false && ($mtime === null || $t > $mtime)) {
                $mtime = $t;
            }
        }

        return $this->formatLastmod($mtime);
    }

    private function formatLastmod(?int $timestamp): ?string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }
}
