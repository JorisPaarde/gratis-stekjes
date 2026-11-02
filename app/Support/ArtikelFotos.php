<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Manifest van artikel-/video-foto’s (Unsplash) voor stekken- en snoeipagina’s.
 */
final class ArtikelFotos
{
    /** @var array<string, mixed>|null */
    private static ?array $manifest = null;

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $path = resource_path('data/artikel-fotos.json');
        $raw = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        self::$manifest = is_array($raw) ? $raw : ['fotos' => []];

        return self::$manifest;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $id): ?array
    {
        $foto = self::manifest()['fotos'][$id] ?? null;

        return is_array($foto) ? $foto : null;
    }

    /**
     * Unieke fotografennamen voor een artikel (volgorde van eerste voorkomen).
     *
     * @return list<string>
     */
    public static function makersVoorArtikel(string $artikelSlug): array
    {
        $seen = [];
        $makers = [];
        foreach (self::manifest()['fotos'] ?? [] as $foto) {
            if (! is_array($foto) || ($foto['artikel'] ?? '') !== $artikelSlug) {
                continue;
            }
            $maker = trim((string) ($foto['maker'] ?? ''));
            if ($maker === '' || isset($seen[$maker])) {
                continue;
            }
            $seen[$maker] = true;
            $makers[] = $maker;
        }

        return $makers;
    }
}
