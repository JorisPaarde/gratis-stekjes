<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\Deelplek;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PlaatsSlug;
use App\Support\PostalCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Vaste + automatische stadspagina’s op basis van publiek aanbod / deelplekken.
 *
 * Drempel (config `gratisplanten.stad_aanbod_min`, default 1): gemeente met
 * minstens zoveel actieve publieke aanbiedingen of zichtbare deelplekken krijgt
 * een geïndexeerde automatische pagina. Vaste steden uit steden.json blijven
 * altijd bereikbaar én geïndexeerd (sitemap, overzicht, interne links), ook
 * zonder aanbod of deelplek. Automatische steden onder de drempel: 301 naar
 * `/gratis-planten` en uit overzicht/sitemap; onbekende slugs → 404.
 */
final class StadCatalogus
{
    public const CACHE_KEY = 'stad-catalogus.v1';

    public function __construct(
        private readonly Steden $steden,
        private readonly Deelplekken $deelplekken,
    ) {}

    public function threshold(): int
    {
        return max(1, (int) config('gratisplanten.stad_aanbod_min', 1));
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   by_slug: array<string, array{
     *     naam: string,
     *     slug: string,
     *     provincie: string,
     *     lat: float,
     *     lng: float,
     *     straal_km: float,
     *     intro: string,
     *     country: string,
     *     bron: string,
     *     aanbod: int,
     *     deelplekken: int,
     *     totaal: int
     *   }>
     * }
     */
    public function catalog(): array
    {
        $ttl = max(60, (int) config('gratisplanten.stad_catalogus_ttl', 3600));

        /** @var array{by_slug: array<string, array<string, mixed>>} $cached */
        $cached = Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->build());

