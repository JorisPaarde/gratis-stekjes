<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Catalogus plantensoorten voor autocomplete bij stekje aanbieden.
 * Bron: data/plantensoorten.json (stektips + gangbare NL-soorten).
 * Verborgen soorten blijven in de data voor bestaande aanbod-referenties;
 * lijsten/zoeken tonen alleen zichtbare rijen. Zie docs/plantensoorten-selectie.md.
 */
final class Plantensoorten
{
    /**
     * @var list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, zichtbaar: bool, tags: list<string>, naam_norm: string, latijn_norm: string, syn_norms: list<string>}>|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /**
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string}>
     */
    public function all(bool $includeHidden = false): array
    {
        $rows = $includeHidden
            ? $this->rows()
            : array_values(array_filter($this->rows(), static fn (array $r): bool => $r['zichtbaar']));

        return array_map(fn (array $row) => $this->toPublic($row), $rows);
    }

    /**
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string}|null
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

    public function isVisible(int $id): bool
    {
        $soort = $this->find($id);

        return $soort !== null && ($soort['zichtbaar'] ?? true);
    }

    /**
     * Of een stektips-slug in publieke lijsten mag (verborgen catalogussoorten
     * blijven bereikbaar via directe URL).
     */
    public function isSlugListed(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }

        foreach ($this->rows() as $row) {
            if (($row['slug'] ?? null) === $slug) {
                return $row['zichtbaar'];
            }
        }

