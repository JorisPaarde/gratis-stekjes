<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Kennisbank;
use App\Services\SoortAfbeeldingen;
use App\Services\Stektips;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StektipsController extends Controller
{
    public function index(Request $request, Stektips $stektips, Kennisbank $kennisbank, SoortAfbeeldingen $afbeeldingen): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categorie' => trim((string) $request->query('categorie', '')),
            'seizoen' => trim((string) $request->query('seizoen', '')),
            'maand' => trim((string) $request->query('maand', '')),
            'locatie' => trim((string) $request->query('locatie', '')),
            'soort' => trim((string) $request->query('soort', '')),
        ];

        $artikelen = $kennisbank->filter([
            'q' => $filters['q'],
            'categorie' => $filters['categorie'],
            'seizoen' => $filters['seizoen'],
            'maand' => $filters['maand'],
            'locatie' => $filters['locatie'],
            'soort' => $filters['soort'],
        ]);

        $soorten = $this->filterSoorten($stektips->all(), $filters);

        $queryForCanonical = array_filter($filters, static fn ($v) => $v !== '' && $v !== null);

        $categoryLabels = $kennisbank->categoryLabels();
        $entries = [];
        foreach ($artikelen as $artikel) {
            $entries[] = [
                'type' => 'artikel',
                'titel' => (string) $artikel['titel'],
                'url' => route('stektips.artikel', ['slug' => $artikel['slug']]),
                'meta' => (string) ($artikel['samenvatting'] ?? ''),
                'badges' => array_map(
                    static fn (string $slug) => $categoryLabels[$slug] ?? $slug,
                    array_values(array_filter(
                        $artikel['categorieen'] ?? [],
                        static fn ($c) => is_string($c) && $c !== ''
                    ))
                ),
            ];
        }
        foreach ($soorten as $soort) {
            $slug = (string) $soort['slug'];
            $entries[] = [
                'type' => 'soort',
                'titel' => (string) $soort['naam'],
                'url' => route('stektips.show', ['slug' => $slug]),
                'meta' => trim(
                    ((string) ($soort['wanneer'] ?? '')).
                    ' · '.
                    ((string) ($soort['kans'] ?? '')).
                    (! empty($soort['categorie']) ? ' · '.$soort['categorie'] : '')
                ),
                'badges' => ['Soortpagina'],
                'afbeelding' => $afbeeldingen->forSlug($slug, (string) $soort['naam']),
            ];
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => strcmp($a['titel'], $b['titel'])
        );

        $hasActiveFilters = $queryForCanonical !== [];
        $pillars = $hasActiveFilters ? [] : $kennisbank->pillars();

        return view('pages.stektips-index', [
            'title' => 'Stektips & kennisbank',
            'metaDescription' => 'Kennisbank over stekken, snoeien, natuurlijk bewortelen en permacultuur — plus 48 soortpagina’s. Filter en zoek zonder JavaScript.',
            'canonical' => route('stektips.index', $queryForCanonical),
            'entries' => $entries,
            'pillars' => $pillars,
            'artikelenCount' => count($artikelen),
            'soortenCount' => count($soorten),
            'totalCount' => count($entries),
            'filters' => $filters,
            'categorieen' => $kennisbank->categories(),
            'alleSoorten' => $stektips->all(),
            'seizoenen' => [
                'lente' => 'Lente',
                'zomer' => 'Zomer',
                'herfst' => 'Herfst',
                'winter' => 'Winter',
            ],
            'maanden' => [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December',
            ],
            'locaties' => [
                'binnen' => 'Binnen',
                'buiten' => 'Buiten',
                'beide' => 'Binnen & buiten',
            ],
            'hasActiveFilters' => $hasActiveFilters,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Stektips & kennisbank',
                'url' => route('stektips.index'),
                'description' => 'Kennisbank over stekken, snoeien en 48 soortpagina’s.',
                'inLanguage' => 'nl-NL',
            ],
        ]);
    }

    public function artikel(string $slug, Kennisbank $kennisbank, Stektips $stektips): View|Response
    {
        $artikel = $kennisbank->find($slug);
        if ($artikel === null) {
            abort(404);
        }

        $url = route('stektips.artikel', ['slug' => $slug]);
        $gerelateerdeSoorten = [];
        foreach ($artikel['gerelateerde_soorten'] ?? [] as $soortSlug) {
            if (! is_string($soortSlug)) {
                continue;
            }
            $found = $stektips->find($soortSlug);
            if ($found !== null) {
                $gerelateerdeSoorten[] = $found;
            }
        }

        $duurzamer = null;
        $altSlug = $artikel['duurzamer_alternatief'] ?? null;
        if (is_string($altSlug) && $altSlug !== '') {
            $duurzamer = $kennisbank->find($altSlug);
        }

        $categoryLabels = $kennisbank->categoryLabels();
        $meta = (string) ($artikel['meta_description'] ?? $artikel['samenvatting'] ?? $artikel['titel']);
        $pillarSlug = (string) ($artikel['pillar'] ?? ($artikel['categorieen'][0] ?? ''));
        $breadcrumbs = [
            ['naam' => 'Home', 'url' => route('home')],
            ['naam' => 'Stektips', 'url' => route('stektips.index')],
        ];
        if ($pillarSlug !== '' && empty($artikel['is_pillar']) && isset($categoryLabels[$pillarSlug])) {
            $hub = collect($kennisbank->pillars())->first(
                static fn (array $p): bool => ($p['pillar'] ?? '') === $pillarSlug || ($p['slug'] ?? '') === $pillarSlug
            );
            if (is_array($hub)) {
                $breadcrumbs[] = [
                    'naam' => $categoryLabels[$pillarSlug],
                    'url' => route('stektips.artikel', ['slug' => $hub['slug']]),
                ];
            }
        }
        $breadcrumbs[] = ['naam' => (string) $artikel['titel'], 'url' => $url];

        return view('pages.stektips-artikel', [
            'title' => (string) $artikel['titel'],
            'metaDescription' => $meta,
            'canonical' => $url,
            'artikel' => $artikel,
            'categoryLabels' => $categoryLabels,
            'gerelateerdeSoorten' => $gerelateerdeSoorten,
            'gerelateerdeArtikelen' => $kennisbank->relatedArticles($artikel),
            'kinderen' => $kennisbank->childrenOf($artikel),
            'duurzamer' => $duurzamer,
            'seoCtas' => $this->seoCtasForArtikel($artikel, $kennisbank),
            'schema' => $kennisbank->articleSchema($artikel, $url),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function show(string $slug, Stektips $stektips, Kennisbank $kennisbank, SoortAfbeeldingen $afbeeldingen): View|Response
    {
        if ($slug === 'artikel') {
            abort(404);
        }

        $soort = $stektips->find($slug);
        if ($soort === null) {
            abort(404);
        }

        $naam = (string) ($soort['naam'] ?? $slug);
        $latijn = (string) ($soort['latijn'] ?? '');
        $kort = (string) ($soort['kort'] ?? '');
        $metaLatijn = ($latijn !== '' && $latijn !== 'Volgt binnenkort') ? " ({$latijn})" : '';
        $metaKort = $kort !== '' ? $kort.'. ' : '';
        $url = route('stektips.show', ['slug' => $slug]);
        $hub = $kennisbank->soortHub($slug);
        $aanbodPlant = is_array($hub) ? (string) ($hub['aanbod_plant'] ?? $naam) : $naam;
        $aanbodUrl = route('aanbod', ['plant' => $aanbodPlant]);

        return view('pages.stektips-show', [
            'title' => $naam.' stekken en gratis delen',
            'metaDescription' => $naam.$metaLatijn.' stekken en gratis delen. '.$metaKort.'Methode, wanneer, moeilijkheid, licht en water — zoek aanbod op '.config('site.name').'.',
            'canonical' => $url,
            'soort' => $soort,
            'afbeelding' => $afbeeldingen->forSlug($slug, $naam),
            'verwanten' => $stektips->related($soort),
            'gerelateerdeArtikelen' => $kennisbank->articlesForSoort($slug),
            'aanbodUrl' => $aanbodUrl,
            'seoCtas' => $this->seoCtasForSoort($slug, $naam, $kennisbank),
            'breadcrumbs' => [
                ['naam' => 'Home', 'url' => route('home')],
                ['naam' => 'Stektips', 'url' => route('stektips.index')],
                ['naam' => 'Stekken', 'url' => route('stektips.artikel', ['slug' => 'stekken'])],
                ['naam' => $naam, 'url' => $url],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $artikel
     * @return array<string, mixed>
     */
    private function seoCtasForArtikel(array $artikel, Kennisbank $kennisbank): array
    {
        $seo = $artikel['seo'] ?? [];
        if (! is_array($seo) || $seo === []) {
            return [];
        }

        $ctas = [];
        $plant = (string) ($seo['plant'] ?? '');
        $plantLabel = $plant !== '' ? ucfirst(str_replace('-', ' ', $plant)) : '';
        $ctas['plant_label'] = $plantLabel;

        $rol = (string) ($seo['rol'] ?? '');
        if ($rol === 'snoeien') {
            $stekType = (string) ($seo['stek_type'] ?? 'artikel');
            $stekSlug = (string) ($seo['stek_slug'] ?? '');
            if ($stekSlug !== '') {
                $ctas['stek_url'] = $stekType === 'soort'
                    ? route('stektips.show', ['slug' => $stekSlug])
                    : route('stektips.artikel', ['slug' => $stekSlug]);
                $ctas['stek_label'] = $plantLabel !== '' ? $plantLabel.' stekken' : 'Stekpagina';
            }
            $aanbodPlant = (string) ($seo['aanbod_plant'] ?? $plant);
            if ($aanbodPlant !== '') {
                $ctas['aanbod_plant'] = $aanbodPlant;
            }
        }

        if ($rol === 'stekken' || ! empty($seo['wilgenwater'])) {
            $ctas['wilgenwater_url'] = route('stektips.artikel', ['slug' => 'wilgenwater']);
            $aanbodPlant = (string) ($seo['aanbod_plant'] ?? $plant);
            if ($aanbodPlant !== '') {
                $ctas['aanbod_url'] = route('aanbod', ['plant' => $aanbodPlant]);
                $ctas['aanbod_plant'] = $aanbodPlant;
            }
        }

        return $ctas;
    }

    /**
     * @return array<string, mixed>
     */
    private function seoCtasForSoort(string $slug, string $naam, Kennisbank $kennisbank): array
    {
        $hub = $kennisbank->soortHub($slug);
        if ($hub === null) {
            return [];
        }

        $ctas = [
            'plant_label' => $naam,
            'wilgenwater_url' => route('stektips.artikel', ['slug' => 'wilgenwater']),
            'aanbod_url' => route('aanbod', ['plant' => (string) ($hub['aanbod_plant'] ?? $naam)]),
            'aanbod_plant' => (string) ($hub['aanbod_plant'] ?? $naam),
        ];
        $snoei = $hub['snoei_slug'] ?? null;
        if (is_string($snoei) && $snoei !== '' && $kennisbank->find($snoei) !== null) {
            $ctas['snoei_url'] = route('stektips.artikel', ['slug' => $snoei]);
            $ctas['snoei_label'] = $naam.' snoeien';
        }

        return $ctas;
    }

    /**
     * @param  list<array<string, mixed>>  $soorten
     * @param  array{q: string, categorie: string, seizoen: string, maand: string, locatie: string, soort: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filterSoorten(array $soorten, array $filters): array
    {
        // Soortpagina’s hebben geen kennisbank-categorie; bij artikelfilter op categorie tonen we ze niet.
        if ($filters['categorie'] !== '') {
            return [];
        }

        $q = mb_strtolower($filters['q'], 'UTF-8');
        $seizoen = $filters['seizoen'];
        $maand = (int) $filters['maand'];
        $locatie = $filters['locatie'];
        $soortSlug = $filters['soort'];

        $out = [];
        foreach ($soorten as $soort) {
            $slug = (string) ($soort['slug'] ?? '');
            if ($soortSlug !== '' && $slug !== $soortSlug) {
                continue;
            }

            $categorie = (string) ($soort['categorie'] ?? '');
            $soortLocatie = $categorie === 'kamerplant' ? 'binnen' : 'buiten';
            if ($locatie === 'binnen' && $soortLocatie !== 'binnen') {
                continue;
            }
            if ($locatie === 'buiten' && $soortLocatie !== 'buiten') {
                continue;
            }
            // locatie=beide: toon alle soorten

            if ($seizoen !== '' || ($maand >= 1 && $maand <= 12)) {
                if (! $this->soortMatchesSeason($soort, $seizoen, $maand)) {
                    continue;
                }
            }

            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [
                    (string) ($soort['naam'] ?? ''),
                    (string) ($soort['latijn'] ?? ''),
                    (string) ($soort['slug'] ?? ''),
                    (string) ($soort['kort'] ?? ''),
                    (string) ($soort['methode'] ?? ''),
                    (string) ($soort['wanneer'] ?? ''),
                    $categorie,
                ]), 'UTF-8');
                if (! str_contains($hay, $q)) {
                    continue;
                }
            }

            $out[] = $soort;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $soort
     */
    private function soortMatchesSeason(array $soort, string $seizoen, int $maand): bool
    {
        $wanneer = mb_strtolower((string) ($soort['wanneer'] ?? ''), 'UTF-8');
        if ($wanneer === '') {
            return false;
        }

        $map = [
            'lente' => ['lente', 'voorjaar', 'mrt', 'maa', 'apr', 'mei', 'mar', 'apr–', 'lent'],
            'zomer' => ['zomer', 'jun', 'jul', 'aug'],
            'herfst' => ['herfst', 'najaar', 'sep', 'okt', 'nov'],
            'winter' => ['winter', 'dec', 'jan', 'feb', 'nov–feb', 'nov-feb'],
        ];

        if ($seizoen !== '' && isset($map[$seizoen])) {
            $hit = false;
            foreach ($map[$seizoen] as $needle) {
                if (str_contains($wanneer, $needle)) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return false;
            }
        }

        if ($maand >= 1 && $maand <= 12) {
            $monthHints = [
                1 => ['jan', 'winter', 'nov–feb', 'nov-feb', 'dec–feb'],
                2 => ['feb', 'winter', 'nov–feb', 'nov-feb'],
                3 => ['mrt', 'maa', 'mar', 'lente', 'voorjaar', 'lente–zomer', 'lente-zomer'],
                4 => ['apr', 'lente', 'voorjaar', 'lente–zomer'],
                5 => ['mei', 'lente', 'voorjaar', 'lente–zomer'],
                6 => ['jun', 'zomer', 'lente–zomer', 'zomer–'],
                7 => ['jul', 'zomer'],
                8 => ['aug', 'zomer', 'aug–sep'],
                9 => ['sep', 'herfst', 'aug–sep', 'najaar'],
                10 => ['okt', 'herfst', 'najaar'],
                11 => ['nov', 'herfst', 'winter', 'nov–feb'],
                12 => ['dec', 'winter', 'nov–feb'],
            ];
            $hit = false;
            foreach ($monthHints[$maand] as $needle) {
                if (str_contains($wanneer, $needle)) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return false;
            }
        }

        return true;
    }
}
