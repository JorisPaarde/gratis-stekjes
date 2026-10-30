<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeocodeCache;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PdokGeocoder
{
    /**
     * @return array{lat: float, lon: float, label: string}|null
     */
    public function geocode(string $query, bool $allowNetwork = true): ?array
    {
        $key = Geo::normalizeQuery($query);
        if ($key === '') {
            return null;
        }

        $cached = GeocodeCache::query()->find($key);
        if ($cached) {
            return [
                'lat' => Geo::roundCoord((float) $cached->lat),
                'lon' => Geo::roundCoord((float) $cached->lon),
                'label' => (string) $cached->label,
            ];
        }

        if (! $allowNetwork) {
            return null;
        }

        $base = rtrim((string) config('stekjes.pdok_url'), '/');
        if ($base === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0'),
                ])
                ->get($base, [
                    'q' => $query,
                    'rows' => 1,
                    'fl' => 'centroide_ll,weergavenaam,type,score',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $docs = $response->json('response.docs');
        if (! is_array($docs) || $docs === []) {
            return null;
        }

        $doc = $docs[0];
        $centroid = (string) ($doc['centroide_ll'] ?? '');
        if (! preg_match('/POINT\s*\(\s*([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s*\)/i', $centroid, $m)) {
            return null;
        }

        $lon = Geo::roundCoord((float) $m[1]);
        $lat = Geo::roundCoord((float) $m[2]);
        $label = (string) ($doc['weergavenaam'] ?? $query);
        if (preg_match('/^\d{4}$/', trim($query))) {
            $label = trim($query);
        }

        GeocodeCache::query()->updateOrCreate(
            ['query_key' => $key],
            [
                'lat' => $lat,
                'lon' => $lon,
                'label' => $label,
                'cached_at' => now(),
            ]
        );

        return ['lat' => $lat, 'lon' => $lon, 'label' => $label];
    }
}
