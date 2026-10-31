<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Support\Geo;
use Illuminate\Support\Facades\Schema;

/**
 * Actueel aanbod binnen een straal rond een stad + deelplekken (JSON/DB).
 */
final class StadAanbod
{
    public function __construct(
        private readonly AanbodSearch $search,
        private readonly Deelplekken $deelplekken,
    ) {}

    /**
     * @param  array{lat: float, lng: float, straal_km: float}  $stad
     * @return list<array<string, mixed>>
     */
    public function aanbodWithin(array $stad): array
    {
        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $straal = (float) $stad['straal_km'];

        $rows = Aanbod::query()
            ->publiclyListed()
            ->with(['user' => fn ($q) => $q->where('active', true)->whereNotNull('email_verified_at')])
            ->whereHas('user', function ($q) {
                $q->where('active', true)
                    ->whereNotNull('email_verified_at')
                    ->whereNotNull('lat')
                    ->whereNotNull('lon');
            })
            ->latest('created_at')
            ->get();

        $items = [];
        foreach ($rows as $aanbod) {
            $item = $this->search->toPublicItem($aanbod);
            $ilat = isset($item['lat']) ? (float) $item['lat'] : null;
            $ilon = isset($item['lon']) ? (float) $item['lon'] : null;
            if ($ilat === null || $ilon === null) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $ilat, $ilon);
            if ($km > $straal) {
                continue;
            }
            $item['afstand_km'] = round($km, 1);
            $items[] = $item;
        }

        usort($items, static fn ($a, $b) => ($a['afstand_km'] ?? 999) <=> ($b['afstand_km'] ?? 999));

        return $items;
    }

    /**
     * Deelplekken binnen straal: JSON (geseede plekken) plus goedgekeurde
     * aanmeldingen uit de database. Pending/afgewezen blijven buiten beeld.
     *
     * @param  array{lat: float, lng: float, straal_km: float, naam?: string}  $stad
     * @return list<array<string, mixed>>
     */
    public function deelplekkenWithin(array $stad): array
    {
        $fromJson = $this->deelplekkenFromJson($stad);
        if ($fromJson === [] && ! $this->deelplekkenJsonReadable()) {
            return $this->deelplekkenFromDatabase($stad) ?? [];
        }

        $fromAanmeldingen = $this->approvedAanmeldingenWithin($stad);
        if ($fromAanmeldingen === []) {
            return $fromJson;
        }

        $seen = [];
        foreach ($fromJson as $item) {
            $key = mb_strtolower(($item['naam'] ?? '').'|'.($item['plaats'] ?? ''), 'UTF-8');
            $seen[$key] = true;
        }
        foreach ($fromAanmeldingen as $item) {
            $key = mb_strtolower(($item['naam'] ?? '').'|'.($item['plaats'] ?? ''), 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $fromJson[] = $item;
            $seen[$key] = true;
        }

        usort($fromJson, static fn ($a, $b) => ($a['afstand_km'] ?? 999) <=> ($b['afstand_km'] ?? 999));

        return $fromJson;
    }

    /**
     * @param  array{lat: float, lng: float, straal_km: float}  $stad
     * @return list<array<string, mixed>>
     */
    private function approvedAanmeldingenWithin(array $stad): array
    {
        if (! class_exists(\App\Models\Deelplek::class)) {
            return [];
        }
        try {
            if (! Schema::hasTable('deelplekken')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $straal = (float) $stad['straal_km'];
        $labels = $this->deelplekken->typeLabels();

        try {
            $rows = \App\Models\Deelplek::query()
                ->visible()
                ->where('source', \App\Models\Deelplek::SOURCE_AANMELDING)
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $rlat = (float) $row->lat;
            $rlng = (float) $row->lng;
            $km = Geo::haversineKm($lat, $lng, $rlat, $rlng);
            if ($km > $straal) {
                continue;
            }
            $type = (string) $row->type;
            $out[] = [
                'naam' => (string) $row->naam,
                'type' => $type,
                'type_label' => $labels[$type] ?? $type,
                'plaats' => (string) $row->plaats,
                'provincie' => (string) $row->provincie,
                'lat' => Geo::roundCoord($rlat),
                'lng' => Geo::roundCoord($rlng),
                'afstand_km' => round($km, 1),
                'bron_url' => (string) $row->bron_url,
            ];
        }

        return $out;
    }

    private function deelplekkenJsonReadable(): bool
    {
        try {
            $this->deelplekken->loadJson();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array{lat: float, lng: float, straal_km: float}  $stad
     * @return list<array<string, mixed>>|null null = tabel/model niet bruikbaar
     */
    private function deelplekkenFromDatabase(array $stad): ?array
    {
        if (! class_exists(\App\Models\Deelplek::class)) {
            return null;
        }
        try {
            if (! Schema::hasTable('deelplekken')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $straal = (float) $stad['straal_km'];
        $labels = $this->deelplekken->typeLabels();

        try {
            $rows = \App\Models\Deelplek::query()->visible()->get();
        } catch (\Throwable) {
            return null;
        }

        $out = [];
        foreach ($rows as $row) {
            $rlat = (float) $row->lat;
            $rlng = (float) $row->lng;
            $km = Geo::haversineKm($lat, $lng, $rlat, $rlng);
            if ($km > $straal) {
                continue;
            }
            $type = (string) $row->type;
            $out[] = [
                'naam' => (string) $row->naam,
                'type' => $type,
                'type_label' => $labels[$type] ?? $type,
                'plaats' => (string) $row->plaats,
                'provincie' => (string) $row->provincie,
                'lat' => Geo::roundCoord($rlat),
                'lng' => Geo::roundCoord($rlng),
                'afstand_km' => round($km, 1),
                'bron_url' => (string) $row->bron_url,
            ];
        }

        usort($out, static fn ($a, $b) => ($a['afstand_km'] ?? 999) <=> ($b['afstand_km'] ?? 999));

        return $out;
    }

    /**
     * @param  array{lat: float, lng: float, straal_km: float}  $stad
     * @return list<array<string, mixed>>
     */
    private function deelplekkenFromJson(array $stad): array
    {
        try {
            $data = $this->deelplekken->loadJson();
        } catch (\Throwable) {
            return [];
        }

        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $straal = (float) $stad['straal_km'];
        $labels = $this->deelplekken->typeLabels();
        $out = [];

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
            $rlat = isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null;
            $rlng = isset($row['lng']) && is_numeric($row['lng']) ? (float) $row['lng'] : null;
            if ($rlat === null || $rlng === null) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $rlat, $rlng);
            if ($km > $straal) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            $out[] = [
                'naam' => (string) ($row['naam'] ?? ''),
                'type' => $type,
                'type_label' => $labels[$type] ?? $type,
                'plaats' => (string) ($row['plaats'] ?? ''),
                'provincie' => (string) ($row['provincie'] ?? ''),
                'lat' => Geo::roundCoord($rlat),
                'lng' => Geo::roundCoord($rlng),
                'afstand_km' => round($km, 1),
                'bron_url' => (string) ($row['bron_url'] ?? ''),
            ];
        }

        usort($out, static fn ($a, $b) => ($a['afstand_km'] ?? 999) <=> ($b['afstand_km'] ?? 999));

        return $out;
    }
}
