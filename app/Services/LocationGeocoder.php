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

/**
 * Locatiezoeken voor NL (PDOK) en heel België (Nominatim, countrycodes=nl,be).
 * Franstalige plaatsnamen worden overgenomen zoals de geocoder ze geeft (geen sitevertaling).
 */
final class LocationGeocoder
{
    public function __construct(
        private readonly PdokGeocoder $pdok,
    ) {}

    /**
     * @return array{ok: true, lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}
     *           | array{ok: false, error: string}
     */
    public function resolve(string $query, ?string $country = null, bool $allowNetwork = true): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'error' => 'Vul een postcode of plaats in.'];
        }

        $country = $country !== null && $country !== ''
            ? Country::normalize($country)
            : null;

        $isPostcode = $country !== null && PostalCode::looksLikePostcode($query, $country);

        if ($country === Country::BE && $isPostcode) {
            $pcCheck = PostalCode::normalize($query, Country::BE);
            if ($pcCheck !== null) {
                $valid = PostalCode::validate($pcCheck, Country::BE);
                if ($valid !== true) {
                    return ['ok' => false, 'error' => $valid];
                }
            }
        }

        $cacheKey = $this->cacheKey($query, $country);
        $cached = GeocodeCache::query()->find($cacheKey);
        if ($cached) {
            return ['ok' => true, ...$this->hitFromCache($cached, $country, $query)];
        }

        // Ook oude PDOK-cache zonder landprefix (alleen bij NL/null).
        if ($country === null || $country === Country::NL) {
            $legacyKey = Geo::normalizeQuery($query);
            if ($legacyKey !== $cacheKey) {
                $legacy = GeocodeCache::query()->find($legacyKey);
                if ($legacy) {
                    return ['ok' => true, ...$this->hitFromCache($legacy, $country ?? Country::NL, $query)];
                }
            }
        }

        if (! $allowNetwork) {
            return ['ok' => false, 'error' => 'Locatie niet gevonden.'];
        }

        if ($country === Country::NL) {
            $pdok = $this->pdok->geocode($query, true);
            if ($pdok === null) {
                return ['ok' => false, 'error' => 'Locatie niet gevonden. Gebruik een Nederlandse postcode (bijv. 1234 AB) of plaatsnaam.'];
            }
            $hit = [
                'lat' => $pdok['lat'],
                'lon' => $pdok['lon'],
                'label' => $pdok['label'],
                'country' => Country::NL,
                'postcode' => $pdok['postcode'] ?? PostalCode::gebied($query, Country::NL),
                'woonplaats' => $pdok['woonplaats'] ?? null,
            ];
            $this->storeCache($cacheKey, $hit);

            return ['ok' => true, ...$hit];
        }

        // BE of land onbekend (zoeken): Nominatim.
        $hit = $country === Country::BE && $isPostcode
            ? $this->nominatimPostalcode(PostalCode::normalize($query, Country::BE) ?? $query, 'be')
            : $this->nominatim($query, $country === Country::BE ? 'be' : 'nl,be', $isPostcode ? 'postcode' : 'plaats');

        if ($hit === null) {
            if ($country === null) {
                $pdok = $this->pdok->geocode($query, true);
                if ($pdok !== null) {
                    $hit = [
                        'lat' => $pdok['lat'],
                        'lon' => $pdok['lon'],
                        'label' => $pdok['label'],
                        'country' => Country::NL,
                        'postcode' => $pdok['postcode'] ?? PostalCode::gebied($query, Country::NL),
                        'woonplaats' => $pdok['woonplaats'] ?? null,
                    ];
                    $this->storeCache($cacheKey, $hit);

                    return ['ok' => true, ...$hit];
                }
            }

            $hint = $country === Country::BE
                ? ($isPostcode
                    ? 'Deze Belgische postcode vond ik niet. Controleer de cijfers of gebruik «Weet je je postcode niet?».'
                    : 'Locatie niet gevonden. Gebruik een Belgische postcode (4 cijfers) of een plaatsnaam.')
                : 'Locatie niet gevonden. Gebruik een postcode of plaats in Nederland of België.';

            return ['ok' => false, 'error' => $hint];
        }

        $this->storeCache($cacheKey, $hit);

        return ['ok' => true, ...$hit];
    }

    /**
     * Reverse geocode: lat/lon → postcodegebied + woonplaats.
     *
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}|null
     */
    public function reverse(float $lat, float $lon, string $country, bool $allowNetwork = true): ?array
    {
        $country = Country::normalize($country);

        if ($country === Country::NL) {
            $pdok = $this->pdok->reverse($lat, $lon, $allowNetwork);
            if ($pdok === null) {
                return null;
            }

            return [
                'lat' => $pdok['lat'],
                'lon' => $pdok['lon'],
                'label' => $pdok['label'],
                'country' => Country::NL,
                'postcode' => $pdok['postcode'],
                'woonplaats' => $pdok['woonplaats'],
            ];
        }

        return $this->nominatimReverse($lat, $lon, $country, $allowNetwork);
    }

    /**
     * Compat: zelfde vorm als PdokGeocoder::geocode (null bij fout).
     *
     * @return array{lat: float, lon: float, label: string}|null
     */
    public function geocode(string $query, bool $allowNetwork = true, ?string $country = null): ?array
    {
        $result = $this->resolve($query, $country, $allowNetwork);
        if (! ($result['ok'] ?? false)) {
            return null;
        }

        return [
            'lat' => $result['lat'],
            'lon' => $result['lon'],
            'label' => $result['label'],
        ];
    }

    /**
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}|null
     */
    private function nominatimPostalcode(string $postcode, string $countrycodes): ?array
    {
        $base = rtrim((string) config('stekjes.nominatim_url', 'https://nominatim.openstreetmap.org/search'), '/');
        if ($base === '') {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0').' ('.config('site.contact_email', 'info@gratisplanten.nl').')',
                ])
                ->get($base, [
                    'postalcode' => $postcode,
                    'country' => $countrycodes === 'be' ? 'Belgium' : 'Netherlands',
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => 1,
                    'countrycodes' => $countrycodes,
                ]);
        } catch (Throwable $e) {
            Log::warning('geocode timeout/fout', [
                'land' => strtoupper($countrycodes === 'be' ? 'BE' : 'NL'),
                'invoertype' => 'postcode',
                'bron' => 'nominatim',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => strtoupper($countrycodes === 'be' ? 'BE' : 'NL'),
                'invoertype' => 'postcode',
                'bron' => 'nominatim',
                'status' => $response->status(),
            ]);

            return null;
        }

        return $this->parseNominatimRow($response->json(), $postcode, $countrycodes === 'be' ? Country::BE : Country::NL);
    }

    /**
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}|null
     */
    private function nominatim(string $query, string $countrycodes, string $invoertype = 'plaats'): ?array
    {
        $base = rtrim((string) config('stekjes.nominatim_url', 'https://nominatim.openstreetmap.org/search'), '/');
        if ($base === '') {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0').' ('.config('site.contact_email', 'info@gratisplanten.nl').')',
                ])
                ->get($base, [
                    'q' => $query,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => 1,
                    'countrycodes' => $countrycodes,
                ]);
        } catch (Throwable $e) {
            Log::warning('geocode timeout/fout', [
                'land' => $countrycodes === 'be' ? Country::BE : ($countrycodes === 'nl' ? Country::NL : 'NL,BE'),
                'invoertype' => $invoertype,
                'bron' => 'nominatim',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => $countrycodes === 'be' ? Country::BE : 'NL,BE',
                'invoertype' => $invoertype,
                'bron' => 'nominatim',
                'status' => $response->status(),
            ]);

            return null;
        }

        $fallbackCountry = str_contains($countrycodes, 'be') && ! str_contains($countrycodes, 'nl')
            ? Country::BE
            : Country::NL;

        return $this->parseNominatimRow($response->json(), $query, $fallbackCountry);
    }

    /**
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}|null
     */
    private function nominatimReverse(float $lat, float $lon, string $country, bool $allowNetwork): ?array
    {
        if (! $allowNetwork) {
            return null;
        }

        $search = rtrim((string) config('stekjes.nominatim_url', 'https://nominatim.openstreetmap.org/search'), '/');
        $base = preg_replace('#/search$#', '/reverse', $search) ?? '';
        if ($base === '' || $base === $search) {
            $base = 'https://nominatim.openstreetmap.org/reverse';
        }

        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'GratisPlanten/'.config('site.version', '0').' ('.config('site.contact_email', 'info@gratisplanten.nl').')',
                ])
                ->get($base, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'zoom' => 16,
                ]);
        } catch (Throwable $e) {
            Log::warning('geocode timeout/fout', [
                'land' => $country,
                'invoertype' => 'reverse',
                'bron' => 'nominatim',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => $country,
                'invoertype' => 'reverse',
                'bron' => 'nominatim',
                'status' => $response->status(),
            ]);

            return null;
        }

        $row = $response->json();
        if (! is_array($row)) {
            return null;
        }

        return $this->parseNominatimRow([$row], null, $country);
    }

    /**
     * @param  mixed  $rows
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}|null
     */
    private function parseNominatimRow(mixed $rows, ?string $query, string $fallbackCountry): ?array
    {
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : (isset($rows['lat']) ? $rows : null);
        if (! is_array($row)) {
            return null;
        }

        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lon = isset($row['lon']) ? (float) $row['lon'] : null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $cc = strtoupper((string) ($address['country_code'] ?? ''));
        $country = $cc === 'BE' ? Country::BE : ($cc === 'NL' ? Country::NL : $fallbackCountry);

        $postcode = isset($address['postcode'])
            ? PostalCode::gebied((string) $address['postcode'], $country)
            : null;
        if ($postcode === null && $query !== null) {
            $postcode = PostalCode::gebied($query, $country);
        }

        $plaats = (string) (
            $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['suburb']
            ?? $address['city_district']
            ?? ''
        );
        $display = (string) ($row['display_name'] ?? ($query ?? ''));
        if ($plaats === '') {
            $plaats = PostalCode::plaatsFromGeoLabel($display, $query);
        }

        $label = PostalCode::publicLabel($postcode, $plaats !== '' ? $plaats : null);
        if ($label === '') {
            $label = $plaats !== '' ? $plaats : trim(explode(',', $display)[0]);
        }

        return [
            'lat' => Geo::roundCoord($lat),
            'lon' => Geo::roundCoord($lon),
            'label' => $label !== '' ? $label : $display,
            'country' => $country,
            'postcode' => $postcode,
            'woonplaats' => $plaats !== '' ? $plaats : null,
        ];
    }

    private function cacheKey(string $query, ?string $country): string
    {
        $prefix = $country !== null ? strtolower($country).':' : 'nl,be:';

        return Geo::normalizeQuery($prefix.$query);
    }

    /**
     * @param  array{lat: float, lon: float, label: string, country?: string}  $hit
     */
    private function storeCache(string $key, array $hit): void
    {
        GeocodeCache::query()->updateOrCreate(
            ['query_key' => $key],
            [
                'lat' => $hit['lat'],
                'lon' => $hit['lon'],
                'label' => $hit['label'],
                'country' => Country::normalize((string) ($hit['country'] ?? Country::NL)),
                'cached_at' => now(),
            ]
        );
    }

    /**
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string, woonplaats: ?string}
     */
    private function hitFromCache(GeocodeCache $cached, ?string $requested, string $query): array
    {
        $label = (string) $cached->label;
        $country = $this->countryFromCache($cached, $requested);
        $postcode = PostalCode::gebied($query, $country)
            ?? $this->extractPostcodeFromLabel($label);
        $woonplaats = PostalCode::plaatsFromGeoLabel($label, $query);
        if ($woonplaats === '' && $postcode !== null && preg_match('/^\d{4}\s+(.+)$/u', $label, $m)) {
            $woonplaats = trim($m[1]);
        }
        if ($woonplaats === '' && ! preg_match('/^\d{4}/', $label)) {
            $woonplaats = trim(explode(',', $label)[0]);
        }

        return [
            'lat' => Geo::roundCoord((float) $cached->lat),
            'lon' => Geo::roundCoord((float) $cached->lon),
            'label' => $label,
            'country' => $country,
            'postcode' => $postcode,
            'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
        ];
    }

    private function countryFromCache(GeocodeCache $cached, ?string $requested): string
    {
        $stored = trim((string) ($cached->country ?? ''));
        if ($stored !== '') {
            return Country::normalize($stored);
        }

        return $requested !== null ? Country::normalize($requested) : Country::NL;
    }

    private function extractPostcodeFromLabel(string $label): ?string
    {
        if (preg_match('/\b(\d{4})\b/', $label, $m)) {
            return $m[1];
        }

        return null;
    }
};
