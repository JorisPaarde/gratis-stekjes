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
            'title' => 'Initiatieven',
            'metaDescription' => 'Initiatieven waar '.$site.' naar linkt — plantenasiels, plantenbiebs, ruilkasten, voedselbossen en meer.',
            'canonical' => route('partners'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Initiatieven',
                'url' => route('partners'),
                'description' => 'Initiatieven waar '.$site.' naar linkt — plantenasiels, plantenbiebs, ruilkasten, voedselbossen en meer.',
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
