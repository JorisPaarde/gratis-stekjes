<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NuTeStekken;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(NuTeStekken $calendar): View
    {
        $site = config('site.name');

        return view('pages.home', [
            'title' => $site,
            'metaDescription' => config('site.description'),
            'canonical' => url('/'),
            'nuTeStekken' => $calendar->blockForViews(),
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
}
