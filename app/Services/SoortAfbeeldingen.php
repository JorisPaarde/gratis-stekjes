<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Self-hosted soortfoto’s met geverifieerde vrije licentie (CC0 / CC-BY / CC-BY-SA).
 * Metadata: data/soort-afbeeldingen.json. Ontbrekende soorten → neutrale SVG-fallback.
 * Alleen rijen met status «goedgekeurd» (of zonder status, oud formaat) worden getoond;
 * «te_bekijken» (nieuw opgehaald, nog niet met het oog gecontroleerd) valt terug op de SVG.
 */
final class SoortAfbeeldingen
{
    public const STATUS_GOEDGEKEURD = 'goedgekeurd';

    public const STATUS_TE_BEKIJKEN = 'te_bekijken';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $bySlug = null;

    public function __construct(private readonly ?string $jsonPath = null) {}

    /**
     * @return array{
     *   slug: string,
     *   url: string,
     *   url_desktop: ?string,
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
                    'url' => self::urlWithContentHash((string) $row['bestand'], (string) ($row['sha256'] ?? '')),
                    'url_desktop' => self::desktopUrl($row),
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
            'url_desktop' => null,
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

    /**
     * Kaartformaat (WebP) van een geverifieerde soortfoto; null als die er niet is.
     * Geen SVG-fallback: de aanbodkaart toont dan gewoon geen beeld.
     *
     * @return array{
     *   url: string,
     *   alt: string,
     *   auteur: string,
     *   licentie: string,
     *   licentie_url: string,
     *   bron_url: string,
     *   width: int,
     *   height: int
     * }|null
     */
    public function kaartVoorSlug(string $slug, string $naam = ''): ?array
    {
        if (! $this->hasLicensedImage($slug)) {
            return null;
        }

        $img = $this->forSlug($slug, $naam);
        $row = $this->all()[$slug] ?? [];
        $kaart = is_array($row['kaart'] ?? null) ? $row['kaart'] : [];
        $url = $img['url'];
        $width = 640;
        $height = 480;
        $bestand = (string) ($kaart['bestand'] ?? '');
        if ($bestand !== '' && is_file(public_path($bestand))) {
            $url = self::urlWithContentHash($bestand, (string) ($kaart['sha256'] ?? ''));
            $width = max(1, (int) ($kaart['width'] ?? 640));
            $height = max(1, (int) ($kaart['height'] ?? 480));
        }

        return [
            'url' => $url,
            'alt' => $img['alt'],
            'auteur' => $img['auteur'],
            'licentie' => $img['licentie'],
            'licentie_url' => $img['licentie_url'],
            'bron_url' => $img['bron_url'],
            'width' => $width,
            'height' => $height,
        ];
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
            $status = (string) ($row['status'] ?? self::STATUS_GOEDGEKEURD);
            if ($status !== self::STATUS_GOEDGEKEURD) {
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

    /**
     * Optionele bijgesneden desktop-hero; null als de soort geen desktop-veld heeft
     * of het bestand ontbreekt. Zelfde credit als de hoofd foto.
     *
     * @param  array<string, mixed>  $row
     */
    private static function desktopUrl(array $row): ?string
    {
        $desktop = $row['desktop'] ?? null;
        if (! is_array($desktop)) {
            return null;
        }
        $bestand = (string) ($desktop['bestand'] ?? '');
        if ($bestand === '' || ! is_file(public_path($bestand))) {
            return null;
        }

        return self::urlWithContentHash($bestand, (string) ($desktop['sha256'] ?? ''));
    }

    /**
     * Cache-busting query zodat SW/browser/CDN een stille vervanging van het
     * JPEG (zelfde pad, andere bytes — zoals de Cassis-schelp → Ribes) meteen tonen.
     */
    private static function urlWithContentHash(string $bestand, string $sha256): string
    {
        $url = asset($bestand);
        $hash = strtolower(trim($sha256));
        if ($hash === '' || ! preg_match('/^[a-f0-9]{12,64}$/', $hash)) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'h='.substr($hash, 0, 12);
    }
}
