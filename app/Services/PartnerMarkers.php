<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerMarker;
use App\Support\Geo;

final class PartnerMarkers
{
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
}
