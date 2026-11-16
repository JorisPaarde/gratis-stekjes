<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Api\DeelplekkenController as ApiDeelplekkenController;
use App\Services\Deelplekken;
use App\Services\Evenementen;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\Faq;
use App\Support\Geo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class DeelplekkenController extends Controller
{
    public function __invoke(
        Request $request,
        Deelplekken $deelplekken,
        Evenementen $evenementen,
        LocationGeocoder $geocoder,
    ): View {
        $type = trim((string) $request->query('type', ''));
        $provincie = trim((string) $request->query('provincie', ''));
        $plaats = trim((string) $request->query('plaats', ''));

        $alleenUitdeeldagen = $type === ApiDeelplekkenController::FILTER_UITDEELDAGEN;
        if ($type !== '' && ! $alleenUitdeeldagen && ! $deelplekken->typeValid($type)) {
            $type = '';
        }

        if ($alleenUitdeeldagen) {
            $items = $this->uitdeeldagenVoorLijst($evenementen, $provincie, $plaats);
        } else {
            $items = $deelplekken->queryVisible([
                'type' => $type,
                'provincie' => $provincie,
                'plaats' => $plaats,
            ]);
        }

        // Met bekende plaats: dichtstbijzijnde eerst (PDOK/Nominatim); anders bestaande volgorde.
        if ($plaats !== '' && $items->isNotEmpty()) {
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
        $uitdeeldagenAantal = count($evenementen->listPublicForKaart(null, null));

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
            'evenementTypeLabels' => $evenementen->typeLabels(),
            'provincies' => $deelplekken->provincies(),
            'filters' => [
                'type' => $type,
                'provincie' => $provincie,
                'plaats' => $plaats,
            ],
            'items' => $items,
            'typeAantallen' => $typeAantallen,
            'uitdeeldagenAantal' => $uitdeeldagenAantal,
            'faq' => $faq,
            'schema' => $faq === [] ? ['@context' => 'https://schema.org'] + $collection : [
                '@context' => 'https://schema.org',
                '@graph' => [$collection, Faq::schema($canonical.'#veelgestelde-vragen', $faq)],
            ],
        ]);
    }

    /**
     * Lijstitems voor chip «Uitdeeldagen» (zelfde shape als deelplek-rijen in de Blade).
     *
     * @return Collection<int, object>
     */
    private function uitdeeldagenVoorLijst(Evenementen $evenementen, string $provincie, string $plaats): Collection
    {
        return collect($evenementen->listPublicForKaart(
            $provincie !== '' ? $provincie : null,
            $plaats !== '' ? $plaats : null,
        ))->map(function (array $row) {
            $geraadpleegd = trim((string) ($row['geraadpleegd'] ?? ''));

            return (object) [
                'naam' => (string) $row['naam'],
                'type' => (string) $row['type'],
                'plaats' => (string) ($row['plaats'] ?? ''),
                'provincie' => (string) ($row['provincie'] ?? ''),
                'adres' => (string) ($row['adres'] ?? ''),
                'lat' => $row['lat'] ?? null,
                'lng' => $row['lng'] ?? ($row['lon'] ?? null),
                'bron_url' => (string) ($row['bron_url'] ?? ''),
                'geraadpleegd' => $geraadpleegd !== '' ? Carbon::parse($geraadpleegd) : null,
                'tijdelijk' => true,
                'datum_label' => (string) ($row['datum_label'] ?? ''),
                'type_label' => (string) ($row['type_label'] ?? $row['type']),
            ];
        })->values();
    }
}
