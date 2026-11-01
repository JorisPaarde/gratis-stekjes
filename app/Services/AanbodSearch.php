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

        return Aanbod::query()
            ->publiclyListed()
            ->with($with)
            ->whereHas('user', function ($q) {
                $q->where('active', true)
                    ->whereNotNull('email_verified_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lon');
            })
            ->when(
                in_array($typeFilter, ['uitdelen', 'ophalen'], true),
                fn ($q) => $q->where('type', $typeFilter)
            )
            ->latest('created_at')
            ->orderByDesc('id');
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
            'voornaam' => (string) ($user?->name ?? ''),
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
