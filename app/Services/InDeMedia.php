<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Mediavermeldingen uit data/in-de-media.json (geen database).
 *
 * @phpstan-type Vermelding array{
 *     medium: string,
 *     datum: string,
 *     kop: string,
 *     samenvatting: string,
 *     url: string,
 *     datum_label: string
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
     * @param  array<string, mixed>  $row
     * @return Vermelding|null
     */
    private function normalize(array $row): ?array
    {
        $medium = trim((string) ($row['medium'] ?? ''));
        $datum = trim((string) ($row['datum'] ?? ''));
        $kop = trim((string) ($row['kop'] ?? ''));
        $samenvatting = trim((string) ($row['samenvatting'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));

        if ($medium === '' || $kop === '' || $samenvatting === '' || $url === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            return null;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
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

        return [
            'medium' => $medium,
            'datum' => $datum,
            'kop' => $kop,
            'samenvatting' => $samenvatting,
            'url' => $url,
            'datum_label' => $label,
        ];
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
