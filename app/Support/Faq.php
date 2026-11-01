<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Veelgestelde vragen uit een JSON-bestand in data/ (stektips, deelplekken),
 * plus FAQPage-schema voor Google.
 */
final class Faq
{
    /** @return list<array{vraag: string, antwoord: string, link: ?array{label: string, pad: string}}> */
    public static function load(string $file): array
    {
        $path = base_path('data/'.$file);
        if (! is_readable($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        $out = [];
        foreach (($data['vragen'] ?? []) as $row) {
            if (! is_array($row) || empty($row['vraag']) || empty($row['antwoord'])) {
                continue;
            }
            $link = is_array($row['link'] ?? null) && ! empty($row['link']['pad']) && str_starts_with((string) $row['link']['pad'], '/')
                ? ['label' => (string) $row['link']['label'], 'pad' => (string) $row['link']['pad']]
                : null;
            $out[] = ['vraag' => (string) $row['vraag'], 'antwoord' => (string) $row['antwoord'], 'link' => $link];
        }

        return $out;
    }

    /**
     * @param  list<array{vraag: string, antwoord: string}>  $faq
     * @return array<string, mixed>
     */
    public static function schema(string $url, array $faq): array
    {
        return [
            '@type' => 'FAQPage',
            'url' => $url,
            'inLanguage' => 'nl-NL',
            'mainEntity' => array_map(static fn (array $f) => [
                '@type' => 'Question',
                'name' => $f['vraag'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['antwoord']],
            ], $faq),
        ];
    }
}
