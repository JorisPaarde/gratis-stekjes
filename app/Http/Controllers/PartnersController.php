<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class PartnersController extends Controller
{
    public function __invoke(): View
    {
        $site = config('site.name');

        return view('pages.partners', [
            'title' => 'Stekjesmarkt, plantenruilbeurs en ruilinitiatieven',
            'heading' => 'Plantenruilbeurzen, stekjesmarkten en initiatieven',
            'metaDescription' => 'Stekjesmarkt, plantenruilbeurs en andere ruilinitiatieven — plekken naast '.$site.' waar planten van hand tot hand gaan.',
            'canonical' => route('partners'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Stekjesmarkt, plantenruilbeurs en ruilinitiatieven',
                'url' => route('partners'),
                'description' => 'Stekjesmarkt, plantenruilbeurs en andere ruilinitiatieven naast '.$site.'.',
                'inLanguage' => 'nl-NL',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ],
        ]);
    }
}
