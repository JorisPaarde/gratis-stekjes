<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StadAanbod;
use App\Services\StadCatalogus;
use App\Support\RecentAanbod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GratisPlantenController extends Controller
{
    public function index(StadCatalogus $catalogus): View
    {
        $site = config('site.name');
        $title = 'Gratis planten ophalen in jouw stad';
        $metaDescription = 'Gratis planten ophalen en afhalen per stad in Nederland en België. Kies jouw stad en vind of deel stekjes via '.$site.'.';

        return view('pages.gratis-planten-index', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => route('gratis-planten.index'),
            'byLand' => $catalogus->groupedByLandForOverview(),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => route('gratis-planten.index'),
                'description' => $metaDescription,
                'inLanguage' => 'nl',
                'areaServed' => [
                    ['@type' => 'Country', 'name' => 'NL'],
                    ['@type' => 'Country', 'name' => 'BE'],
                ],
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ],
        ]);
    }

    public function show(string $stad, StadCatalogus $catalogus, StadAanbod $stadAanbod): View|Response|RedirectResponse
    {
        $city = $catalogus->find($stad);
        if ($city === null) {
            abort(404);
        }

        // Automatische stad onder de drempel: geen dunne pagina, wel nette redirect.
        if (($city['bron'] ?? 'vast') === 'auto' && ! $catalogus->isIndexable($city)) {
            return redirect()->route('gratis-planten.index', status: 301);
        }

        $site = config('site.name');
        $title = $catalogus->pageTitle($city);
        $isBe = ($city['country'] ?? 'NL') === 'BE';
        $metaDescription = $catalogus->metaDescription($city);
        $canonical = route('gratis-planten.show', ['stad' => $city['slug']]);
        $faq = $catalogus->faqFor($city);
        $aanbod = $stadAanbod->aanbodWithin($city);
        // Lijst/kop alleen bij genoeg actief aanbod mét foto in de straal; CTAs + kaart blijven.
        $toonAanbod = RecentAanbod::shouldShow($stadAanbod->countWithFotoWithin($city));
        $deelplekken = $stadAanbod->deelplekkenWithin($city);
        // Alleen plantenasiels mét plaatsnaam die bij deze stad hoort (geen
        // buurgemeente binnen de straal — anders verzinnen we een asiel "in" de stad).
        $stadNaamNorm = mb_strtolower((string) $city['naam'], 'UTF-8');
        $plantenasiels = array_values(array_filter(
            $deelplekken,
            static function (array $p) use ($stadNaamNorm): bool {
                if (($p['type'] ?? '') !== 'plantenasiel') {
                    return false;
                }
                $plaats = mb_strtolower(trim((string) ($p['plaats'] ?? '')), 'UTF-8');

                return $plaats !== '' && $plaats === $stadNaamNorm;
            }
        ));
        $nearby = $catalogus->nearby($city);
        $indexable = $catalogus->isIndexable($city);
        $inLanguage = $isBe ? 'nl-BE' : 'nl';
        $areaServed = $isBe
            ? [['@type' => 'Country', 'name' => 'BE']]
            : [['@type' => 'Country', 'name' => 'NL']];

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
            'robots' => $indexable ? null : 'noindex, follow',
            'stad' => $city,
            'aanbod' => $aanbod,
            'toonAanbod' => $toonAanbod,
            'deelplekken' => $deelplekken,
            'plantenasiels' => $plantenasiels,
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
                        'inLanguage' => $inLanguage,
                        'areaServed' => $areaServed,
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
