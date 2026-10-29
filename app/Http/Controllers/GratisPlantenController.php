<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StadAanbod;
use App\Services\Steden;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GratisPlantenController extends Controller
{
    public function index(Steden $steden): View
    {
        $site = config('site.name');
        $title = 'Gratis planten in jouw stad';
        $metaDescription = 'Overzicht van gratis planten per stad in Nederland — vind stekjes en planten bij jou in de buurt via '.$site.'.';

        return view('pages.gratis-planten-index', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => route('gratis-planten.index'),
            'byProvincie' => $steden->groupedByProvincie(),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => route('gratis-planten.index'),
                'description' => $metaDescription,
                'inLanguage' => 'nl-NL',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ],
        ]);
    }

    public function show(string $stad, Steden $steden, StadAanbod $stadAanbod): View|Response
    {
        $city = $steden->find($stad);
        if ($city === null) {
            abort(404);
        }

        $site = config('site.name');
        $title = 'Gratis planten in '.$city['naam'];
        $metaDescription = 'Gratis planten en stekjes in '.$city['naam'].' ('.$city['provincie'].'). Bekijk actueel aanbod binnen ~'.(int) $city['straal_km'].' km en deel zelf via '.$site.'.';
        $canonical = route('gratis-planten.show', ['stad' => $city['slug']]);
        $faq = $steden->faqFor($city);
        $aanbod = $stadAanbod->aanbodWithin($city);
        $deelplekken = $stadAanbod->deelplekkenWithin($city);
        $nearby = $steden->nearby($city);

        $faqEntities = array_map(static fn (array $item) => [
            '@type' => 'Question',
            'name' => $item['vraag'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['antwoord'],
            ],
        ], $faq);

        return view('pages.gratis-planten-stad', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => $canonical,
            'stad' => $city,
            'aanbod' => $aanbod,
            'deelplekken' => $deelplekken,
            'nearby' => $nearby,
            'faq' => $faq,
            'schema' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        'name' => $title,
                        'url' => $canonical,
                        'description' => $metaDescription,
                        'inLanguage' => 'nl-NL',
                        'isPartOf' => [
                            '@type' => 'WebSite',
                            'name' => $site,
                            'url' => url('/'),
                        ],
                    ],
                    [
                        '@type' => 'FAQPage',
                        'mainEntity' => $faqEntities,
                    ],
                ],
            ],
        ]);
    }
}
