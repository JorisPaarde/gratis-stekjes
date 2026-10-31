<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deelplek;

/**
 * Idempotente check-update (okt 2026): alleen de 14 te verbergen + 2
 * gecorrigeerde seed-deelplekken bijwerken. Verwijdert niets.
 */
final class DeelplekkenGratisCheck
{
    /** @var list<string> */
    public const HIDE_NIET_GRATIS = [
        'haarlem-plantenasiel',
        'utrecht-plantenhuis',
        'maarssen-plant-voor-plant',
        'leiden-plantenasiel',
        'eindhoven-de-mortel',
        'houten-plantenasiel',
        'middelburg-plantaardig',
    ];

    /** @var list<string> */
    public const HIDE_TWIJFEL = [
        'wageningen-plantenasiel',
        'groningen-plantenasiel-050',
        'delft-mekelpark-ruilkast',
        'medemblik-plantenbieb',
        'voorburg-koningkerk-plantenbieb',
        'leidschenveen-plantenbieb',
        'leeuwarden-zadenbieb-fonteinland',
    ];

    /**
     * @return array{hidden: int, corrected: int, missing: list<string>}
     */
    public function apply(): array
    {
        $now = now();
        $hidden = 0;
        $corrected = 0;
        $missing = [];

        foreach (self::HIDE_NIET_GRATIS as $slug) {
            $n = Deelplek::query()->where('slug', $slug)->update([
                'visible' => false,
                'updated_at' => $now,
            ]);
            if ($n === 0) {
                $missing[] = $slug;
            } else {
                $hidden += $n;
            }
        }

        foreach (self::HIDE_TWIJFEL as $slug) {
            $n = Deelplek::query()->where('slug', $slug)->update([
                'visible' => false,
                'updated_at' => $now,
            ]);
            if ($n === 0) {
                $missing[] = $slug;
            } else {
                $hidden += $n;
            }
        }

        $corrections = $this->correctionsFromJson();
        foreach ($corrections as $slug => $fields) {
            $n = Deelplek::query()->where('slug', $slug)->update([
                'adres' => $fields['adres'],
                'bron_url' => $fields['bron_url'],
                'lat' => $fields['lat'],
                'lng' => $fields['lng'],
                'geraadpleegd' => $fields['geraadpleegd'],
                'updated_at' => $now,
            ]);
            if ($n === 0) {
                $missing[] = $slug;
            } else {
                $corrected += $n;
            }
        }

        return [
            'hidden' => $hidden,
            'corrected' => $corrected,
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * @return array<string, array{adres: string, bron_url: string, lat: float, lng: float, geraadpleegd: string}>
     */
    private function correctionsFromJson(): array
    {
        $fallback = [
            'joure-zadenbieb' => [
                'adres' => 'De Merk 31 (Bibliotheek Joure), 8501 AN Joure',
                'bron_url' => 'https://www.bmf.nl/laat-je-inspireren/zadenbibliotheek.html',
                'lat' => 52.964845,
                'lng' => 5.801159,
                'geraadpleegd' => '2026-10-31',
            ],
            'utrecht-zadenbibliotheek' => [
                'adres' => '2e Polderweg 5 (tuindersvereniging Ons Genot), 3565 MD Utrecht',
                'bron_url' => 'https://ons-genot.nl/zadenbibliotheek-utrecht/',
                'lat' => 52.124049,
                'lng' => 5.081672,
                'geraadpleegd' => '2026-10-31',
            ],
        ];

        try {
            $data = app(Deelplekken::class)->loadJson();
        } catch (\Throwable) {
            return $fallback;
        }

        $out = $fallback;
        foreach ($data['plekken'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = (string) ($row['id'] ?? '');
            if (! isset($out[$slug])) {
                continue;
            }
            $out[$slug] = [
                'adres' => trim(mb_substr((string) ($row['adres'] ?? $out[$slug]['adres']), 0, 255)),
                'bron_url' => trim(mb_substr((string) ($row['bron_url'] ?? $out[$slug]['bron_url']), 0, 500)),
                'lat' => (float) ($row['lat'] ?? $out[$slug]['lat']),
                'lng' => (float) ($row['lng'] ?? $out[$slug]['lng']),
                'geraadpleegd' => (string) ($row['geraadpleegd'] ?? $out[$slug]['geraadpleegd']),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function allTargetSlugs(): array
    {
        return array_values(array_unique([
            ...self::HIDE_NIET_GRATIS,
            ...self::HIDE_TWIJFEL,
            'joure-zadenbieb',
            'utrecht-zadenbibliotheek',
        ]));
    }
}
