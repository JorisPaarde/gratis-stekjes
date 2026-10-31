<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deelplek;
use App\Support\Geo;
use Illuminate\Support\Collection;
use RuntimeException;

final class Deelplekken
{
    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /** @return array<string, string> */
    public function typeLabels(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('stekjes.deelplek_types', []);

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
     * @return array{meta: array<string, mixed>, plekken: list<array<string, mixed>>}
     */
    public function loadJson(): array
    {
        $path = $this->jsonPath ?? base_path('data/deelplekken.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Deelplekken-bestand niet leesbaar: '.$path);
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ! isset($data['plekken']) || ! is_array($data['plekken'])) {
            throw new RuntimeException('Ongeldig deelplekken-JSON.');
        }
        if (! isset($data['meta']) || ! is_array($data['meta'])) {
            throw new RuntimeException('Deelplekken-JSON mist meta.');
        }

        return $data;
    }

    /**
     * Valideer JSON-structuur (CI / tests).
     *
     * @return list<string> Fouten (leeg = ok)
     */
    public function validateJson(): array
    {
        $errors = [];
        try {
            $data = $this->loadJson();
        } catch (RuntimeException $e) {
            return [$e->getMessage()];
        }

        $types = $this->types();
        $seen = [];
        foreach ($data['plekken'] as $i => $row) {
            if (! is_array($row)) {
                $errors[] = "plekken[$i] is geen object";

                continue;
            }
            foreach (['id', 'naam', 'type', 'plaats', 'provincie', 'adres', 'lat', 'lng', 'bron_url', 'geraadpleegd'] as $key) {
                if (! array_key_exists($key, $row)) {
                    $errors[] = "plekken[$i] mist veld $key";
                }
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                $errors[] = "plekken[$i] heeft lege id";
            } elseif (isset($seen[$id])) {
                $errors[] = "dubbele id: $id";
            } else {
                $seen[$id] = true;
            }
            $type = (string) ($row['type'] ?? '');
            if ($type !== '' && ! in_array($type, $types, true)) {
                $errors[] = "plekken[$i] onbekend type: $type";
            }
            $lat = $row['lat'] ?? null;
            $lng = $row['lng'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                $errors[] = "plekken[$i] lat/lng niet numeriek";
            } else {
                $latF = (float) $lat;
                $lngF = (float) $lng;
                // NL + heel België (Ardennen ~49.5°, westkust BE ~2.5°).
                if ($latF < 49.4 || $latF > 53.7 || $lngF < 2.4 || $lngF > 7.4) {
                    $errors[] = "plekken[$i] coördinaten buiten NL+België-bbox";
                }
            }
            $bron = (string) ($row['bron_url'] ?? '');
            if ($bron !== '' && ! preg_match('#^https?://#i', $bron)) {
                $errors[] = "plekken[$i] bron_url moet http(s) zijn";
            }
            $date = (string) ($row['geraadpleegd'] ?? '');
            if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = "plekken[$i] geraadpleegd moet YYYY-MM-DD zijn";
            }
        }

        return $errors;
    }

    /**
     * @param  array{type?: string, provincie?: string, plaats?: string}  $filters
     * @return Collection<int, Deelplek>
     */
    public function queryVisible(array $filters = []): Collection
    {
        $query = Deelplek::query()->visible()->orderBy('plaats')->orderBy('naam');

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            if (! $this->typeValid($type)) {
                return collect();
            }
            $query->ofType($type);
        }

        $provincie = trim((string) ($filters['provincie'] ?? ''));
        if ($provincie !== '') {
            $query->where('provincie', $provincie);
        }

        $plaats = trim((string) ($filters['plaats'] ?? ''));
        if ($plaats !== '') {
            $needle = mb_strtolower($plaats, 'UTF-8');
            $query->whereRaw('LOWER(plaats) LIKE ?', ['%'.$needle.'%']);
        }

        return $query->get();
    }

    /** @return list<string> */
    public function provincies(): array
    {
        return Deelplek::query()->visible()
            ->distinct()
            ->orderBy('provincie')
            ->pluck('provincie')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Publieke markers (afgeronde coördinaten).
     *
     * @return list<array<string, mixed>>
     */
    public function listPublic(?string $type = null, ?string $provincie = null, ?string $plaats = null): array
    {
        $labels = $this->typeLabels();
        $rows = $this->queryVisible([
            'type' => $type ?? '',
            'provincie' => $provincie ?? '',
            'plaats' => $plaats ?? '',
        ]);

        return $rows->map(function (Deelplek $row) use ($labels) {
            $type = (string) $row->type;

            return [
                'id' => (int) $row->id,
                'slug' => (string) $row->slug,
                'naam' => (string) $row->naam,
                'type' => $type,
                'type_label' => $labels[$type] ?? $type,
                'plaats' => (string) $row->plaats,
                'provincie' => (string) $row->provincie,
                'country' => (string) ($row->country ?: 'NL'),
                'adres' => (string) $row->adres,
                'lat' => Geo::roundCoord((float) $row->lat),
                'lng' => Geo::roundCoord((float) $row->lng),
                'lon' => Geo::roundCoord((float) $row->lng),
                'bron_url' => (string) $row->bron_url,
                'geraadpleegd' => $row->geraadpleegd?->format('Y-m-d') ?? '',
                'kind' => 'deelplek',
            ];
        })->all();
    }
}
