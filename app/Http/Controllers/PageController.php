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
            'canonical' => url('/'),
            'nuTeStekken' => $calendar->blockForViews(),
            'ervaringenBlok' => $ervaringenBlok,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
                'description' => $metaDescription,
                'inLanguage' => 'nl-NL',
            ],
        ]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', [
            'title' => 'Privacy',
            'metaDescription' => 'Privacyverklaring van '.config('site.name').'.',
            'canonical' => url('/privacy'),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'title' => 'Contact',
            'metaDescription' => 'Contact met '.config('site.name').'.',
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

    public function sitemap(Stektips $stektips, Steden $steden, Organisaties $organisaties, Kennisbank $kennisbank): Response
    {
        $urls = [
            url('/'),
            route('hoe-werkt-het'),
            route('aanbod'),
            route('stektips.index'),
            route('hulpmiddelen'),
            route('over'),
            route('privacy'),
            route('contact'),
            route('partners'),

            route('deelplekken'),
            route('voor-organisaties'),
            route('voor-organisaties.aanmelden'),
            route('organisaties.index'),
            route('invasieve-soorten'),
            route('gratis-planten.index'),
            route('volkstuinen'),
            route('help-mee'),
            route('natuur'),
            route('verbinding'),
        ];

        // /ervaringen alleen in sitemap als er goedgekeurde ervaringen zijn (anders noindex/leeg).
        if (Schema::hasTable('ervaringen') && Ervaring::query()->approved()->exists()) {
            $urls[] = route('ervaringen.index');
        }
        foreach ($kennisbank->all() as $artikel) {
            $urls[] = route('stektips.artikel', ['slug' => $artikel['slug']]);
        }
        foreach ($stektips->all() as $soort) {
            $urls[] = route('stektips.show', ['slug' => $soort['slug']]);
        }
        foreach ($organisaties->all() as $org) {
            $urls[] = route('organisaties.show', ['slug' => $org['slug']]);
        }
        foreach ($steden->all() as $stad) {
            $urls[] = route('gratis-planten.show', ['stad' => $stad['slug']]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $loc) {
            $xml .= '  <url><loc>'.e($loc).'</loc></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
