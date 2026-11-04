<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeocodeCache;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PdokGeocoder
{
    /**
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    public function geocode(string $query, bool $allowNetwork = true): ?array
    {
        $key = Geo::normalizeQuery($query);
        if ($key === '') {
            return null;
        }

        $cached = GeocodeCache::query()->find($key);
        if ($cached) {
            $label = (string) $cached->label;
            $woonplaats = PostalCode::plaatsFromGeoLabel($label, $query);
            if ($woonplaats === '' && ! preg_match('/^\d{4}/', $label)) {
                $woonplaats = trim(explode(',', $label)[0]);
            }

            return [
                'lat' => Geo::roundCoord((float) $cached->lat),
                'lon' => Geo::roundCoord((float) $cached->lon),
                'label' => $label,
                'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
                'postcode' => PostalCode::gebied($query, Country::NL) ?? PostalCode::gebied($label, Country::NL),
            ];
        }

        if (! $allowNetwork) {
            return null;
        }

        $base = rtrim((string) config('stekjes.pdok_url'), '/');
        if ($base === '') {
            return null;
        }

        $params = [
            'q' => $query,
            'rows' => 1,
            'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
        ];
        // Postcode (2037 of 2037 KB): alleen postcodes zoeken. Anders wint een woonplaats
        // met dezelfde code (2037 = Nederweert) en staat het lid ver van zijn echte plek.
        $isPostcode = (bool) preg_match('/^(\d{4})\s*([A-Za-z]{2})?$/', trim($query), $pc);
        if ($isPostcode) {
            $params['q'] = $pc[1].strtoupper($pc[2] ?? '');
            $params['fq'] = 'type:postcode';
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0'),
                ])
                ->get($base, $params);
        } catch (Throwable $e) {
            Log::warning('geocode timeout/fout', [
                'land' => Country::NL,
                'invoertype' => $isPostcode ? 'postcode' : 'plaats',
                'bron' => 'pdok',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => Country::NL,
                'invoertype' => $isPostcode ? 'postcode' : 'plaats',
                'bron' => 'pdok',
                'status' => $response->status(),
            ]);

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
        $weergave = (string) ($doc['weergavenaam'] ?? $query);
        $woonplaats = trim((string) ($doc['woonplaatsnaam'] ?? ''));
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel($weergave, $query);
        }
        $postcode = isset($doc['postcode'])
            ? PostalCode::gebied((string) $doc['postcode'], Country::NL)
            : PostalCode::gebied($query, Country::NL);
        if ($postcode === null) {
            $postcode = PostalCode::gebied($weergave, Country::NL);
        }

        $label = $woonplaats !== ''
            ? PostalCode::publicLabel($postcode, $woonplaats)
            : ($weergave !== '' ? $weergave : $query);

        GeocodeCache::query()->updateOrCreate(
            ['query_key' => $key],
            [
                'lat' => $lat,
                'lon' => $lon,
                'label' => $label,
                'country' => Country::NL,
                'cached_at' => now(),
            ]
        );

        return [
            'lat' => $lat,
            'lon' => $lon,
            'label' => $label,
            'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
            'postcode' => $postcode,
        ];
    }

    /**
     * Reverse: lat/lon → postcodegebied + woonplaats (PDOK reverse).
     *
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    public function reverse(float $lat, float $lon, bool $allowNetwork = true): ?array
    {
        if (! $allowNetwork) {
            return null;
        }

        $free = rtrim((string) config('stekjes.pdok_url'), '/');
        if ($free === '') {
            return null;
        }
        $base = preg_replace('#/free$#', '/reverse', $free) ?? $free;
        if ($base === $free) {
            $base = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/reverse';
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0'),
                ])
                ->get($base, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'rows' => 1,
                    'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
                ]);
        } catch (Throwable $e) {
            Log::warning('geocode timeout/fout', [
                'land' => Country::NL,
                'invoertype' => 'reverse',
                'bron' => 'pdok',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => Country::NL,
                'invoertype' => 'reverse',
                'bron' => 'pdok',
                'status' => $response->status(),
            ]);

            return null;
        }

        $docs = $response->json('response.docs');
        if (! is_array($docs) || $docs === []) {
            // Sommige PDOK-versies zetten response anders; probeer ook top-level.
            $docs = $response->json('response.docs') ?? [];
        }
        if ((! is_array($docs) || $docs === []) && is_array($response->json())) {
            // reverse kan één doc teruggeven
            $single = $response->json();
            if (isset($single['woonplaatsnaam']) || isset($single['weergavenaam'])) {
                $docs = [$single];
            }
        }
        if (! is_array($docs) || $docs === []) {
            return null;
        }

        $doc = $docs[0];
        $weergave = (string) ($doc['weergavenaam'] ?? '');
        $woonplaats = trim((string) ($doc['woonplaatsnaam'] ?? ''));
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel($weergave);
        }
        $postcode = isset($doc['postcode'])
            ? PostalCode::gebied((string) $doc['postcode'], Country::NL)
            : PostalCode::gebied($weergave, Country::NL);

        if ($woonplaats === '' && $postcode === null) {
            return null;
        }

        return [
            'lat' => Geo::roundCoord($lat),
            'lon' => Geo::roundCoord($lon),
            'label' => PostalCode::publicLabel($postcode, $woonplaats !== '' ? $woonplaats : null),
            'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
            'postcode' => $postcode,
        ];
    }
};
