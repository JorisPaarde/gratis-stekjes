<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerMarker;
use App\Support\Geo;

final class PartnerMarkers
{
    public function __construct(
        private readonly PdokGeocoder $geocoder,
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
                'beschrijving' => (string) $row->beschrijving,
                'lat' => Geo::roundCoord((float) $row->lat),
                'lon' => Geo::roundCoord((float) $row->lon),
                'url' => (string) $row->url,
                'kind' => 'partner_marker',
            ];
        })->all();
    }

    /**
     * Admin: maak of werk marker bij (PDOK-geocode op plaats).
     *
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function save(?int $id, array $fields, bool $allowNetworkGeo = true): array
    {
        $naam = trim(mb_substr((string) ($fields['naam'] ?? ''), 0, 160));
        $type = trim((string) ($fields['type'] ?? ''));
        $plaats = trim(mb_substr((string) ($fields['plaats'] ?? ''), 0, 120));
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
            return ['ok' => false, 'error' => 'Vul een plaats of postcode-4 in voor de benaderde plek.'];
        }
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'error' => 'Website moet met http:// of https:// beginnen (of leeg laten).'];
        }

        $existing = $id ? PartnerMarker::query()->find($id) : null;
        $lat = null;
        $lon = null;
        if ($existing && Geo::normalizeQuery($plaats) === Geo::normalizeQuery((string) $existing->plaats)) {
            $lat = (float) $existing->lat;
            $lon = (float) $existing->lon;
        } else {
            $geo = $this->geocoder->geocode($plaats, $allowNetworkGeo);
            if ($geo === null) {
                return ['ok' => false, 'error' => 'Plaats niet gevonden. Probeer een postcode-4 of woonplaats (PDOK).'];
            }
            $lat = $geo['lat'];
            $lon = $geo['lon'];
            if (! preg_match('/^\d{4}$/', $plaats)) {
                $plaats = trim(mb_substr(explode(',', $geo['label'])[0], 0, 120));
            }
        }

        if ($existing) {
            $existing->update([
                'naam' => $naam,
                'type' => $type,
                'plaats' => $plaats,
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
