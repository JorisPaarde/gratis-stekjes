<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        $site = config('site.name');

        return view('pages.home', [
            'title' => $site,
            'metaDescription' => config('site.description'),
            'canonical' => url('/'),
            'nuTeStekken' => $this->nuTeStekkenBlock(),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $site,
                'url' => url('/'),
                'description' => config('site.description'),
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

    public function robots(): Response
    {
        if (config('site.noindex') || app()->environment('staging')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\nAllow: /\nSitemap: ".url('/sitemap.xml')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return array{maand_naam: string, intro: string, groepen: list<array<string, mixed>>}|null */
    private function nuTeStekkenBlock(): ?array
    {
        $path = base_path('data/nu-te-stekken.json');
        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return null;
        }

        $when = Carbon::now('Europe/Amsterdam');
        $month = (int) $when->month;
        $block = $data['maanden'][(string) $month] ?? $data['maanden'][$month] ?? null;
        if (! is_array($block)) {
            return null;
        }

        $namen = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];

        return [
            'maand_naam' => $namen[$month] ?? (string) $month,
            'intro' => (string) ($block['intro'] ?? 'Planten die in deze maand gangbaar te vermeerderen zijn.'),
            'groepen' => is_array($block['groepen'] ?? null) ? $block['groepen'] : [],
        ];
    }
}
