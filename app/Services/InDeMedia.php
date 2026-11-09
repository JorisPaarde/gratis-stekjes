<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Mediavermeldingen uit data/in-de-media.json (geen database).
 *
 * Kaarttekst = vaste vorm: wie het medium is (`over_medium`) + dat zij op
 * `datum` een artikel over Gratis planten plaatsten met de `kop`.
 * Zie CONTEXT.md en docs/in-de-media.md.
 *
 * @phpstan-type Vermelding array{
 *     medium: string,
 *     datum: string,
 *     kop: string,
 *     over_medium: string,
 *     over_medium_bron: string,
 *     kaart_tekst: string,
 *     url: string,
 *     datum_label: string,
 *     image: string|null,
 *     image_webp: string|null,
 *     image_alt: string,
 *     image_credit: string
 * }
 */
final class InDeMedia
{
    public const TIMEZONE = 'Europe/Amsterdam';

    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /**
     * Vermeldingen, nieuwste eerst.
     *
     * @return list<Vermelding>
     */
    public function all(): array
    {
        $rows = [];
        foreach ($this->load()['vermeldingen'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = $this->normalize($row);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp($b['datum'], $a['datum']);
        });

        return array_values($rows);
    }

    /**
     * Vaste kaarttekst: medium-intro + publicatiezin met datum en kop.
     */
    public function kaartTekst(string $medium, string $overMedium, string $datumLabel, string $kop): string
    {
        $site = (string) config('site.name', 'Gratis planten');
        $kopZin = rtrim(trim($kop), ". \t\n\r\0\x0B");
        $publicatie = "Op {$datumLabel} plaatste {$medium} een artikel over {$site} met de kop «{$kopZin}».";

        $over = trim($overMedium);
        if ($over === '') {
            return $publicatie;
        }

        return rtrim($over, ". \t\n\r\0\x0B").'. '.$publicatie;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Vermelding|null
     */
    private function normalize(array $row): ?array
    {
        $medium = trim((string) ($row['medium'] ?? ''));
        $datum = trim((string) ($row['datum'] ?? ''));
        $kop = trim((string) ($row['kop'] ?? ''));
        $overMedium = trim((string) ($row['over_medium'] ?? ''));
        $overMediumBron = trim((string) ($row['over_medium_bron'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));

        if ($medium === '' || $kop === '' || $url === '' || $overMedium === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            return null;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        if ($overMediumBron !== '' && (! filter_var($overMediumBron, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $overMediumBron))) {
            return null;
        }

        try {
            $carbon = Carbon::createFromFormat('Y-m-d', $datum, self::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
        if ($carbon === null || $carbon->format('Y-m-d') !== $datum) {
            return null;
        }

        $maanden = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];
        $label = ((int) $carbon->format('j')).' '.$maanden[(int) $carbon->format('n')].' '.$carbon->format('Y');

        $image = $this->normalizeAssetPath($row['image'] ?? null);
        $imageWebp = $this->normalizeAssetPath($row['image_webp'] ?? null);
        if ($image !== null && ! is_file(public_path(ltrim($image, '/')))) {
            $image = null;
            $imageWebp = null;
        }
        if ($imageWebp !== null && ! is_file(public_path(ltrim($imageWebp, '/')))) {
            $imageWebp = null;
        }

        $imageAlt = trim((string) ($row['image_alt'] ?? ''));
        if ($imageAlt === '' && $image !== null) {
            $imageAlt = $kop;
        }
        $imageCredit = trim((string) ($row['image_credit'] ?? ''));
        if ($imageCredit === '' && $image !== null) {
            $imageCredit = $medium;
        }

        return [
            'medium' => $medium,
            'datum' => $datum,
            'kop' => $kop,
            'over_medium' => $overMedium,
            'over_medium_bron' => $overMediumBron,
            'kaart_tekst' => $this->kaartTekst($medium, $overMedium, $label, $kop),
            'url' => $url,
            'datum_label' => $label,
            'image' => $image,
            'image_webp' => $imageWebp,
            'image_alt' => $imageAlt,
            'image_credit' => $imageCredit,
        ];
    }

    private function normalizeAssetPath(mixed $value): ?string
    {
        $path = trim((string) $value);
        if ($path === '') {
            return null;
        }
        if (! str_starts_with($path, '/assets/')) {
            return null;
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/in-de-media.json');
        if (! is_readable($path)) {
            throw new RuntimeException('In-de-media data ontbreekt: '.$path);
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Kon in-de-media data niet lezen: '.$path);
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new RuntimeException('Ongeldige in-de-media JSON: '.$path);
        }

        return $data;
    }
}
