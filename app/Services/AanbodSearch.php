<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\User;
use App\Models\Vereniging;
use App\Support\Country;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;

final class AanbodSearch
{
    public function __construct(
        private readonly LocationGeocoder $geocoder,
        private readonly Plantensoorten $plantensoorten,
    ) {}

    /**
     * @return array{
     *   meta: ?array{zoek_lat: float, zoek_lon: float, zoek_label: string, country?: string},
     *   items: list<array<string, mixed>>,
     *   error?: string
     * }
     */
    public function search(?string $query, ?string $typeFilter, bool $allowNetwork = true, ?string $plant = null): array
    {
        $rows = $this->baseQuery($typeFilter)->get();

        $plantFilter = $this->plantFilter((string) $plant);
        if ($plantFilter !== null) {
            $rows = $rows->filter(fn (Aanbod $a): bool => $this->matchtPlant($a, $plantFilter))->values();
        }

        $items = $rows->map(fn (Aanbod $a) => $this->toPublicItem($a))->all();

        $q = trim((string) $query);
        if ($q !== '') {
            $resolved = $this->geocoder->resolve($q, null, $allowNetwork);
            if (! ($resolved['ok'] ?? false)) {
                // Onvindbaar → oude gedrag (on-gesorteerde lijst).
                return ['meta' => null, 'items' => $items];
            }

            $items = $this->sortByDistance($items, $resolved['lat'], $resolved['lon']);

            return [
                'meta' => [
                    'zoek_lat' => $resolved['lat'],
                    'zoek_lon' => $resolved['lon'],
                    'zoek_label' => $resolved['label'],
                    'country' => $resolved['country'] ?? Country::NL,
                ],
                'items' => $items,
            ];
        }

        return ['meta' => null, 'items' => $items];
    }