        return $cached;
    }

    /**
     * Alle gemeenten met aantallen (voor artisan-rapport).
     *
     * @return list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   country: string,
     *   bron: string,
     *   aanbod: int,
     *   deelplekken: int,
     *   totaal: int,
     *   boven_drempel: bool
     * }>
     */
    public function rapport(): array
    {
        $min = $this->threshold();
        $rows = [];
        foreach ($this->catalog()['by_slug'] as $row) {
            $totaal = (int) $row['totaal'];
            $rows[] = [
                'naam' => (string) $row['naam'],
                'slug' => (string) $row['slug'],
                'provincie' => (string) $row['provincie'],
                'country' => (string) $row['country'],
                'bron' => (string) $row['bron'],
                'aanbod' => (int) $row['aanbod'],
                'deelplekken' => (int) $row['deelplekken'],
                'totaal' => $totaal,
                'boven_drempel' => $totaal >= $min,
            ];
        }
        usort($rows, static fn ($a, $b) => strcasecmp($a['naam'], $b['naam']));

        return $rows;
    }

    /**
     * @return array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string,
     *   bron: string,
     *   aanbod: int,
     *   deelplekken: int,
     *   totaal: int
     * }|null
     */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        $slug = PlaatsSlug::applyAlias($slug);
        $row = $this->catalog()['by_slug'][$slug] ?? null;
        if ($row === null) {
            return null;
        }

        return $this->withFreshIntro($row);
    }

    public function isIndexable(array $stad): bool
    {
        // Vaste steden uit steden.json altijd indexeren (ook zonder aanbod).
        if (($stad['bron'] ?? 'vast') === 'vast') {
            return true;
        }

        return (int) ($stad['totaal'] ?? 0) >= $this->threshold();
    }

    /**
     * Steden voor sitemap: alle vaste + automatische boven de drempel.
     *
     * @return list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string,
     *   bron: string,
     *   aanbod: int,
     *   deelplekken: int,
     *   totaal: int
     * }>
     */
    public function indexable(): array
    {
        $out = [];
        foreach ($this->catalog()['by_slug'] as $row) {
            if ($this->isIndexable($row)) {
                $out[] = $this->withFreshIntro($row);
            }
        }
        usort($out, static fn ($a, $b) => strcasecmp($a['naam'], $b['naam']));

        return $out;
    }

    /**
     * Overzicht: alle vaste steden + automatische boven de drempel.
     *
     * @return array<string, array<string, list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string,
     *   bron: string,
     *   aanbod: int,
     *   deelplekken: int,
     *   totaal: int
     * }>>>
     */
    public function groupedByLandForOverview(): array
    {
        $landen = [
            'Nederland' => [],
            'België' => [],
        ];
        foreach ($this->catalog()['by_slug'] as $row) {
            $bron = (string) ($row['bron'] ?? 'vast');
            if ($bron === 'auto' && ! $this->isIndexable($row)) {
                continue;
            }
            $land = (($row['country'] ?? 'NL') === 'BE') ? 'België' : 'Nederland';
            $prov = (string) ($row['provincie'] !== '' ? $row['provincie'] : 'Overig');
            $landen[$land][$prov] ??= [];
            $landen[$land][$prov][] = $this->withFreshIntro($row);
        }
        foreach ($landen as $land => $provs) {
            foreach ($provs as $prov => $steden) {
                usort($landen[$land][$prov], static fn ($a, $b) => strcasecmp($a['naam'], $b['naam']));
            }
            ksort($landen[$land], SORT_NATURAL | SORT_FLAG_CASE);
            if ($landen[$land] === []) {
                unset($landen[$land]);
            }
        }

        return $landen;
    }

    /**
     * Nabije geïndexeerde steden (geen dunne pagina’s in interne links).
     *
     * @param  array{lat: float, lng: float, slug: string, country?: string}  $stad
     * @return list<array{naam: string, slug: string, provincie: string, country: string, afstand_km: float}>
     */
    public function nearby(array $stad, int $limit = 6): array
    {
        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $slug = (string) $stad['slug'];
        $country = (string) ($stad['country'] ?? 'NL');
        $rows = [];
        foreach ($this->indexable() as $other) {
            if ($other['slug'] === $slug) {
                continue;
            }
            if (($other['country'] ?? 'NL') !== $country) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $other['lat'], $other['lng']);
            $rows[] = [
                'naam' => $other['naam'],
                'slug' => $other['slug'],
                'provincie' => $other['provincie'],
                'country' => $other['country'],
                'afstand_km' => round($km, 1),
            ];
        }
        usort($rows, static fn ($a, $b) => $a['afstand_km'] <=> $b['afstand_km']);

        return array_slice($rows, 0, max(1, $limit));
    }

    public function pageTitle(array $stad): string
    {
        return $this->steden->pageTitle($stad);
    }

    /**
     * @param  array{naam: string, provincie?: string, country?: string, aanbod?: int, deelplekken?: int, totaal?: int, bron?: string}  $stad
     */
    public function introFor(array $stad): string
    {
        if (($stad['bron'] ?? 'vast') === 'vast' && isset($stad['intro']) && is_string($stad['intro']) && $stad['intro'] !== '') {
            // Vaste intros uit steden.json blijven onaangeroerd.
            return $stad['intro'];
        }

        return $this->autoIntro($stad);
    }

    /**
     * @param  array{naam: string, provincie?: string, country?: string, straal_km?: float, aanbod?: int, deelplekken?: int, totaal?: int}  $stad
     */
    public function metaDescription(array $stad): string
    {
        $site = (string) config('site.name');
        $naam = (string) $stad['naam'];
        $prov = trim((string) ($stad['provincie'] ?? ''));
        $straal = (int) round((float) ($stad['straal_km'] ?? config('gratisplanten.auto_stad_straal_km', 12)));
        $totaal = (int) ($stad['totaal'] ?? 0);
        $isBe = ($stad['country'] ?? 'NL') === 'BE';
        $waar = $prov !== '' ? "{$naam} ({$prov})" : $naam;

        if ($totaal <= 0) {
            return $isBe
                ? "Gratis planten, stekjes en zaden in {$waar}. Deel of haal via {$site}."
                : "Gratis planten en stekjes in {$waar}. Deel of haal via {$site}.";
        }
        if ($totaal === 1) {
            return $isBe
                ? "1 gratis plant, stekje of zadenplek in {$waar}. Bekijk de kaart of bied zelf iets aan via {$site}."
                : "1 gratis plant of stekje in {$waar}. Bekijk de kaart of bied zelf iets aan via {$site}.";
        }

        return $isBe
            ? "{$totaal} gratis planten, stekjes of zadenplekken in {$waar}, binnen ongeveer {$straal} km. Haal of deel via {$site}."
            : "{$totaal} gratis planten of stekjes in {$waar}, binnen ongeveer {$straal} km. Haal of deel via {$site}.";
    }

    /**
     * @param  array{naam: string, straal_km: float, country?: string, slug?: string, bron?: string}  $stad
     * @return list<array{vraag: string, antwoord: string}>
     */
    public function faqFor(array $stad): array
    {
        return $this->steden->faqFor($stad);
    }

    /**
     * @return array{by_slug: array<string, array<string, mixed>>}
     */
    private function build(): array
    {
        $bySlug = [];
        foreach ($this->steden->all() as $stad) {
            $slug = (string) $stad['slug'];
            $bySlug[$slug] = [
                'naam' => (string) $stad['naam'],
                'slug' => $slug,
                'provincie' => (string) $stad['provincie'],
                'lat' => (float) $stad['lat'],
                'lng' => (float) $stad['lng'],
                'straal_km' => (float) $stad['straal_km'],
                'intro' => (string) $stad['intro'],
                'country' => (string) ($stad['country'] ?? 'NL'),
                'bron' => 'vast',
                'aanbod' => 0,
                'deelplekken' => 0,
                'totaal' => 0,
                '_lat_sum' => 0.0,
                '_lng_sum' => 0.0,
                '_coord_n' => 0,
            ];
        }

        $this->addDeelplekken($bySlug);
        $this->addAanbod($bySlug);

        foreach ($bySlug as $slug => $row) {
            $bySlug[$slug]['totaal'] = (int) $row['aanbod'] + (int) $row['deelplekken'];
            if (($row['bron'] ?? '') === 'auto') {
                $n = (int) ($row['_coord_n'] ?? 0);
                if ($n > 0) {
                    $bySlug[$slug]['lat'] = Geo::roundCoord(((float) $row['_lat_sum']) / $n);
                    $bySlug[$slug]['lng'] = Geo::roundCoord(((float) $row['_lng_sum']) / $n);
                }
                $lat = (float) $bySlug[$slug]['lat'];
                $lng = (float) $bySlug[$slug]['lng'];
                // Auto zonder bruikbare coördinaten (buiten NL+BE-bbox) → weglaten.
                if ($n < 1 || $lat < 49.4 || $lat > 53.7 || $lng < 2.4 || $lng > 7.4) {
                    unset($bySlug[$slug]);

                    continue;
                }
                if ($bySlug[$slug]['provincie'] === '') {
                    $bySlug[$slug]['provincie'] = $this->guessProvincie(
                        $lat,
                        $lng,
                        (string) $bySlug[$slug]['country']
                    );
                }
                $bySlug[$slug]['intro'] = '';
            }
            unset($bySlug[$slug]['_lat_sum'], $bySlug[$slug]['_lng_sum'], $bySlug[$slug]['_coord_n']);
        }

        return ['by_slug' => $bySlug];
    }

    /**
     * @param  array<string, array<string, mixed>>  $bySlug
     */
    private function addDeelplekken(array &$bySlug): void
    {
        foreach ($this->visibleDeelplekRows() as $plek) {
            $plaats = trim((string) ($plek['plaats'] ?? ''));
            $slug = PlaatsSlug::from($plaats);
            if ($slug === '') {
                continue;
            }
            $lat = isset($plek['lat']) && is_numeric($plek['lat']) ? (float) $plek['lat'] : null;
            $lng = isset($plek['lng']) && is_numeric($plek['lng']) ? (float) $plek['lng'] : null;
            $country = Country::normalize((string) ($plek['country'] ?? 'NL'));
            $provincie = trim((string) ($plek['provincie'] ?? ''));
            $this->bump($bySlug, $slug, [
                'naam' => $plaats,
                'provincie' => $provincie,
                'country' => $country,
                'lat' => $lat,
                'lng' => $lng,
                'deelplekken' => 1,
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $bySlug
     */
    private function addAanbod(array &$bySlug): void
    {
        try {
            if (! Schema::hasTable('aanbod') || ! Schema::hasTable('users')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $rows = Aanbod::query()
            ->publiclyListed()
            ->whereHas('user', function ($q) {
                $q->members()
                    ->where('active', true)
                    ->whereNotNull('email_verified_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lon');
            })
            ->with(['user' => fn ($q) => $q->select([
                'id', 'country', 'woonplaats', 'locatie_label', 'lat', 'lon', 'active', 'email_verified_at', 'is_stats_viewer',
            ])])
            ->get(['id', 'user_id']);

        foreach ($rows as $aanbod) {
            $user = $aanbod->user;
            if ($user === null) {
                continue;
            }
            $plaats = $this->plaatsVanUser($user);
            $slug = PlaatsSlug::from($plaats);
            if ($slug === '') {
                continue;
            }
            $lat = $user->lat !== null ? (float) $user->lat : null;
            $lng = $user->lon !== null ? (float) $user->lon : null;
            $this->bump($bySlug, $slug, [
                'naam' => $plaats,
                'provincie' => '',
                'country' => Country::normalize((string) ($user->country ?? Country::NL)),
                'lat' => $lat,
                'lng' => $lng,
                'aanbod' => 1,
            ]);
        }
    }

    /**
     * @param  object{woonplaats?: ?string, locatie_label?: ?string}  $user
     */
    private function plaatsVanUser(object $user): string
    {
        $woonplaats = trim((string) ($user->woonplaats ?? ''));
        if ($woonplaats !== '') {
            return $woonplaats;
        }
        $label = trim((string) ($user->locatie_label ?? ''));
        if ($label === '') {
            return '';
        }
        // «1326 Almere» / «2037 Haarlem»
        if (preg_match('/^\d{4}\s+(.+)$/u', $label, $m)) {
            return trim($m[1]);
        }

        return PostalCode::plaatsFromGeoLabel($label);
    }

    /**
     * @param  array<string, array<string, mixed>>  $bySlug
     * @param  array{
     *   naam: string,
     *   provincie: string,
     *   country: string,
     *   lat: ?float,
     *   lng: ?float,
     *   aanbod?: int,
     *   deelplekken?: int
     * }  $hit
     */
    private function bump(array &$bySlug, string $slug, array $hit): void
    {
        $straal = (float) config('gratisplanten.auto_stad_straal_km', 12);
        if (! isset($bySlug[$slug])) {
            $bySlug[$slug] = [
                'naam' => $hit['naam'],
                'slug' => $slug,
                'provincie' => $hit['provincie'],
                'lat' => 0.0,
                'lng' => 0.0,
                'straal_km' => $straal,
                'intro' => '',
                'country' => $hit['country'],
                'bron' => 'auto',
                'aanbod' => 0,
                'deelplekken' => 0,
                'totaal' => 0,
                '_lat_sum' => 0.0,
                '_lng_sum' => 0.0,
                '_coord_n' => 0,
            ];
        }

        $bySlug[$slug]['aanbod'] = (int) $bySlug[$slug]['aanbod'] + (int) ($hit['aanbod'] ?? 0);
        $bySlug[$slug]['deelplekken'] = (int) $bySlug[$slug]['deelplekken'] + (int) ($hit['deelplekken'] ?? 0);

        if (($bySlug[$slug]['bron'] ?? '') === 'auto') {
            if ($bySlug[$slug]['provincie'] === '' && $hit['provincie'] !== '') {
                $bySlug[$slug]['provincie'] = $hit['provincie'];
            }
            // Behoud nettere casing van de eerste niet-lege naam.
            if ($hit['naam'] !== '' && ($bySlug[$slug]['naam'] === '' || $bySlug[$slug]['naam'] === $slug)) {
                $bySlug[$slug]['naam'] = $hit['naam'];
            }
            if (($hit['country'] ?? '') === 'BE') {
                $bySlug[$slug]['country'] = 'BE';
            }
        }

        if ($hit['lat'] !== null && $hit['lng'] !== null) {
            $bySlug[$slug]['_lat_sum'] = (float) $bySlug[$slug]['_lat_sum'] + $hit['lat'];
            $bySlug[$slug]['_lng_sum'] = (float) $bySlug[$slug]['_lng_sum'] + $hit['lng'];
            $bySlug[$slug]['_coord_n'] = (int) $bySlug[$slug]['_coord_n'] + 1;
        }
    }

    /**
     * @return list<array{plaats: string, provincie: string, country: string, lat: float|int|string|null, lng: float|int|string|null, naam?: string}>
     */
    private function visibleDeelplekRows(): array
    {
        $out = [];
        $seen = [];

        try {
            $data = $this->deelplekken->loadJson();
            foreach ($data['plekken'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (array_key_exists('visible', $row)) {
                    $visible = filter_var($row['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($visible === null) {
                        $visible = (bool) $row['visible'];
                    }
                    if (! $visible) {
                        continue;
                    }
                }
                $key = mb_strtolower(trim((string) ($row['naam'] ?? '')).'|'.trim((string) ($row['plaats'] ?? '')), 'UTF-8');
                if ($key === '|' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'naam' => (string) ($row['naam'] ?? ''),
                    'plaats' => (string) ($row['plaats'] ?? ''),
                    'provincie' => (string) ($row['provincie'] ?? ''),
                    'country' => (string) ($row['country'] ?? 'NL'),
                    'lat' => $row['lat'] ?? null,
                    'lng' => $row['lng'] ?? null,
                ];
            }
        } catch (Throwable) {
            // JSON ontbreekt in sommige tests → alleen DB.
        }

        try {
            if (class_exists(Deelplek::class) && Schema::hasTable('deelplekken')) {
                foreach (Deelplek::query()->visible()->get() as $row) {
                    $key = mb_strtolower(trim((string) $row->naam).'|'.trim((string) $row->plaats), 'UTF-8');
                    if ($key === '|' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $out[] = [
                        'naam' => (string) $row->naam,
                        'plaats' => (string) $row->plaats,
                        'provincie' => (string) $row->provincie,
                        'country' => (string) ($row->country ?? 'NL'),
                        'lat' => $row->lat,
                        'lng' => $row->lng,
                    ];
                }
            }
        } catch (Throwable) {
            // Tabel/model niet bruikbaar.
        }

        return $out;
    }

    private function guessProvincie(float $lat, float $lng, string $country): string
    {
        if ($lat === 0.0 && $lng === 0.0) {
            return '';
        }
        $best = '';
        $bestKm = PHP_FLOAT_MAX;
        foreach ($this->steden->all() as $stad) {
            if (($stad['country'] ?? 'NL') !== $country) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $stad['lat'], $stad['lng']);
            if ($km < $bestKm) {
                $bestKm = $km;
                $best = (string) $stad['provincie'];
            }
        }

        // Alleen overnemen als de dichtstbijzijnde vaste stad redelijk dichtbij is.
        return $bestKm <= 80 ? $best : '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string,
     *   bron: string,
     *   aanbod: int,
     *   deelplekken: int,
     *   totaal: int
     * }
     */
    private function withFreshIntro(array $row): array
    {
        $row['intro'] = $this->introFor($row);

        return $row;
    }

    /**
     * @param  array{naam: string, provincie?: string, country?: string, aanbod?: int, deelplekken?: int, totaal?: int, straal_km?: float}  $stad
     */
    private function autoIntro(array $stad): string
    {
        $naam = (string) $stad['naam'];
        $prov = trim((string) ($stad['provincie'] ?? ''));
        $totaal = (int) ($stad['totaal'] ?? 0);
        $aanbod = (int) ($stad['aanbod'] ?? 0);
        $deelplekken = (int) ($stad['deelplekken'] ?? 0);
        $straal = (int) round((float) ($stad['straal_km'] ?? config('gratisplanten.auto_stad_straal_km', 12)));
        $waar = $prov !== '' ? "{$naam} ({$prov})" : $naam;

        if ($totaal <= 0) {
            return "In {$waar} kun je gratis planten en stekjes delen via deze pagina. Op de kaart zie je de omgeving binnen ongeveer {$straal} km. Heb je zelf iets over? Zet het erbij.";
        }

        if ($totaal === 1) {
            if ($aanbod === 1 && $deelplekken === 0) {
                $eerste = "In {$waar} staat nu 1 gratis plant of stekje klaar om op te halen.";
            } elseif ($aanbod === 0 && $deelplekken === 1) {
                $eerste = "In {$waar} ken ik nu 1 vaste deelplek waar je planten of zaden kunt delen.";
            } else {
                $eerste = "In {$waar} vind je nu 1 gratis plant, stekje of deelplek.";
            }

            return $eerste.' Op de kaart zie je de plek bij benadering, binnen ongeveer '.$straal.' km. Zoek gerust verder in de omgeving of bied zelf iets aan.';
        }

        $eerste = "In {$waar} vind je nu {$totaal} gratis planten, stekjes of deelplekken.";
        if ($aanbod > 0 && $deelplekken > 0) {
            $eerste = "In {$waar} vind je nu {$aanbod} ".($aanbod === 1 ? 'actieve aanbieding' : 'actieve aanbiedingen')
                .' en '.$deelplekken.' '.($deelplekken === 1 ? 'vaste deelplek' : 'vaste deelplekken').'.';
        }

        return $eerste.' Locaties op de kaart zijn bij benadering, binnen ongeveer '.$straal.' km. Heb je zelf iets over? Zet het erbij.';
    }
}
