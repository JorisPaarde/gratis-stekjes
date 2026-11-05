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
    public function __construct(private readonly LocationGeocoder $geocoder) {}

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

        $items = $rows->map(fn (Aanbod $a) => $this->toPublicItem($a))->all();

        $plantNeedle = mb_strtolower(trim((string) $plant), 'UTF-8');
        if ($plantNeedle !== '') {
            $items = array_values(array_filter(
                $items,
                static function (array $item) use ($plantNeedle): bool {
                    $tekst = mb_strtolower((string) ($item['tekst'] ?? ''), 'UTF-8');

                    return str_contains($tekst, $plantNeedle);
                }
            ));
        }

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
