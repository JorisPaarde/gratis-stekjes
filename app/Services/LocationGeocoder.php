<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeocodeCache;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;
use Illuminate\Support\Facades\Http;
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
     * @return array{ok: true, lat: float, lon: float, label: string, country: string, postcode: ?string}
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

        if ($country === Country::BE) {
            $pcCheck = $this->belgianPostcodeFromQuery($query);
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
            $cachedCountry = $this->countryFromCache($cached, $country);
            $hit = [
                'lat' => Geo::roundCoord((float) $cached->lat),
                'lon' => Geo::roundCoord((float) $cached->lon),
                'label' => (string) $cached->label,
                'country' => $cachedCountry,
                'postcode' => $this->extractPostcodeFromLabel((string) $cached->label),
            ];

            return ['ok' => true, ...$hit];
        }

        // Ook oude PDOK-cache zonder landprefix (alleen bij NL/null).
        if ($country === null || $country === Country::NL) {
            $legacyKey = Geo::normalizeQuery($query);
            if ($legacyKey !== $cacheKey) {
                $legacy = GeocodeCache::query()->find($legacyKey);
                if ($legacy) {
                    $hit = [
                        'lat' => Geo::roundCoord((float) $legacy->lat),
                        'lon' => Geo::roundCoord((float) $legacy->lon),
                        'label' => (string) $legacy->label,
                        'country' => Country::normalize((string) ($legacy->country ?? Country::NL)),
                        'postcode' => $this->extractPostcodeFromLabel((string) $legacy->label),
                    ];

                    return ['ok' => true, ...$hit];
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
                'postcode' => $this->extractPostcodeFromLabel($pdok['label']),
            ];
            $this->storeCache($cacheKey, $hit);

            return ['ok' => true, ...$hit];
        }

        // BE of land onbekend (zoeken): Nominatim met countrycodes=nl,be.
        $hit = $this->nominatim($query, $country === Country::BE ? 'be' : 'nl,be');
        if ($hit === null) {
            if ($country === null) {
                $pdok = $this->pdok->geocode($query, true);
                if ($pdok !== null) {
                    $hit = [
                        'lat' => $pdok['lat'],
                        'lon' => $pdok['lon'],
                        'label' => $pdok['label'],
                        'country' => Country::NL,
                        'postcode' => $this->extractPostcodeFromLabel($pdok['label']),
                    ];
                    $this->storeCache($cacheKey, $hit);

                    return ['ok' => true, ...$hit];
                }
            }

            $hint = $country === Country::BE
                ? 'Locatie niet gevonden. Gebruik een Belgische postcode (4 cijfers) of een plaatsnaam.'
                : 'Locatie niet gevonden. Gebruik een postcode of plaats in Nederland of België.';

            return ['ok' => false, 'error' => $hint];
        }

        $this->storeCache($cacheKey, $hit);

        return ['ok' => true, ...$hit];
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
     * @return array{lat: float, lon: float, label: string, country: string, postcode: ?string}|null
     */
    private function nominatim(string $query, string $countrycodes): ?array
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
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $rows = $response->json();
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lon = isset($row['lon']) ? (float) $row['lon'] : null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $cc = strtoupper((string) ($address['country_code'] ?? ''));
        $country = $cc === 'BE' ? Country::BE : Country::NL;

        $postcode = isset($address['postcode'])
            ? substr(preg_replace('/\D/', '', (string) $address['postcode']) ?? '', 0, 4)
            : null;
        if ($postcode === '') {
            $postcode = null;
        }

        // Plaats: gemeente/deelgemeente zoals Nominatim die geeft (NL of FR).
        $plaats = (string) (
            $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['suburb']
            ?? $address['city_district']
            ?? ''
        );
        $display = (string) ($row['display_name'] ?? $query);
        $label = $plaats !== '' ? $plaats : trim(explode(',', $display)[0]);
        if (preg_match('/^\d{4}$/', trim($query))) {
            $label = trim($query);
        }

        return [
            'lat' => Geo::roundCoord($lat),
            'lon' => Geo::roundCoord($lon),
            'label' => $label !== '' ? $label : $display,
            'country' => $country,
            'postcode' => $postcode,
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

    private function countryFromCache(GeocodeCache $cached, ?string $requested): string
    {
        $stored = trim((string) ($cached->country ?? ''));
        if ($stored !== '') {
            return Country::normalize($stored);
        }

        return $requested !== null ? Country::normalize($requested) : Country::NL;
    }

    private function belgianPostcodeFromQuery(string $query): ?string
    {
        if (preg_match('/\b(\d{4})\b/', $query, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractPostcodeFromLabel(string $label): ?string
    {
        if (preg_match('/\b(\d{4})\b/', $label, $m)) {
            return $m[1];
        }

        return null;
    }
}