    /**
     * Of er publiek aanbod is waarvan de tekst deze term bevat (ook zonder catalogusnaam,
     * bijvoorbeeld «vetplant»). Voor het herkennen van een plant in het hero-zoekveld.
     */
    public function heeftAanbodMet(string $term): bool
    {
        $filter = $this->plantFilter($term);
        if ($filter === null) {
            return false;
        }
        $needle = mb_strtolower(trim($term), 'UTF-8');
        if (preg_match('/[%_\\\\]/', $needle) === 1) {
            return false; // LIKE-jokertekens: geen plantnaam
        }
        $like = '%'.$needle.'%';

        // Voorselectie in SQL op de term en de namen van de soort; daarna dezelfde matching
        // als het zoeken zelf. Beperkt tot een handvol rijen.
        $kandidaten = $this->eligibleQuery(null)
            ->where(function ($q) use ($like, $filter) {
                $q->whereRaw('LOWER(tekst) LIKE ?', [$like]);
                foreach ($filter['needles'] as $naam) {
                    $q->orWhereRaw('LOWER(tekst) LIKE ?', ['%'.$naam.'%']);
                }
                if ($filter['id'] !== null) {
                    $q->orWhere('plantensoort_id', $filter['id']);
                }
            })
            ->limit(25)
            ->get();

        foreach ($kandidaten as $aanbod) {
            if ($this->matchtPlant($aanbod, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zoekfilter voor een plantterm: de term zelf plus, bij een bekende soort, alle namen
     * (volksnaam, synoniem, Latijn) en het soort-id van de catalogus.
     *
     * @return array{id: ?int, needles: list<string>, raw: string}|null
     */
    private function plantFilter(string $plant): ?array
    {
        $raw = mb_strtolower(trim($plant), 'UTF-8');
        if ($raw === '') {
            return null;
        }

        $needles = [];
        $norm = $this->plantensoorten->normalize($raw);
        if ($norm !== '') {
            $needles[$norm] = true;
        }
        $soort = $this->plantensoorten->zoeknamen($raw);
        foreach ($soort['namen'] ?? [] as $naam) {
            $needles[$naam] = true;
        }

        return ['id' => $soort['id'] ?? null, 'needles' => array_keys($needles), 'raw' => $raw];
    }

    /**
     * @param  array{id: ?int, needles: list<string>, raw: string}  $filter
     */
    private function matchtPlant(Aanbod $aanbod, array $filter): bool
    {
        if ($filter['id'] !== null && (int) $aanbod->plantensoort_id === $filter['id']) {
            return true;
        }

        $tekst = (string) $aanbod->tekst;
        if (str_contains(mb_strtolower($tekst, 'UTF-8'), $filter['raw'])) {
            return true;
        }

        $tekstNorm = $this->plantensoorten->normalize($tekst);
        foreach ($filter['needles'] as $needle) {
            if (str_contains($tekstNorm, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alleen op plant zoeken (1e veld leeg). Gesorteerd op afstand vanaf $origin
     * (opgeslagen coördinaten van het ingelogde lid); zonder origin nieuwste eerst.
     * De origin zelf komt nooit in de items; alleen afstand_km.
     *
     * @param  array{lat: float, lon: float}|null  $origin
     * @return list<array<string, mixed>>
     */
    public function zoekOpPlant(string $plant, ?string $typeFilter, ?array $origin): array
    {
        $items = $this->search(null, $typeFilter, false, $plant)['items'];
        if ($origin === null) {
            return $items;
        }

        return $this->sortByDistance($items, $origin['lat'], $origin['lon']);
    }

    /**
     * Opgeslagen coördinaten van het ingelogde lid, of null (gast / geen adres).
     *
     * @return array{lat: float, lon: float}|null
     */
    public static function accountOrigin(?User $user): ?array
    {
        if ($user === null || $user->lat === null || $user->lon === null) {
            return null;
        }

        return ['lat' => (float) $user->lat, 'lon' => (float) $user->lon];
    }

    /**
     * Waarom er (niet) op afstand gesorteerd wordt bij alleen-plant-zoeken.
     *
     * @return array{sortering: 'afstand'|'recent', reden: ?string}
     */
    public static function plantSortering(?User $user): array
    {
        if ($user === null) {
            return ['sortering' => 'recent', 'reden' => 'niet_ingelogd'];
        }
        if (self::accountOrigin($user) === null) {
            return ['sortering' => 'recent', 'reden' => 'geen_adres'];
        }

        return ['sortering' => 'afstand', 'reden' => null];
    }

    /**
     * Nieuwste publieke aanbiedingen, zonder locatiezoekopdracht (geen geocoding).
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit, ?string $typeFilter = null): array
    {
        $limit = max(1, min($limit, 50));

        return $this->baseQuery($typeFilter)
            ->limit($limit)
            ->get()
            ->map(fn (Aanbod $a) => $this->toPublicItem($a))
            ->all();
    }

    /**
     * Nieuwste publieke aanbiedingen mét foto (voor homepage-teaserkaartjes).
     *
     * @return list<array<string, mixed>>
     */
    public function recentWithFoto(int $limit, ?string $typeFilter = null): array
    {
        $limit = max(1, min($limit, 50));

        return $this->baseQuery($typeFilter)
            ->whereHas('fotos')
            ->limit($limit)
            ->get()
            ->map(fn (Aanbod $a) => $this->toPublicItem($a))
            ->all();
    }

    /**
     * Aantal actieve publieke aanbiedingen mét minstens één foto
     * (zelfde filters als {@see recent()}, plus foto). Voor teaser-drempel.
     */
    public function countPublicWithFoto(?string $typeFilter = null): int
    {
        return $this->eligibleQuery($typeFilter)->whereHas('fotos')->count();
    }

    /** @return Builder<Aanbod> */
    private function baseQuery(?string $typeFilter): Builder
    {
        $with = [
            'user' => fn ($q) => $q->where('active', true)->whereNotNull('email_verified_at'),
            'fotos',
        ];
        if (VerenigingService::beschikbaar()) {
            $with[] = 'user.zichtbareVerenigingen';
        }

        return $this->eligibleQuery($typeFilter)
            ->with($with)
            ->latest('created_at')
            ->orderByDesc('id');
    }

    /**
     * Zelfde scope als zoeken/recent: zichtbaar, niet verlopen, actief lid met coördinaten.
     *
     * @return Builder<Aanbod>
     */
    private function eligibleQuery(?string $typeFilter): Builder
    {
        return Aanbod::query()
            ->publiclyListed()
            ->whereHas('user', function ($q) {
                $q->members()
                    ->where('active', true)
                    ->whereNotNull('email_verified_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lon');
            })
            ->when(
                in_array($typeFilter, ['uitdelen', 'ophalen'], true),
                fn ($q) => $q->where('type', $typeFilter)
            );
    }

    /** @return array<string, mixed> */
    public function toPublicItem(Aanbod $aanbod): array
    {
        $user = $aanbod->user;

        $fotos = $aanbod->relationLoaded('fotos')
            ? $aanbod->fotos
            : $aanbod->fotos()->get();

        return [
            'id' => $aanbod->id,
            'type' => (string) $aanbod->type,
            'tekst' => (string) $aanbod->tekst,
            'aantal' => (int) ($aanbod->aantal ?? 1),
            'voornaam' => $user?->publiekeNaam() ?? '',
            'locatie_label' => (string) ($user?->locatie_label ?? ''),
            'country' => Country::normalize((string) ($user?->country ?? Country::NL)),
            'lat' => $user?->lat !== null ? Geo::roundCoord((float) $user->lat) : null,
            'lon' => $user?->lon !== null ? Geo::roundCoord((float) $user->lon) : null,
            'user_id' => (int) $aanbod->user_id,
            'fotos' => $fotos->map(fn ($f) => $f->toPublicArray())->values()->all(),
            'verenigingen' => $this->verenigingenVoor($user),
            'geplaatst' => $aanbod->created_at?->timezone(config('app.timezone', 'Europe/Amsterdam'))->format('Y-m-d'),
            'verloopt' => $aanbod->expires_at?->timezone(config('app.timezone', 'Europe/Amsterdam'))->format('Y-m-d'),
            'soort' => $this->soortVoor($aanbod),
        ];
    }

    /**
     * Catalogussoort bij dit aanbod (naam, andere namen, stektips-link). Geen id als de soort onbekend is.
     *
     * @return array{naam: string, andere: list<string>, url: ?string}|null
     */
    private function soortVoor(Aanbod $aanbod): ?array
    {
        $id = (int) ($aanbod->plantensoort_id ?? 0);
        if ($id <= 0) {
            return null;
        }
        $row = $this->plantensoorten->find($id);
        if ($row === null || trim((string) ($row['naam'] ?? '')) === '') {
            return null;
        }
        $andere = [];
        $latijn = trim((string) ($row['latijn'] ?? ''));
        if ($latijn !== '' && strcasecmp($latijn, (string) $row['naam']) !== 0) {
            $andere[] = $latijn;
        }
        foreach ($row['synoniemen'] ?? [] as $syn) {
            $syn = trim((string) $syn);
            if ($syn === '' || strcasecmp($syn, (string) $row['naam']) === 0) {
                continue;
            }
            if (! in_array($syn, $andere, true)) {
                $andere[] = $syn;
            }
        }
        $url = null;
        if (($row['zichtbaar'] ?? true) && is_string($row['url'] ?? null) && $row['url'] !== '') {
            $url = $row['url'];
        }

        return [
            'naam' => (string) $row['naam'],
            'andere' => $andere,
            'url' => $url,
        ];
    }

    /**
     * Goedgekeurde verenigingen die het lid zelf openbaar heeft gezet.
     *
     * @return list<array{naam: string, url: string}>
     */
    private function verenigingenVoor(?User $user): array
    {
        if ($user === null || ! VerenigingService::beschikbaar()) {
            return [];
        }
        try {
            $rows = $user->relationLoaded('zichtbareVerenigingen')
                ? $user->zichtbareVerenigingen
                : $user->zichtbareVerenigingen()->get();
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn (Vereniging $v) => ['naam' => (string) $v->naam, 'url' => $v->url()])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sortByDistance(array $items, float $lat, float $lon): array
    {
        foreach ($items as &$item) {
            $ilat = isset($item['lat']) ? (float) $item['lat'] : null;
            $ilon = isset($item['lon']) ? (float) $item['lon'] : null;
            $item['afstand_km'] = ($ilat === null || $ilon === null)
                ? null
                : round(Geo::haversineKm($lat, $lon, $ilat, $ilon), 1);
        }
        unset($item);

        usort($items, static function ($a, $b) {
            $da = $a['afstand_km'];
            $db = $b['afstand_km'];
            if ($da === null && $db === null) {
                return 0;
            }
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }

            return $da <=> $db;
        });

        return $items;
    }
}
