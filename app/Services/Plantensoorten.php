<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Catalogus plantensoorten voor autocomplete bij stekje aanbieden.
 * Bron: data/plantensoorten.json (48 stektips + gangbare NL-soorten).
 */
final class Plantensoorten
{
    /** @var list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, naam_norm: string, latijn_norm: string, syn_norms: list<string>}>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /**
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, url: ?string}>
     */
    public function all(): array
    {
        return array_map(fn (array $row) => $this->toPublic($row), $this->rows());
    }

    /**
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, url: ?string}|null
     */
    public function find(int $id): ?array
    {
        foreach ($this->rows() as $row) {
            if ($row['id'] === $id) {
                return $this->toPublic($row);
            }
        }

        return null;
    }

    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Zoek op Nederlandse naam, synoniem of Latijnse naam.
     * Begint-met eerst, daarna bevat; accent- en hoofdletterongevoelig.
     *
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, url: ?string}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $q = $this->normalize($query);
        if ($q === '' || mb_strlen($q) < 1) {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $starts = [];
        $contains = [];

        foreach ($this->rows() as $row) {
            $fields = array_merge(
                [$row['naam_norm'], $row['latijn_norm']],
                $row['syn_norms']
            );

            $isStart = false;
            $isContain = false;
            foreach ($fields as $field) {
                if ($field === '') {
                    continue;
                }
                if (str_starts_with($field, $q)) {
                    $isStart = true;
                    break;
                }
                if (str_contains($field, $q)) {
                    $isContain = true;
                }
            }

            if ($isStart) {
                $starts[] = $row;
            } elseif ($isContain) {
                $contains[] = $row;
            }
        }

        usort($starts, $this->sortByNaam(...));
        usort($contains, $this->sortByNaam(...));

        $merged = array_slice(array_merge($starts, $contains), 0, $limit);

        return array_map(fn (array $row) => $this->toPublic($row), $merged);
    }

    /**
     * Exacte match op genormaliseerde Nederlandse naam, synoniem of Latijnse naam.
     *
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, url: ?string}|null
     */
    public function resolveExact(string $text): ?array
    {
        $norm = $this->normalize($text);
        if ($norm === '') {
            return null;
        }

        foreach ($this->rows() as $row) {
            if ($row['naam_norm'] === $norm || $row['latijn_norm'] === $norm) {
                return $this->toPublic($row);
            }
            foreach ($row['syn_norms'] as $syn) {
                if ($syn === $norm) {
                    return $this->toPublic($row);
                }
            }
        }

        return null;
    }

    /**
     * Tekst om te checken op invasieve soorten: ingevoerde tekst plus
     * (bij bekende soort) naam, Latijn en synoniemen.
     */
    public function textForInvasieveCheck(string $tekst, ?int $plantensoortId): string
    {
        $parts = [trim($tekst)];
        if ($plantensoortId !== null) {
            $soort = $this->find($plantensoortId);
            if ($soort !== null) {
                $parts[] = $soort['naam'];
                $parts[] = $soort['latijn'];
                foreach ($soort['synoniemen'] as $syn) {
                    $parts[] = $syn;
                }
            }
        }

        return trim(implode(' ', array_filter($parts, static fn ($p) => $p !== '')));
    }

    public function normalize(string $text): string
    {
        $text = str_replace(
            ["\xC2\xA0", '×', '✕', '⋅', '’', '‘', 'ʼ', '`', '´'],
            [' ', 'x', 'x', 'x', "'", "'", "'", "'", "'"],
            $text
        );

        $text = mb_strtolower($text, 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        $text = str_replace("'", '', $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, naam_norm: string, latijn_norm: string, syn_norms: list<string>}>
     */
    private function rows(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $path = $this->jsonPath ?? base_path('data/plantensoorten.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Plantensoortenbestand niet leesbaar: '.$path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Kon plantensoortenbestand niet lezen: '.$path);
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['soorten']) || ! is_array($data['soorten'])) {
            throw new RuntimeException('Ongeldig JSON-formaat in '.$path);
        }

        $rows = [];
        foreach ($data['soorten'] as $row) {
            if (! is_array($row) || empty($row['id']) || empty($row['naam']) || empty($row['latijn'])) {
                continue;
            }

            $syns = [];
            if (! empty($row['synoniemen']) && is_array($row['synoniemen'])) {
                foreach ($row['synoniemen'] as $syn) {
                    if (is_string($syn) && trim($syn) !== '') {
                        $syns[] = trim($syn);
                    }
                }
            }

            $slug = isset($row['slug']) && is_string($row['slug']) && $row['slug'] !== ''
                ? $row['slug']
                : null;

            $naam = (string) $row['naam'];
            $latijn = (string) $row['latijn'];

            $rows[] = [
                'id' => (int) $row['id'],
                'naam' => $naam,
                'latijn' => $latijn,
                'synoniemen' => array_values($syns),
                'slug' => $slug,
                'categorie' => is_string($row['categorie'] ?? null) ? (string) $row['categorie'] : '',
                'naam_norm' => $this->normalize($naam),
                'latijn_norm' => $this->normalize($latijn),
                'syn_norms' => array_values(array_filter(
                    array_map(fn (string $s) => $this->normalize($s), $syns),
                    static fn (string $s) => $s !== ''
                )),
            ];
        }

        return $this->cache = $rows;
    }

    /**
     * @param  array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, naam_norm?: string, latijn_norm?: string, syn_norms?: list<string>}  $row
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, url: ?string}
     */
    private function toPublic(array $row): array
    {
        $slug = $row['slug'] ?? null;
        // Relatief pad: werkt in unit-tests zonder route-helper; views/API kunnen abs. URL maken.
        $url = (is_string($slug) && $slug !== '') ? '/stektips/'.$slug : null;

        return [
            'id' => $row['id'],
            'naam' => $row['naam'],
            'latijn' => $row['latijn'],
            'synoniemen' => $row['synoniemen'],
            'slug' => $slug,
            'categorie' => $row['categorie'] ?? '',
            'url' => $url,
        ];
    }

    /**
     * @param  array{naam: string}  $a
     * @param  array{naam: string}  $b
     */
    private function sortByNaam(array $a, array $b): int
    {
        return strcasecmp($a['naam'], $b['naam']);
    }
}
