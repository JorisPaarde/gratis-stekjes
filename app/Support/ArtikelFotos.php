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
     * Id van de eigen kaartfoto van een artikel (veld `kaart_voor` in het manifest), of null.
     * Bewust geen terugval op soortfoto's of andere artikelen: dat gaf dubbele en lege kaartjes.
     */
    public static function kaartFotoId(string $artikelSlug): ?string
    {
        foreach (self::manifest()['fotos'] ?? [] as $id => $foto) {
            if (is_array($foto) && ($foto['kaart_voor'] ?? null) === $artikelSlug) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * Unieke fotografennamen voor een artikel (volgorde van eerste voorkomen):
     * eigen foto's plus de kaartfoto's die op de pagina staan.
     *
     * @param  list<string>  $extraIds  foto-id's van kaartjes op deze pagina
     * @return list<string>
     */
    public static function makersVoorArtikel(string $artikelSlug, array $extraIds = []): array
    {
        $seen = [];
        $makers = [];
        $fotos = self::manifest()['fotos'] ?? [];
        $opPagina = array_filter($fotos, fn ($foto) => is_array($foto) && ($foto['artikel'] ?? '') === $artikelSlug);
        foreach ($extraIds as $id) {
            if (is_array($fotos[$id] ?? null)) {
                $opPagina[$id] = $fotos[$id];
            }
        }
        foreach ($opPagina as $foto) {
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
