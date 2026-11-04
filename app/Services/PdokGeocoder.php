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
 * NL-locaties via PDOK Locatieserver (free + reverse).
 *
 * Postcodes (0.58.2): altijd fq=type:postcode zodat «2037» Haarlem wordt i.p.v.
 * woonplaatscode Nederweert. Kale PC4: meerdere treffers → meest voorkomende woonplaats.
 * Plaatsnaam: ook postcodegebied (meest voorkomende PC4) meenemen.
 */
final class PdokGeocoder
{
    private const POSTCODE_ROWS = 25;

    private const PLAATS_POSTCODE_ROWS = 40;

    /**
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    public function geocode(string $query, bool $allowNetwork = true): ?array
    {
        $key = Geo::normalizeQuery($query);
        if ($key === '') {
            return null;
        }

        $isPostcode = (bool) preg_match('/^(\d{4})\s*([A-Za-z]{2})?$/', trim($query), $pc);

        $cached = GeocodeCache::query()->find($key);
        if ($cached) {
            $fromCache = $this->hitFromCache($cached, $query, $isPostcode);
            // Incomplete cache (kale PC4 zonder woonplaats): opnieuw ophalen.
            if ($fromCache !== null) {
                return $fromCache;
            }
        }

        if (! $allowNetwork) {
            return null;
        }

        $base = rtrim((string) config('stekjes.pdok_url'), '/');
        if ($base === '') {
            return null;
        }

        if ($isPostcode) {
            return $this->geocodePostcode($base, $key, $query, $pc[1], isset($pc[2]) ? strtoupper($pc[2]) : null);
        }

        return $this->geocodePlaats($base, $key, $query);
    }

    /**
     * Reverse: lat/lon → postcodegebied + woonplaats (PDOK reverse, type=postcode).
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

        // type=postcode: reverse zonder type gaf soms alleen een adres zonder postcode
        // (bijv. Spierdijk-centroid → «Verlaatsweg 44A» zonder PC).
        $docs = $this->pdokGet($base, [
            'lat' => $lat,
            'lon' => $lon,
            'type' => 'postcode',
            'rows' => 1,
            'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
        ], 'reverse');

        if ($docs === null || $docs === []) {
            $docs = $this->pdokGet($base, [
                'lat' => $lat,
                'lon' => $lon,
                'rows' => 5,
                'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
            ], 'reverse') ?? [];
        }

        if ($docs === []) {
            return null;
        }

        $doc = $this->pickDocWithPostcode($docs) ?? $docs[0];
        $weergave = $this->docString($doc, 'weergavenaam');
        $woonplaats = $this->docString($doc, 'woonplaatsnaam');
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel($weergave);
        }
        $postcode = PostalCode::gebied($this->docString($doc, 'postcode'), Country::NL)
            ?? PostalCode::gebied($weergave, Country::NL);

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

    /**
     * @param  array{1: string, 2?: string}  $pc
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    private function geocodePostcode(string $base, string $key, string $query, string $digits, ?string $letters): ?array
    {
        $q = $digits.($letters ?? '');
        // Kale PC4: meer rijen om meest voorkomende woonplaats te kiezen (meestal één).
        $rows = $letters === null ? self::POSTCODE_ROWS : 1;

        $docs = $this->pdokGet($base, [
            'q' => $q,
            'rows' => $rows,
            'fq' => 'type:postcode',
            'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
        ], 'postcode');

        if ($docs === null || $docs === []) {
            return null;
        }

        $doc = $this->pickByMostCommonWoonplaats($docs);
        if ($doc === null) {
            return null;
        }

        return $this->docToHit($doc, $key, $query, $digits);
    }

    /**
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    private function geocodePlaats(string $base, string $key, string $query): ?array
    {
        $docs = $this->pdokGet($base, [
            'q' => $query,
            'rows' => 1,
            'fl' => 'centroide_ll,weergavenaam,woonplaatsnaam,postcode,type,score',
        ], 'plaats');

        if ($docs === null || $docs === []) {
            return null;
        }

        $doc = $docs[0];
        $hit = $this->docToHit($doc, $key, $query, null);
        if ($hit === null) {
            return null;
        }

        // Plaatsnaam zonder postcode in het doc → PC4 via postcode-zoekopdracht.
        if ($hit['postcode'] === null && $hit['woonplaats'] !== null) {
            $pc4 = $this->mostCommonPc4ForPlaats($base, $hit['woonplaats']);
            if ($pc4 !== null) {
                $hit['postcode'] = $pc4;
                $hit['label'] = PostalCode::publicLabel($pc4, $hit['woonplaats']);
                $this->storeCache($key, $hit);
            }
        }

        return $hit;
    }

    /**
     * Meest voorkomende postcodegebied (PC4) voor een woonplaats.
     * Spierdijk → 1641 (niet de rand-PC van de centroid).
     */
    private function mostCommonPc4ForPlaats(string $base, string $woonplaats): ?string
    {
        $docs = $this->pdokGet($base, [
            'q' => $woonplaats,
            'rows' => self::PLAATS_POSTCODE_ROWS,
            'fq' => 'type:postcode',
            'fl' => 'postcode,woonplaatsnaam,score',
        ], 'plaats');

        if ($docs === null || $docs === []) {
            return null;
        }

        $counts = [];
        $scores = [];
        foreach ($docs as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $wp = mb_strtolower($this->docString($doc, 'woonplaatsnaam'), 'UTF-8');
            if ($wp !== '' && $wp !== mb_strtolower($woonplaats, 'UTF-8')) {
                continue;
            }
            $pc4 = PostalCode::gebied($this->docString($doc, 'postcode'), Country::NL);
            if ($pc4 === null) {
                continue;
            }
            $counts[$pc4] = ($counts[$pc4] ?? 0) + 1;
            $score = (float) ($doc['score'] ?? 0);
            $scores[$pc4] = max($scores[$pc4] ?? 0.0, $score);
        }

        if ($counts === []) {
            return null;
        }

        uksort($counts, static function (string $a, string $b) use ($counts, $scores): int {
            $cmp = $counts[$b] <=> $counts[$a];
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($scores[$b] ?? 0) <=> ($scores[$a] ?? 0);
        });

        $winner = array_key_first($counts);

        return $winner !== null ? (string) $winner : null;
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return array<string, mixed>|null
     */
    private function pickByMostCommonWoonplaats(array $docs): ?array
    {
        $counts = [];
        $bestByPlace = [];
        foreach ($docs as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $wp = $this->docString($doc, 'woonplaatsnaam');
            if ($wp === '') {
                $wp = PostalCode::plaatsFromGeoLabel($this->docString($doc, 'weergavenaam'));
            }
            if ($wp === '') {
                continue;
            }
            $key = mb_strtolower($wp, 'UTF-8');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $score = (float) ($doc['score'] ?? 0);
            $prev = $bestByPlace[$key] ?? null;
            if ($prev === null || $score > (float) ($prev['score'] ?? 0)) {
                $bestByPlace[$key] = $doc + ['_woonplaats' => $wp];
            }
        }

        if ($bestByPlace === []) {
            // Geen woonplaats in docs → eerste doc met geldige centroid.
            foreach ($docs as $doc) {
                if (is_array($doc) && $this->parseCentroid($doc) !== null) {
                    return $doc;
                }
            }

            return null;
        }

        uksort($counts, static function (string $a, string $b) use ($counts, $bestByPlace): int {
            $cmp = $counts[$b] <=> $counts[$a];
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((float) ($bestByPlace[$b]['score'] ?? 0)) <=> ((float) ($bestByPlace[$a]['score'] ?? 0));
        });

        $winner = array_key_first($counts);

        return $bestByPlace[$winner] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return array<string, mixed>|null
     */
    private function pickDocWithPostcode(array $docs): ?array
    {
        foreach ($docs as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $pc = PostalCode::gebied($this->docString($doc, 'postcode'), Country::NL)
                ?? PostalCode::gebied($this->docString($doc, 'weergavenaam'), Country::NL);
            if ($pc !== null) {
                return $doc;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    private function docToHit(array $doc, string $key, string $query, ?string $fallbackPc4): ?array
    {
        $centroid = $this->parseCentroid($doc);
        if ($centroid === null) {
            return null;
        }

        [$lon, $lat] = $centroid;
        $weergave = $this->docString($doc, 'weergavenaam');
        $woonplaats = isset($doc['_woonplaats'])
            ? trim((string) $doc['_woonplaats'])
            : $this->docString($doc, 'woonplaatsnaam');
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel($weergave, $query);
        }
        $postcode = PostalCode::gebied($this->docString($doc, 'postcode'), Country::NL)
            ?? $fallbackPc4
            ?? PostalCode::gebied($query, Country::NL)
            ?? PostalCode::gebied($weergave, Country::NL);

        $label = $woonplaats !== ''
            ? PostalCode::publicLabel($postcode, $woonplaats)
            : ($weergave !== '' ? $weergave : $query);

        $hit = [
            'lat' => $lat,
            'lon' => $lon,
            'label' => $label,
            'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
            'postcode' => $postcode,
        ];
        $this->storeCache($key, $hit);

        return $hit;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array{0: float, 1: float}|null  [lon, lat]
     */
    private function parseCentroid(array $doc): ?array
    {
        $centroid = (string) ($doc['centroide_ll'] ?? '');
        if (! preg_match('/POINT\s*\(\s*([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s*\)/i', $centroid, $m)) {
            return null;
        }

        return [Geo::roundCoord((float) $m[1]), Geo::roundCoord((float) $m[2])];
    }

    /**
     * @param  array<string, scalar|array|null>  $params
     * @return list<array<string, mixed>>|null
     */
    private function pdokGet(string $base, array $params, string $invoertype): ?array
    {
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
                'invoertype' => $invoertype,
                'bron' => 'pdok',
                'fout' => class_basename($e),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('geocode http-fout', [
                'land' => Country::NL,
                'invoertype' => $invoertype,
                'bron' => 'pdok',
                'status' => $response->status(),
            ]);

            return null;
        }

        $docs = $response->json('response.docs');
        if (! is_array($docs) || $docs === []) {
            $single = $response->json();
            if (is_array($single) && (isset($single['woonplaatsnaam']) || isset($single['weergavenaam']))) {
                return [$single];
            }

            return [];
        }

        /** @var list<array<string, mixed>> $docs */
        return array_values(array_filter($docs, 'is_array'));
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
                'country' => Country::NL,
                'cached_at' => now(),
            ]
        );
    }

    /**
     * @return array{lat: float, lon: float, label: string, woonplaats: ?string, postcode: ?string}|null
     */
    private function hitFromCache(GeocodeCache $cached, string $query, bool $isPostcode): ?array
    {
        $label = (string) $cached->label;
        $woonplaats = PostalCode::plaatsFromGeoLabel($label, $query);
        if ($woonplaats === '' && ! preg_match('/^\d{4}/', $label)) {
            $woonplaats = trim(explode(',', $label)[0]);
        }
        $postcode = PostalCode::gebied($query, Country::NL) ?? PostalCode::gebied($label, Country::NL);

        // Incomplete: postcode-zoekopdracht zonder afgeleide woonplaats → netwerk opnieuw.
        if ($isPostcode && $woonplaats === '') {
            return null;
        }

        return [
            'lat' => Geo::roundCoord((float) $cached->lat),
            'lon' => Geo::roundCoord((float) $cached->lon),
            'label' => $label,
            'woonplaats' => $woonplaats !== '' ? $woonplaats : null,
            'postcode' => $postcode,
        ];
    }

    /** @param  array<string, mixed>  $doc */
    private function docString(array $doc, string $field): string
    {
        $v = $doc[$field] ?? '';
        if (is_array($v)) {
            $v = $v[0] ?? '';
        }

        return trim((string) $v);
    }
}