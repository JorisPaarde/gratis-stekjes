<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Controleer vrije tekst (aanbod) op Unielijst-planten uit data/niet-aanbieden.json.
 */
final class InvasieveSoorten
{
    public const DEFAULT_BRON_URL =
        'https://www.nvwa.nl/onderwerpen/plant/invasieve-exoten/unielijst-invasieve-exoten';

    public const EUR_LEX_URL =
        'https://eur-lex.europa.eu/legal-content/NL/TXT/?uri=CELEX:02016R1141-20250807';

    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    public function normalize(string $text): string
    {
        $text = str_replace(
            ["\xC2\xA0", '×', '✕', '⋅', '’', '‘', 'ʼ', '`', '´'],
            [' ', 'x', 'x', 'x', "'", "'", "'", "'", "'"],
            $text
        );

        $text = mb_strtolower($text, 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        // Apostroffen weg zodat "Sosnowsky's" en "Sosnowskys" hetzelfde matchen.
        $text = str_replace("'", '', $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{meta?: array<string, mixed>, soorten: list<array<string, mixed>>}
     */
    public function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/niet-aanbieden.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Invasieve-soortenbestand niet leesbaar: '.$path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Kon invasieve-soortenbestand niet lezen: '.$path);
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['soorten']) || ! is_array($data['soorten'])) {
            throw new RuntimeException('Ongeldig JSON-formaat in '.$path);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_values(array_filter(
            $this->load()['soorten'],
            static fn ($row) => is_array($row)
                && ! empty($row['wetenschappelijke_naam'])
                && ! empty($row['nederlandse_naam'])
        ));
    }

    /** @return array<string, mixed>|null */
    public function meta(): ?array
    {
        $meta = $this->load()['meta'] ?? null;

        return is_array($meta) ? $meta : null;
    }

    /**
     * @return list<array{
     *   wetenschappelijke_naam: string,
     *   nederlandse_naam: string,
     *   matched_term: string,
     *   bron_url: string
     * }>
     */
    public function matches(string $text): array
    {
        $soorten = $this->all();
        $haystack = ' '.$this->normalize($text).' ';
        $index = $this->buildTermIndex($soorten);

        $found = [];
        foreach ($index as $term => $i) {
            if (! str_contains($haystack, ' '.$term.' ')) {
                continue;
            }
            if (isset($found[$i])) {
                continue;
            }
            $soort = $soorten[$i];
            $found[$i] = [
                'wetenschappelijke_naam' => (string) ($soort['wetenschappelijke_naam'] ?? ''),
                'nederlandse_naam' => (string) ($soort['nederlandse_naam'] ?? ''),
                'matched_term' => $term,
                'bron_url' => (string) ($soort['bron_url'] ?? self::DEFAULT_BRON_URL),
            ];
        }

        return array_values($found);
    }

    public function findsMatch(string $text): bool
    {
        return $this->matches($text) !== [];
    }

    /**
     * Foutmelding in gewoon Nederlands, met link naar de uitlegpagina.
     */
    public function blockMessage(string $text, ?string $uitlegUrl = null): ?string
    {
        $matches = $this->matches($text);
        if ($matches === []) {
            return null;
        }

        $namen = [];
        foreach ($matches as $m) {
            $nl = $m['nederlandse_naam'];
            $sci = $m['wetenschappelijke_naam'];
            $namen[] = $nl !== '' && $sci !== '' ? "{$nl} ({$sci})" : ($nl !== '' ? $nl : $sci);
        }

        $lijst = implode(', ', $namen);
        $url = $uitlegUrl ?? url('/invasieve-soorten');

        return 'Deze soort mag je hier niet aanbieden: '.$lijst.'. '
            .'Planten op de EU-Unielijst van invasieve uitheemse soorten mogen in de EU niet worden '
            .'verhandeld of verspreid — ook niet gratis als stekje. Ze schaden de natuur. '
            .'Meer uitleg en de volledige lijst: '.$url;
    }

    /**
     * Genormaliseerde termen voor optionele client-side waarschuwing.
     *
     * @return list<string>
     */
    public function termsForClient(): array
    {
        return array_keys($this->buildTermIndex($this->all()));
    }

    /**
     * @param  list<array<string, mixed>>  $soorten
     * @return array<string, int>
     */
    private function buildTermIndex(array $soorten): array
    {
        $index = [];

        foreach ($soorten as $i => $soort) {
            $terms = [];
            if (! empty($soort['nederlandse_naam'])) {
                if (is_array($soort['nederlandse_naam'])) {
                    foreach ($soort['nederlandse_naam'] as $nl) {
                        $terms[] = (string) $nl;
                    }
                } else {
                    $terms[] = (string) $soort['nederlandse_naam'];
                }
            }
            if (! empty($soort['nederlandse_namen']) && is_array($soort['nederlandse_namen'])) {
                foreach ($soort['nederlandse_namen'] as $nl) {
                    $terms[] = (string) $nl;
                }
            }
            if (! empty($soort['wetenschappelijke_naam'])) {
                $terms[] = (string) $soort['wetenschappelijke_naam'];
            }
            if (! empty($soort['synoniemen']) && is_array($soort['synoniemen'])) {
                foreach ($soort['synoniemen'] as $syn) {
                    $terms[] = (string) $syn;
                }
            }

            foreach ($terms as $term) {
                $norm = $this->normalize($term);
                if ($norm === '') {
                    continue;
                }
                if (! isset($index[$norm])) {
                    $index[$norm] = $i;
                }
            }
        }

        uksort($index, static function (string $a, string $b): int {
            $len = strlen($b) <=> strlen($a);

            return $len !== 0 ? $len : strcmp($a, $b);
        });

        return $index;
    }
}
