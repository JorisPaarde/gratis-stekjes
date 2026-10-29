<?php

declare(strict_types=1);

/**
 * Maandkalender "Nu te stekken" (Europe/Amsterdam).
 * Data: data/nu-te-stekken.json
 */

const STEKJES_NU_TE_STEKKEN_TZ = 'Europe/Amsterdam';

const STEKJES_NU_TE_STEKKEN_METHODES = ['stek', 'scheut', 'deling', 'zaad'];

/**
 * @return DateTimeZone
 */
function stekjes_nu_te_stekken_timezone(): DateTimeZone
{
    return new DateTimeZone(STEKJES_NU_TE_STEKKEN_TZ);
}

/**
 * Kalendermaand 1–12 in Europe/Amsterdam voor een tijdstip.
 */
function stekjes_nu_te_stekken_maandnummer(?DateTimeInterface $when = null): int
{
    $tz = stekjes_nu_te_stekken_timezone();
    if ($when === null) {
        $local = new DateTimeImmutable('now', $tz);
    } else {
        $local = DateTimeImmutable::createFromInterface($when)->setTimezone($tz);
    }

    return (int) $local->format('n');
}

/**
 * Nederlandse maandnaam (kleine letters) bij nummer 1–12.
 */
function stekjes_nu_te_stekken_maandnaam(int $maand): string
{
    $namen = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    if (!isset($namen[$maand])) {
        throw new InvalidArgumentException('Maandnummer moet 1–12 zijn, kreeg: ' . $maand);
    }

    return $namen[$maand];
}

/**
 * Laad kalender-JSON.
 *
 * @return array{meta?: array, maanden: array<string, array>}
 */
function stekjes_nu_te_stekken_load(?string $jsonPath = null): array
{
    if ($jsonPath === null) {
        $jsonPath = dirname(__DIR__) . '/data/nu-te-stekken.json';
    }

    if (!is_readable($jsonPath)) {
        throw new RuntimeException('Nu-te-stekken-bestand niet leesbaar: ' . $jsonPath);
    }

    $raw = file_get_contents($jsonPath);
    if ($raw === false) {
        throw new RuntimeException('Kon nu-te-stekken-bestand niet lezen: ' . $jsonPath);
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['maanden']) || !is_array($data['maanden'])) {
        throw new RuntimeException('Ongeldig JSON-formaat in ' . $jsonPath);
    }

    return $data;
}

/**
 * Pad naar bestaande stektips-pagina, of null.
 */
function stekjes_nu_te_stekken_stek_pad(?string $slug): ?string
{
    if ($slug === null || $slug === '') {
        return null;
    }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return null;
    }

    $dir = dirname(__DIR__) . '/stektips/' . $slug;
    if (!is_dir($dir) || !is_file($dir . '/index.html')) {
        return null;
    }

    if (function_exists('stekjes_path')) {
        return stekjes_path('/stektips/' . $slug . '/');
    }

    return '/stektips/' . $slug . '/';
}

/**
 * Normaliseer één plantregel + optionele stek-link.
 *
 * @param array $plant
 * @return array{naam: string, slug: ?string, stek_url: ?string}
 */
function stekjes_nu_te_stekken_normalize_plant(array $plant): array
{
    $naam = trim((string) ($plant['naam'] ?? ''));
    $slug = $plant['slug'] ?? null;
    if ($slug !== null) {
        $slug = trim((string) $slug);
        if ($slug === '') {
            $slug = null;
        }
    }

    return [
        'naam' => $naam,
        'slug' => $slug,
        'stek_url' => stekjes_nu_te_stekken_stek_pad($slug),
    ];
}

/**
 * Groepen voor één kalendermaand (1–12).
 *
 * @return array{
 *   maand: int,
 *   maand_naam: string,
 *   timezone: string,
 *   groepen: list<array>
 * }
 */
function stekjes_nu_te_stekken_voor_maand(int $maand, ?string $jsonPath = null): array
{
    if ($maand < 1 || $maand > 12) {
        throw new InvalidArgumentException('Maandnummer moet 1–12 zijn, kreeg: ' . $maand);
    }

    $data = stekjes_nu_te_stekken_load($jsonPath);
    $key = (string) $maand;
    $blok = $data['maanden'][$key] ?? null;
    $groepenIn = is_array($blok) && isset($blok['groepen']) && is_array($blok['groepen'])
        ? $blok['groepen']
        : [];

    $groepen = [];
    foreach ($groepenIn as $g) {
        if (!is_array($g)) {
            continue;
        }
        $methode = (string) ($g['methode'] ?? '');
        if (!in_array($methode, STEKJES_NU_TE_STEKKEN_METHODES, true)) {
            continue;
        }
        $planten = [];
        foreach (($g['planten'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $np = stekjes_nu_te_stekken_normalize_plant($p);
            if ($np['naam'] === '') {
                continue;
            }
            $planten[] = $np;
        }
        if ($planten === []) {
            continue;
        }
        $bron = null;
        if (isset($g['bron']) && is_array($g['bron'])) {
            $bronNaam = trim((string) ($g['bron']['naam'] ?? ''));
            $bronUrl = trim((string) ($g['bron']['url'] ?? ''));
            if ($bronNaam !== '' && $bronUrl !== '') {
                $bron = ['naam' => $bronNaam, 'url' => $bronUrl];
            }
        }
        $groepen[] = [
            'id' => (string) ($g['id'] ?? ''),
            'titel' => (string) ($g['titel'] ?? ''),
            'methode' => $methode,
            'tip' => (string) ($g['tip'] ?? ''),
            'bron' => $bron,
            'planten' => $planten,
        ];
    }

    return [
        'maand' => $maand,
        'maand_naam' => stekjes_nu_te_stekken_maandnaam($maand),
        'timezone' => STEKJES_NU_TE_STEKKEN_TZ,
        'groepen' => $groepen,
    ];
}

/**
 * Selecteer blok voor "nu" (of gegeven tijdstip), altijd Europe/Amsterdam.
 *
 * @return array{
 *   maand: int,
 *   maand_naam: string,
 *   timezone: string,
 *   groepen: list<array>
 * }
 */
function stekjes_nu_te_stekken_nu(?DateTimeInterface $when = null, ?string $jsonPath = null): array
{
    return stekjes_nu_te_stekken_voor_maand(
        stekjes_nu_te_stekken_maandnummer($when),
        $jsonPath
    );
}
