<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Self-hosted soortfoto’s met geverifieerde vrije licentie (CC0 / CC-BY / CC-BY-SA).
 * Metadata: data/soort-afbeeldingen.json. Ontbrekende soorten → neutrale SVG-fallback.
 */
final class SoortAfbeeldingen
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $bySlug = null;

    public function __construct(private readonly ?string $jsonPath = null) {}

    /**
     * @return array{
     *   slug: string,
     *   url: string,
     *   alt: string,
     *   auteur: string,
     *   licentie: string,
     *   licentie_url: string,
     *   bron_url: string,
     *   is_fallback: bool
     * }
     */
    public function forSlug(string $slug, string $naam = ''): array
    {
        $slug = trim($slug);
        $row = $this->all()[$slug] ?? null;

        if ($row !== null) {
            $path = public_path((string) $row['bestand']);
            if (is_file($path)) {
                return [
                    'slug' => $slug,
                    'url' => asset((string) $row['bestand']),
                    'alt' => (string) ($row['alt'] ?? ($naam !== '' ? $naam : $slug)),
                    'auteur' => (string) ($row['auteur'] ?? ''),
                    'licentie' => (string) ($row['licentie'] ?? ''),
                    'licentie_url' => (string) ($row['licentie_url'] ?? ''),
                    'bron_url' => (string) ($row['bron_url'] ?? ''),
                    'is_fallback' => false,
                ];
            }
        }

        return [
            'slug' => $slug,
            'url' => asset('assets/illustrations/soort-fallback.svg'),
            'alt' => $naam !== '' ? 'Illustratie bij '.$naam : 'Neutrale plantillustratie',
            'auteur' => 'Gratis planten',
            'licentie' => 'CC0',
            'licentie_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'bron_url' => '',
            'is_fallback' => true,
        ];
    }

    public function hasLicensedImage(string $slug): bool
    {
        $img = $this->forSlug($slug);

        return ! $img['is_fallback'];
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        if ($this->bySlug !== null) {
            return $this->bySlug;
        }

        $path = $this->jsonPath ?? base_path('data/soort-afbeeldingen.json');
        if (! is_file($path)) {
            return $this->bySlug = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $rows = is_array($data['soorten'] ?? null) ? $data['soorten'] : [];
        $out = [];
        foreach ($rows as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? $key);
            if ($slug === '') {
                continue;
            }
            $out[$slug] = $row;
        }

        return $this->bySlug = $out;
    }

    public function licensedCount(): int
    {
        $n = 0;
        foreach (array_keys($this->all()) as $slug) {
            if ($this->hasLicensedImage($slug)) {
                $n++;
            }
        }

        return $n;
    }
}
