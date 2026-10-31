<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerMarker;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;

final class PartnerMarkers
{
    public function __construct(
        private readonly LocationGeocoder $geocoder,
    ) {}

    /** @return array<string, string> */
    public function typeLabels(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('stekjes.partner_types', []);

        return $labels;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->typeLabels());
    }

    public function typeValid(string $type): bool
    {
        return array_key_exists($type, $this->typeLabels());
    }

    /**
     * Publieke markers (afgeronde coördinaten, geen bron-veld).
     *
     * @return list<array<string, mixed>>
     */
    public function listPublic(?string $type = null): array
    {
        $query = PartnerMarker::query()->visible()->orderBy('naam');
        if ($type !== null && $type !== '') {
            if (! $this->typeValid($type)) {
                return [];
            }
            $query->ofType($type);
        }

        $labels = $this->typeLabels();

        return $query->get()->map(function (PartnerMarker $row) use ($labels) {
            $type = (string) $row->type;

            return [
                'id' => (int) $row->id,
                'naam' => (string) $row->naam,
                'type' => $type,
                'type_label' => $labels[$type] ?? $type,
                'plaats' => (string) $row->plaats,
                'country' => Country::normalize((string) ($row->country ?? Country::NL)),
                'beschrijving' => (string) $row->beschrijving,
                'lat' => Geo::roundCoord((float) $row->lat),
                'lon' => Geo::roundCoord((float) $row->lon),
                'url' => (string) $row->url,
                'kind' => 'partner_marker',
            ];
        })->all();
    }

    /**
     * Admin: maak of werk marker bij (geocode op plaats/postcode).
     *
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function save(?int $id, array $fields, bool $allowNetworkGeo = true): array
    {
        $naam = trim(mb_substr((string) ($fields['naam'] ?? ''), 0, 160));
        $type = trim((string) ($fields['type'] ?? ''));
        $plaats = trim(mb_substr((string) ($fields['plaats'] ?? ''), 0, 120));
        $country = Country::normalize((string) ($fields['country'] ?? Country::NL));
        $beschrijving = trim(mb_substr((string) ($fields['beschrijving'] ?? ''), 0, 500));
        $url = trim(mb_substr((string) ($fields['url'] ?? ''), 0, 500));
        $bron = trim(mb_substr((string) ($fields['bron'] ?? ''), 0, 500));
        $visible = ! empty($fields['visible']);

        if ($naam === '') {
            return ['ok' => false, 'error' => 'Vul een naam in.'];
        }
        if (! $this->typeValid($type)) {
            return ['ok' => false, 'error' => 'Kies een geldig type.'];
        }
        if ($plaats === '') {
            return ['ok' => false, 'error' => 'Vul een plaats of postcode in voor de benaderde plek.'];
        }
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'error' => 'Website moet met http:// of https:// beginnen (of leeg laten).'];
        }

        if (preg_match('/^\d{4}(\s*[A-Za-z]{2})?$/', $plaats)) {
            $pcValid = PostalCode::validate($plaats, $country);
            if ($pcValid !== true) {
                return ['ok' => false, 'error' => $pcValid];
            }
        }

        $existing = $id ? PartnerMarker::query()->find($id) : null;
        $lat = null;
        $lon = null;
        $samePlace = $existing
            && Geo::normalizeQuery($plaats) === Geo::normalizeQuery((string) $existing->plaats)
            && Country::normalize((string) ($existing->country ?? Country::NL)) === $country;
        if ($samePlace) {
            $lat = (float) $existing->lat;
            $lon = (float) $existing->lon;
        } else {
            $resolved = $this->geocoder->resolve($plaats, $country, $allowNetworkGeo);
            if (! ($resolved['ok'] ?? false)) {
                return ['ok' => false, 'error' => $resolved['error'] ?? 'Plaats niet gevonden.'];
            }
            $lat = $resolved['lat'];
            $lon = $resolved['lon'];
            if (! preg_match('/^\d{4}(\s*[A-Za-z]{2})?$/', $plaats)) {
                $plaats = trim(mb_substr(explode(',', $resolved['label'])[0], 0, 120));
            } else {
                $norm = PostalCode::normalize($plaats, $country);
                if ($norm !== null) {
                    $plaats = PostalCode::format($norm, $country);
                }
            }
        }

        if ($existing) {
            $existing->update([
                'naam' => $naam,
                'type' => $type,
                'plaats' => $plaats,
                'country' => $country,
                'beschrijving' => $beschrijving,
                'lat' => $lat,
                'lon' => $lon,
                'url' => $url,
                'bron' => $bron,
                'visible' => $visible,
            ]);

            return ['ok' => true, 'id' => (int) $existing->id];
        }

        $row = PartnerMarker::query()->create([
            'naam' => $naam,
            'type' => $type,
            'plaats' => $plaats,
            'country' => $country,
            'beschrijving' => $beschrijving,
            'lat' => $lat,
            'lon' => $lon,
            'url' => $url,
            'bron' => $bron,
            'visible' => $visible,
        ]);

        return ['ok' => true, 'id' => (int) $row->id];
    }

    /** @return array{ok: bool, error?: string} */
    public function delete(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Ongeldige marker.'];
        }
        $deleted = PartnerMarker::query()->whereKey($id)->delete();
        if ($deleted < 1) {
            return ['ok' => false, 'error' => 'Marker niet gevonden.'];
        }

        return ['ok' => true];
    }
}
