<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Kennisbank
{
    public function __construct(
        private readonly ?string $directory = null,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $dir = $this->directory ?? base_path('data/kennisbank');
        $files = glob($dir.'/*.json') ?: [];
        $artikelen = [];

        foreach ($files as $path) {
            $base = basename($path);
            if ($base === 'categories.json') {
                continue;
            }
            $row = $this->decodeFile($path);
            if ($row === null) {
                continue;
            }
            $artikelen[] = $row;
        }

        usort(
            $artikelen,
            static fn (array $a, array $b): int => strcmp((string) ($a['titel'] ?? ''), (string) ($b['titel'] ?? ''))
        );

        return $artikelen;
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        foreach ($this->all() as $row) {
            if (($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array{slug: string, naam: string}>
     */
    public function categories(): array
    {
        $path = ($this->directory ?? base_path('data/kennisbank')).'/categories.json';
        if (! is_readable($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        $cats = $data['categorieen'] ?? [];
        if (! is_array($cats)) {
            return [];
        }

        $out = [];
        foreach ($cats as $cat) {
            if (! is_array($cat) || empty($cat['slug']) || empty($cat['naam'])) {
                continue;
            }
            $out[] = [
                'slug' => (string) $cat['slug'],
                'naam' => (string) $cat['naam'],
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    public function categoryLabels(): array
    {
        $labels = [];
        foreach ($this->categories() as $cat) {
            $labels[$cat['slug']] = $cat['naam'];
        }

        return $labels;
    }

    /**
     * Gerelateerde artikelen op gedeelde categorie of soort.
     *
     * @param  array<string, mixed>  $artikel
     * @return list<array<string, mixed>>
     */
    public function relatedArticles(array $artikel, int $limit = 6): array
    {
        $slug = (string) ($artikel['slug'] ?? '');
        $cats = $this->stringList($artikel['categorieen'] ?? []);
        $soorten = $this->stringList($artikel['gerelateerde_soorten'] ?? []);
        $scored = [];

        foreach ($this->all() as $other) {
            $otherSlug = (string) ($other['slug'] ?? '');
            if ($otherSlug === '' || $otherSlug === $slug) {
                continue;
            }
            $score = 0;
            $otherCats = $this->stringList($other['categorieen'] ?? []);
            $otherSoorten = $this->stringList($other['gerelateerde_soorten'] ?? []);
            $score += count(array_intersect($cats, $otherCats)) * 2;
            $score += count(array_intersect($soorten, $otherSoorten));
            if ($score > 0) {
                $scored[] = ['score' => $score, 'artikel' => $other];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $row) => $row['artikel'],
            array_slice($scored, 0, $limit)
        );
    }

    /**
     * Artikelen die bij een soortpagina horen.
     *
     * @return list<array<string, mixed>>
     */
    public function articlesForSoort(string $soortSlug, int $limit = 6): array
    {
        if ($soortSlug === '') {
            return [];
        }
        $matched = [];
        foreach ($this->all() as $artikel) {
            $soorten = $this->stringList($artikel['gerelateerde_soorten'] ?? []);
            if (in_array($soortSlug, $soorten, true)) {
                $matched[] = $artikel;
            }
        }

        return array_slice($matched, 0, $limit);
    }

    /**
     * Filter artikelen (server-side).
     *
     * @param  array{q?: string, categorie?: string, seizoen?: string, maand?: string|int, locatie?: string, soort?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function filter(array $filters): array
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')), 'UTF-8');
        $categorie = trim((string) ($filters['categorie'] ?? ''));
        $seizoen = trim((string) ($filters['seizoen'] ?? ''));
        $maand = (int) ($filters['maand'] ?? 0);
        $locatie = trim((string) ($filters['locatie'] ?? ''));
        $soort = trim((string) ($filters['soort'] ?? ''));

        $validCats = array_column($this->categories(), 'slug');
        if ($categorie !== '' && ! in_array($categorie, $validCats, true)) {
            $categorie = '';
        }
        if ($seizoen !== '' && ! in_array($seizoen, ['lente', 'zomer', 'herfst', 'winter'], true)) {
            $seizoen = '';
        }
        if ($maand < 1 || $maand > 12) {
            $maand = 0;
        }
        if ($locatie !== '' && ! in_array($locatie, ['binnen', 'buiten', 'beide'], true)) {
            $locatie = '';
        }

        $out = [];
        foreach ($this->all() as $artikel) {
            if ($categorie !== '' && ! in_array($categorie, $this->stringList($artikel['categorieen'] ?? []), true)) {
                continue;
            }
            if ($seizoen !== '' && ! in_array($seizoen, $this->stringList($artikel['seizoenen'] ?? []), true)) {
                continue;
            }
            if ($maand > 0) {
                $maanden = array_map('intval', $artikel['maanden'] ?? []);
                if (! in_array($maand, $maanden, true)) {
                    continue;
                }
            }
            if ($locatie !== '') {
                $artLoc = (string) ($artikel['locatie'] ?? 'beide');
                if ($artLoc !== 'beide' && $artLoc !== $locatie) {
                    continue;
                }
            }
            if ($soort !== '') {
                if (! in_array($soort, $this->stringList($artikel['gerelateerde_soorten'] ?? []), true)) {
                    continue;
                }
            }
            if ($q !== '' && ! $this->matchesQuery($artikel, $q)) {
                continue;
            }
            $out[] = $artikel;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $artikel
     */
    public function matchesQuery(array $artikel, string $q): bool
    {
        $hay = mb_strtolower(implode(' ', [
            (string) ($artikel['titel'] ?? ''),
            (string) ($artikel['samenvatting'] ?? ''),
            (string) ($artikel['slug'] ?? ''),
            implode(' ', $this->stringList($artikel['zoekwoorden'] ?? [])),
            implode(' ', $this->stringList($artikel['categorieen'] ?? [])),
            implode(' ', $this->stringList($artikel['gerelateerde_soorten'] ?? [])),
        ]), 'UTF-8');

        return str_contains($hay, $q);
    }

    /**
     * @param  array<string, mixed>  $artikel
     * @return array<string, mixed>
     */
    public function articleSchema(array $artikel, string $url): array
    {
        $bronnen = [];
        foreach ($artikel['bronnen'] ?? [] as $bron) {
            if (! is_array($bron) || empty($bron['url'])) {
                continue;
            }
            $bronnen[] = [
                '@type' => 'CreativeWork',
                'name' => (string) ($bron['titel'] ?? $bron['url']),
                'url' => (string) $bron['url'],
            ];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) ($artikel['titel'] ?? ''),
            'description' => (string) ($artikel['samenvatting'] ?? ''),
            'inLanguage' => 'nl-NL',
            'url' => $url,
            'author' => [
                '@type' => 'Organization',
                'name' => config('site.name'),
            ],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => config('site.name'),
                'url' => url('/'),
            ],
        ];

        if ($bronnen !== []) {
            $schema['citation'] = $bronnen;
        }

        if (! empty($artikel['howto'])) {
            $steps = [];
            foreach ($artikel['inhoud'] ?? [] as $block) {
                if (! is_array($block) || ($block['type'] ?? '') !== 'ol') {
                    continue;
                }
                foreach ($block['items'] ?? [] as $i => $item) {
                    if (! is_string($item) || trim($item) === '') {
                        continue;
                    }
                    $steps[] = [
                        '@type' => 'HowToStep',
                        'position' => $i + 1,
                        'text' => $item,
                    ];
                }
            }
            if ($steps !== []) {
                $schema = [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        $schema,
                        [
                            '@type' => 'HowTo',
                            'name' => (string) ($artikel['titel'] ?? ''),
                            'description' => (string) ($artikel['samenvatting'] ?? ''),
                            'step' => $steps,
                            'url' => $url,
                        ],
                    ],
                ];
            }
        }

        return $schema;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function decodeFile(string $path): ?array
    {
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || empty($data['slug']) || empty($data['titel'])) {
            return null;
        }
        $bronnen = $data['bronnen'] ?? [];
        if (! is_array($bronnen) || $bronnen === []) {
            throw new RuntimeException('Artikel zonder bronnen: '.$path);
        }
        $hasUrl = false;
        foreach ($bronnen as $bron) {
            if (is_array($bron) && ! empty($bron['url']) && preg_match('#^https?://#', (string) $bron['url'])) {
                $hasUrl = true;
                break;
            }
        }
        if (! $hasUrl) {
            throw new RuntimeException('Artikel zonder geldige bron-URL: '.$path);
        }

        return $data;
    }
}
