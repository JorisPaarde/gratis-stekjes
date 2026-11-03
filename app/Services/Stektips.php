<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Stektips
{
    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $data = $this->load();

        return array_values(array_filter(
            $data['soorten'] ?? [],
            static fn ($row) => is_array($row) && ! empty($row['slug']) && ! empty($row['naam'])
        ));
    }

    /** Manieren van vermeerderen (sleutels = kennisbank-categorieën). */
    public const METHODES = [
        'stekken' => 'Stekken',
        'scheuren' => 'Scheuren',
        'zaaien' => 'Zaaien',
        'afleggen' => 'Afleggen',
        'uitlopers' => 'Uitlopers en kindjes',
        'enten' => 'Enten',
    ];

    /** Herkenningspatronen in de vrije tekst «methode» en «kort» van een soort. */
    private const METHODE_PATRONEN = [
        'stekken' => '/stek|top van \d|hout in een pot|in water/u',
        'scheuren' => '/scheur|\bdelen\b|deel de|\bpol\b|pollen|wortelstok|deling/u',
        'zaaien' => '/zaai|zaad|\bpit\b|pitten/u',
        'afleggen' => '/afleg|marcot|heuvelen/u',
        'uitlopers' => '/uitloper|kindje|zijscheut|broedbol|bolletje|stolon|knolletje/u',
        'enten' => '/\benten\b|oculeer|oculeren/u',
    ];

    /**
     * Welke manieren werken voor deze soort? Expliciet veld «methodes» wint; anders afgeleid uit de tekst.
     *
     * @param  array<string, mixed>  $soort
     * @return list<string>
     */
    public function methodes(array $soort): array
    {
        if (isset($soort['methodes']) && is_array($soort['methodes'])) {
            return array_values(array_filter($soort['methodes'], static fn ($m) => is_string($m) && isset(self::METHODES[$m])));
        }

        $tekst = mb_strtolower(((string) ($soort['methode'] ?? '')).' '.((string) ($soort['kort'] ?? '')), 'UTF-8');
        $uit = [];
        foreach (self::METHODE_PATRONEN as $methode => $patroon) {
            if (preg_match($patroon, $tekst) === 1) {
                $uit[] = $methode;
            }
        }

        return $uit;
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
     * @param  array<string, mixed>  $soort
     * @return list<array<string, mixed>>
     */
    public function related(array $soort): array
    {
        $slugs = $soort['verwanten'] ?? [];
        if (! is_array($slugs)) {
            return [];
        }

        $related = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            $found = $this->find($slug);
            if ($found !== null) {
                $related[] = $found;
            }
        }

        return $related;
    }

    /** @return array{soorten: list<array<string, mixed>>} */
    public function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/stektips.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Stektips-bestand niet leesbaar: '.$path);
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ! isset($data['soorten']) || ! is_array($data['soorten'])) {
            throw new RuntimeException('Ongeldig stektips-JSON.');
        }

        return $data;
    }
}