        // Soortpagina zonder catalogusrij: wel tonen.
        return true;
    }

    /**
     * Tags van een stektips-slug (voedselbos, inheems, …). Leeg als onbekend.
     *
     * @return list<string>
     */
    public function tagsForSlug(string $slug): array
    {
        if ($slug === '') {
            return [];
        }
        foreach ($this->rows() as $row) {
            if (($row['slug'] ?? null) === $slug) {
                return array_values($row['tags'] ?? []);
            }
        }

        return [];
    }

    /**
     * Stektips-slugs met een gegeven tag (alleen zichtbare catalogusrijen met slug).
     *
     * @return list<string>
     */
    public function slugsWithTag(string $tag): array
    {
        $tagNorm = mb_strtolower(trim($tag), 'UTF-8');
        if ($tagNorm === '') {
            return [];
        }
        $out = [];
        foreach ($this->rows() as $row) {
            if (! ($row['zichtbaar'] ?? true)) {
                continue;
            }
            $slug = $row['slug'] ?? null;
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            if (in_array($tagNorm, $row['tags'] ?? [], true)) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    /**
     * Zoek op Nederlandse naam, synoniem of Latijnse naam (alleen zichtbare soorten).
     * Ranking: exacte naam → naam begint-met → synoniem/Latijn begint-met → bevat.
     * Accent- en hoofdletterongevoelig. Bij synoniem-match: veld `match` = dat synoniem.
     *
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string, match: ?string}>
     */
    public function search(string $query, int $limit = 10, ?string $tag = null): array
    {
        $q = $this->normalize($query);
        if ($q === '' || mb_strlen($q) < 1) {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $tagNorm = $tag !== null && $tag !== '' ? mb_strtolower(trim($tag), 'UTF-8') : null;

        /** @var array<int, list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string, match: ?string}>> $buckets */
        $buckets = [
            0 => [], // exacte naam
            1 => [], // naam begint-met
            2 => [], // synoniem of Latijn begint-met
            3 => [], // bevat
        ];

        foreach ($this->rows() as $row) {
            if (! $row['zichtbaar']) {
                continue;
            }
            if ($tagNorm !== null && ! in_array($tagNorm, $row['tags'], true)) {
                continue;
            }

            $naamNorm = $row['naam_norm'];
            $latijnNorm = $row['latijn_norm'];

            $rank = null;
            $match = null;

            if ($naamNorm !== '' && $naamNorm === $q) {
                $rank = 0;
            } elseif ($naamNorm !== '' && str_starts_with($naamNorm, $q)) {
                $rank = 1;
            } else {
                $synStart = $this->firstMatchingSynonym($row, $q, 'starts');
                $latijnStarts = $latijnNorm !== '' && str_starts_with($latijnNorm, $q);

                if ($synStart !== null || $latijnStarts) {
                    $rank = 2;
                    $match = $synStart;
                } else {
                    $naamContains = $naamNorm !== '' && str_contains($naamNorm, $q);
                    $latijnContains = $latijnNorm !== '' && str_contains($latijnNorm, $q);
                    $synContains = $this->firstMatchingSynonym($row, $q, 'contains');

                    if ($naamContains || $latijnContains || $synContains !== null) {
                        $rank = 3;
                        if (! $naamContains) {
                            $match = $synContains;
                        }
                    }
                }
            }

            if ($rank === null) {
                continue;
            }

            $public = $this->toPublic($row);
            $public['match'] = $match;
            $buckets[$rank][] = $public;
        }

        foreach ($buckets as &$bucket) {
            usort($bucket, $this->sortByNaam(...));
        }
        unset($bucket);

        $merged = array_merge($buckets[0], $buckets[1], $buckets[2], $buckets[3]);

        return array_slice($merged, 0, $limit);
    }

    /**
     * Exacte match op genormaliseerde Nederlandse naam, synoniem of Latijnse naam.
     * Vindt ook verborgen soorten (bestaande aanbod / invasieve check).
     *
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string}|null
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
     * Maximaal aantal tikfouten: 1 bij korte termen (≤6), 2 bij langere.
     */
    public function maxEditDistance(string $normalizedQuery): int
    {
        $len = strlen($normalizedQuery);

        return $len <= 6 ? 1 : 2;
    }

    /**
     * Dichtstbijzijnde soorten op naam/synoniem/Latijn (Levenshtein na normalize).
     * Alleen zichtbare soorten. Optioneel alleen met stektips-slug.
     *
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string, match: ?string, distance: int}>
     */
    public function suggestClose(string $query, int $limit = 3, bool $onlyWithSlug = false, ?int $maxDistance = null): array
    {
        $q = $this->normalize($query);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $maxDistance ??= $this->maxEditDistance($q);
        $maxDistance = max(0, $maxDistance);

        /** @var list<array{distance: int, row: array<string, mixed>, match: ?string}> $scored */
        $scored = [];

        foreach ($this->rows() as $row) {
            if (! $row['zichtbaar']) {
                continue;
            }
            if ($onlyWithSlug && ($row['slug'] ?? null) === null) {
                continue;
            }

            $best = null;
            $bestMatch = null;
            foreach ($this->nameCandidates($row) as $candidate) {
                [$norm, $label] = $candidate;
                if ($norm === '' || abs(strlen($norm) - strlen($q)) > $maxDistance) {
                    continue;
                }
                $dist = levenshtein($q, $norm);
                if ($dist > $maxDistance) {
                    continue;
                }
                if ($best === null || $dist < $best) {
                    $best = $dist;
                    $bestMatch = $label === $row['naam'] ? null : $label;
                }
            }

            if ($best === null) {
                continue;
            }

            $scored[] = [
                'distance' => $best,
                'row' => $row,
                'match' => $bestMatch,
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            if ($a['distance'] !== $b['distance']) {
                return $a['distance'] <=> $b['distance'];
            }

            return strcasecmp((string) $a['row']['naam'], (string) $b['row']['naam']);
        });

        $out = [];
        foreach (array_slice($scored, 0, $limit) as $item) {
            $public = $this->toPublic($item['row']);
            $public['match'] = $item['match'];
            $public['distance'] = $item['distance'];
            $out[] = $public;
        }

        return $out;
    }

    /**
     * Stektips-slugs die bij een zoekterm horen: search() plus (bij 0 hits) tikfout-suggesties.
     *
     * @return list<string>
     */
    public function stektipsSlugsForQuery(string $query): array
    {
        $slugs = [];
        foreach ($this->search($query, 25) as $hit) {
            $slug = $hit['slug'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $slugs[$slug] = true;
            }
        }

        if ($slugs === []) {
            foreach ($this->suggestClose($query, 10, onlyWithSlug: true) as $hit) {
                $slug = $hit['slug'] ?? null;
                if (is_string($slug) && $slug !== '') {
                    $slugs[$slug] = true;
                }
            }
        }

        return array_keys($slugs);
    }

    /**
     * @param  array{naam: string, latijn: string, synoniemen: list<string>, naam_norm: string, latijn_norm: string, syn_norms: list<string>}  $row
     * @return list<array{0: string, 1: string}>
     */
    private function nameCandidates(array $row): array
    {
        $out = [];
        if ($row['naam_norm'] !== '') {
            $out[] = [$row['naam_norm'], $row['naam']];
        }
        if ($row['latijn_norm'] !== '') {
            $out[] = [$row['latijn_norm'], $row['latijn']];
        }
        foreach ($row['syn_norms'] as $i => $synNorm) {
            if ($synNorm === '') {
                continue;
            }
            $out[] = [$synNorm, $row['synoniemen'][$i] ?? $synNorm];
        }

        return $out;
    }

    /**
     * @return list<array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, zichtbaar: bool, tags: list<string>, naam_norm: string, latijn_norm: string, syn_norms: list<string>}>
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

            $tags = [];
            if (! empty($row['tags']) && is_array($row['tags'])) {
                foreach ($row['tags'] as $tag) {
                    if (is_string($tag) && trim($tag) !== '') {
                        $tags[] = mb_strtolower(trim($tag), 'UTF-8');
                    }
                }
            }
            $tags = array_values(array_unique($tags));

            // Ontbrekend veld = zichtbaar (backwards compatible).
            $zichtbaar = ! array_key_exists('zichtbaar', $row) || (bool) $row['zichtbaar'];

            $rows[] = [
                'id' => (int) $row['id'],
                'naam' => $naam,
                'latijn' => $latijn,
                'synoniemen' => array_values($syns),
                'slug' => $slug,
                'categorie' => is_string($row['categorie'] ?? null) ? (string) $row['categorie'] : '',
                'zichtbaar' => $zichtbaar,
                'tags' => $tags,
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
     * Eerste synoniem waarvan de genormaliseerde vorm begint met of de term bevat.
     *
     * @param  array{synoniemen: list<string>, syn_norms: list<string>}  $row
     * @param  'starts'|'contains'  $mode
     */
    private function firstMatchingSynonym(array $row, string $q, string $mode): ?string
    {
        foreach ($row['syn_norms'] as $i => $synNorm) {
            if ($synNorm === '') {
                continue;
            }
            $hit = $mode === 'starts'
                ? str_starts_with($synNorm, $q)
                : str_contains($synNorm, $q);
            if ($hit) {
                return $row['synoniemen'][$i] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, zichtbaar?: bool, tags?: list<string>, naam_norm?: string, latijn_norm?: string, syn_norms?: list<string>}  $row
     * @return array{id: int, naam: string, latijn: string, synoniemen: list<string>, slug: ?string, categorie: string, tags: list<string>, zichtbaar: bool, url: ?string}
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
            'tags' => array_values($row['tags'] ?? []),
            'zichtbaar' => (bool) ($row['zichtbaar'] ?? true),
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
