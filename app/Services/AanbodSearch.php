<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Support\Geo;

final class AanbodSearch
{
    public function __construct(private readonly PdokGeocoder $geocoder) {}

    /**
     * @return array{meta: ?array{zoek_lat: float, zoek_lon: float, zoek_label: string}, items: list<array<string, mixed>>}
     */
    public function search(?string $query, ?string $typeFilter, bool $allowNetwork = true): array
    {
        $rows = Aanbod::query()
            ->publiclyListed()
            ->with(['user' => fn ($q) => $q->where('active', true)->whereNotNull('email_verified_at')])
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
            ->get();

        $items = $rows->map(fn (Aanbod $a) => $this->toPublicItem($a))->all();

        $q = trim((string) $query);
        if ($q !== '') {
            $geo = $this->geocoder->geocode($q, $allowNetwork);
            if ($geo) {
                $items = $this->sortByDistance($items, $geo['lat'], $geo['lon']);

                return [
                    'meta' => [
                        'zoek_lat' => $geo['lat'],
                        'zoek_lon' => $geo['lon'],
                        'zoek_label' => $geo['label'],
                    ],
                    'items' => $items,
                ];
            }
        }

        return ['meta' => null, 'items' => $items];
    }

    /** @return array<string, mixed> */
    public function toPublicItem(Aanbod $aanbod): array
    {
        $user = $aanbod->user;

        return [
            'id' => $aanbod->id,
            'type' => (string) $aanbod->type,
            'tekst' => (string) $aanbod->tekst,
            'voornaam' => (string) ($user?->name ?? ''),
            'locatie_label' => (string) ($user?->locatie_label ?? ''),
            'lat' => $user?->lat !== null ? Geo::roundCoord((float) $user->lat) : null,
            'lon' => $user?->lon !== null ? Geo::roundCoord((float) $user->lon) : null,
            'user_id' => (int) $aanbod->user_id,
        ];
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
