<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Deelplekken;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\Faq;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DeelplekkenController extends Controller
{
    public function __invoke(Request $request, Deelplekken $deelplekken, LocationGeocoder $geocoder): View
    {
        $type = trim((string) $request->query('type', ''));
        $provincie = trim((string) $request->query('provincie', ''));
        $plaats = trim((string) $request->query('plaats', ''));

        if ($type !== '' && ! $deelplekken->typeValid($type)) {
            $type = '';
        }

        $items = $deelplekken->queryVisible([
            'type' => $type,
            'provincie' => $provincie,
            'plaats' => $plaats,
        ]);

        // Met bekende plaats: dichtstbijzijnde eerst (PDOK/Nominatim); anders bestaande volgorde.
        if ($plaats !== '') {
            try {
                $hit = $geocoder->resolve($plaats, Country::NL, true);
                if (! empty($hit['ok']) && isset($hit['lat'], $hit['lon'])) {
                    $clat = (float) $hit['lat'];
                    $clon = (float) $hit['lon'];
                    $items = $items->sortBy(function ($plek) use ($clat, $clon) {
                        return Geo::haversineKm($clat, $clon, (float) $plek->lat, (float) $plek->lng);
                    })->values();
                }
            } catch (Throwable) {
                // Behoud huidige volgorde bij geocode-fout.
            }
        }

        $faq = ($type === '' && $provincie === '' && $plaats === '') ? Faq::load('deelplekken-faq.json') : [];

        // Aantallen per type (zonder filters) voor de chips.
        $typeAantallen = [];
        foreach ($deelplekken->queryVisible([])->groupBy('type') as $t => $rows) {
            $typeAantallen[(string) $t] = $rows->count();
        }

        $site = config('site.name');
        $title = 'Plantenasiel, plantenbieb en ruilkastje in je buurt';
        $metaDescription = 'Vind een plantenasiel, plantenbieb, ruilkast of zadenbibliotheek bij jou in de buurt. Overzicht voor Nederland en België.';

        $canonical = route('deelplekken');

        $collection = [
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonical,
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
        ];

        return view('pages.deelplekken', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            // Filter-URL's (?plaats= / ?type= / ?provincie=) → canonical zonder query.
            'canonical' => $canonical,
            'typeLabels' => $deelplekken->typeLabels(),
            'provincies' => $deelplekken->provincies(),
            'filters' => [
                'type' => $type,
                'provincie' => $provincie,
                'plaats' => $plaats,
            ],
            'items' => $items,
            'typeAantallen' => $typeAantallen,
            'faq' => $faq,
            'schema' => $faq === [] ? ['@context' => 'https://schema.org'] + $collection : [
                '@context' => 'https://schema.org',
                '@graph' => [$collection, Faq::schema($canonical.'#veelgestelde-vragen', $faq)],
            ],
        ]);
    }
}
