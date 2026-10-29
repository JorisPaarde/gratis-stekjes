<?php
/**
 * Losstaande module: controleer vrije tekst (aanbod) op Unielijst-planten.
 * Geeft alleen een waarschuwing terug — geen blokkade.
 */

declare(strict_types=1);

const INVASIVE_DEFAULT_BRON_URL =
    'https://www.nvwa.nl/onderwerpen/plant/invasieve-exoten/unielijst-invasieve-exoten';

const INVASIVE_EUR_LEX_URL =
    'https://eur-lex.europa.eu/legal-content/NL/TXT/?uri=CELEX:02016R1141-20250807';

/**
 * Normaliseer tekst voor hoofdletterongevoelige matching.
 */
function invasive_normalize(string $text): string
{
    $text = str_replace(
        ["\xC2\xA0", '×', '✕', '⋅', '’', '‘', 'ʼ', '`', '´'],
        [' ', 'x', 'x', 'x', "'", "'", "'", "'", "'"],
        $text
    );

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    if (function_exists('iconv')) {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }
    }

    // Apostroffen weghalen zodat "Sosnowsky's" en "Sosnowskys" hetzelfde matchen.
    $text = str_replace("'", '', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return trim($text);
}

/**
 * Laad soortenlijst uit JSON. Geeft een array met sleutel "soorten" terug.
 *
 * @return array{meta?: array, soorten: list<array>}
 */
function invasive_load_species(?string $jsonPath = null): array
{
    if ($jsonPath === null) {
        $jsonPath = dirname(__DIR__, 2) . '/data/niet-aanbieden.json';
    }

    if (!is_readable($jsonPath)) {
        throw new RuntimeException('Invasieve-soortenbestand niet leesbaar: ' . $jsonPath);
    }

    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        throw new RuntimeException('Kon invasieve-soortenbestand niet lezen: ' . $jsonPath);
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['soorten']) || !is_array($data['soorten'])) {
        throw new RuntimeException('Ongeldig JSON-formaat in ' . $jsonPath);
    }

    return $data;
}

/**
 * Bouw zoektermen (genormaliseerd) → soort-index.
 *
 * @param list<array> $soorten
 * @return array<string, int>
 */
function invasive_build_term_index(array $soorten): array
{
    $index = [];

    foreach ($soorten as $i => $soort) {
        $terms = [];
        if (!empty($soort['nederlandse_naam'])) {
            $terms[] = (string) $soort['nederlandse_naam'];
        }
        if (!empty($soort['wetenschappelijke_naam'])) {
            $terms[] = (string) $soort['wetenschappelijke_naam'];
        }
        if (!empty($soort['synoniemen']) && is_array($soort['synoniemen'])) {
            foreach ($soort['synoniemen'] as $syn) {
                $terms[] = (string) $syn;
            }
        }

        foreach ($terms as $term) {
            $norm = invasive_normalize($term);
            if ($norm === '') {
                continue;
            }
            // Langere/exacte namen winnen bij gelijke term; eerste wint bij conflict.
            if (!isset($index[$norm])) {
                $index[$norm] = $i;
            }
        }
    }

    // Langste termen eerst matchen (voorkomt dat een kortere synonym een langere mist).
    uksort($index, static function (string $a, string $b): int {
        $len = strlen($b) <=> strlen($a);
        return $len !== 0 ? $len : strcmp($a, $b);
    });

    return $index;
}

/**
 * Zoek Unielijst-planten in vrije tekst.
 *
 * @return array{
 *   matches: list<array{wetenschappelijke_naam: string, nederlandse_naam: string, matched_term: string, bron_url: string}>,
 *   warning: ?string
 * }
 */
function invasive_check_text(string $text, ?string $jsonPath = null): array
{
    $data = invasive_load_species($jsonPath);
    $soorten = $data['soorten'];
    $haystack = ' ' . invasive_normalize($text) . ' ';
    $index = invasive_build_term_index($soorten);

    $found = [];
    foreach ($index as $term => $i) {
        $needle = ' ' . $term . ' ';
        if (strpos($haystack, $needle) === false) {
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
            'bron_url' => (string) ($soort['bron_url'] ?? INVASIVE_DEFAULT_BRON_URL),
        ];
    }

    $matches = array_values($found);
    $warning = null;

    if ($matches !== []) {
        $namen = [];
        foreach ($matches as $m) {
            $nl = $m['nederlandse_naam'];
            $sci = $m['wetenschappelijke_naam'];
            $namen[] = $nl !== '' && $sci !== '' ? "{$nl} ({$sci})" : ($nl !== '' ? $nl : $sci);
        }
        $lijst = implode(', ', $namen);
        $bron = $matches[0]['bron_url'] !== '' ? $matches[0]['bron_url'] : INVASIVE_DEFAULT_BRON_URL;
        $warning = 'Let op: in je tekst lijkt een plant op de EU-Unielijst van invasieve uitheemse soorten '
            . "voor te komen: {$lijst}. Handel, kweek, vervoer en import van deze soorten is in de EU verboden. "
            . "Dit is een waarschuwing, geen blokkade. Bron: {$bron} "
            . '(zie ook ' . INVASIVE_EUR_LEX_URL . ').';
    }

    return [
        'matches' => $matches,
        'warning' => $warning,
    ];
}
