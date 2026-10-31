<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

final class NuTeStekken
{
    public const TIMEZONE = 'Europe/Amsterdam';

    /** Maximaal aantal plantkaarten in het compacte blok (home / aanbod). */
    public const UITGELICHT_MAX = 6;

    /** @var list<string> */
    public const METHODES = ['stek', 'scheut', 'deling', 'zaad', 'enten', 'afleggen'];

    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    public function maandnummer(?DateTimeInterface $when = null): int
    {
        $local = $when === null
            ? Carbon::now(self::TIMEZONE)
            : Carbon::parse($when)->timezone(self::TIMEZONE);

        return (int) $local->format('n');
    }

    public function maandnaam(int $maand): string
    {
        $namen = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];
        if (! isset($namen[$maand])) {
            throw new InvalidArgumentException('Maandnummer moet 1–12 zijn, kreeg: '.$maand);
        }

        return $namen[$maand];
    }

    /**
     * @return array{maand: int, maand_naam: string, timezone: string, groepen: list<array<string, mixed>>}
     */
    public function voorMaand(int $maand): array
    {
        if ($maand < 1 || $maand > 12) {
            throw new InvalidArgumentException('Maandnummer moet 1–12 zijn, kreeg: '.$maand);
        }

        $data = $this->load();
        $blok = $data['maanden'][(string) $maand] ?? null;
        $groepenIn = is_array($blok) && isset($blok['groepen']) && is_array($blok['groepen'])
            ? $blok['groepen']
            : [];

        $groepen = [];
        foreach ($groepenIn as $g) {
            if (! is_array($g)) {
                continue;
            }
            $methode = (string) ($g['methode'] ?? '');
            if (! in_array($methode, self::METHODES, true)) {
                continue;
            }
            $planten = [];
            foreach (($g['planten'] ?? []) as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $np = $this->normalizePlant($p);
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
            'maand_naam' => $this->maandnaam($maand),
            'timezone' => self::TIMEZONE,
            'groepen' => $groepen,
        ];
    }

    /**
     * @return array{maand: int, maand_naam: string, timezone: string, groepen: list<array<string, mixed>>, intro: string, titel: string, stektips_index: string|null}
     */
    public function nu(?DateTimeInterface $when = null): array
    {
        $payload = $this->voorMaand($this->maandnummer($when));
        $payload['titel'] = 'Nu te stekken';
        $payload['intro'] = 'Deze planten kun je in '.$payload['maand_naam'].' goed stekken of delen.';
        $payload['stektips_index'] = $this->stektipsIndexUrl();

        return $payload;
    }

    /**
     * Compacte homepage/aanbod-weergave (null als geen groepen).
     *
     * @return array{maand_naam: string, intro: string, groepen: list<array<string, mixed>>}|null
     */
    public function blockForViews(?DateTimeInterface $when = null): ?array
    {
        $payload = $this->nu($when);
        if ($payload['groepen'] === []) {
            return null;
        }

        return [
            'maand_naam' => $payload['maand_naam'],
            'intro' => $payload['intro'],
            'groepen' => $payload['groepen'],
            'stektips_index' => $payload['stektips_index'],
            'uitgelicht' => $this->uitgelicht($payload['groepen']),
        ];
    }

    /**
     * Plantkaarten voor home/aanbod: alleen soorten met een stektip, met foto eerst,
     * zonder dubbelen, maximaal {@see self::UITGELICHT_MAX}.
     *
     * @param  list<array<string, mixed>>  $groepen
     * @return list<array{naam: string, slug: string, url: string, methode: string, afbeelding: array<string, mixed>}>
     */
    public function uitgelicht(array $groepen): array
    {
        $afbeeldingen = app(SoortAfbeeldingen::class);
        $metFoto = [];
        $zonderFoto = [];
        $gezien = [];

        foreach ($groepen as $groep) {
            foreach (($groep['planten'] ?? []) as $plant) {
                $slug = (string) ($plant['slug'] ?? '');
                $url = $plant['stek_url'] ?? null;
                if ($slug === '' || $url === null || isset($gezien[$slug])) {
                    continue;
                }
                $gezien[$slug] = true;
                $afbeelding = $afbeeldingen->forSlug($slug, (string) ($plant['naam'] ?? ''));
                $kaart = [
                    'naam' => (string) ($plant['naam'] ?? $slug),
                    'slug' => $slug,
                    'url' => (string) $url,
                    'methode' => (string) ($groep['methode'] ?? ''),
                    'afbeelding' => $afbeelding,
                ];
                if ($afbeelding['is_fallback']) {
                    $zonderFoto[] = $kaart;
                } else {
                    $metFoto[] = $kaart;
                }
            }
        }

        return array_slice(array_merge($metFoto, $zonderFoto), 0, self::UITGELICHT_MAX);
    }

    /** @return array{meta?: array<string, mixed>, maanden: array<string, mixed>} */
    public function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/nu-te-stekken.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Nu-te-stekken-bestand niet leesbaar: '.$path);
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Kon nu-te-stekken-bestand niet lezen: '.$path);
        }
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['maanden']) || ! is_array($data['maanden'])) {
            throw new RuntimeException('Ongeldig JSON-formaat in '.$path);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $plant
     * @return array{naam: string, slug: ?string, stek_url: ?string}
     */
    public function normalizePlant(array $plant): array
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
            'stek_url' => $this->stekUrl($slug),
        ];
    }

    public function stekUrl(?string $slug): ?string
    {
        if ($slug === null || $slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        // Alleen linken als de stektip echt bestaat (geen dode links).
        if (app(Stektips::class)->find($slug) === null) {
            return null;
        }

        return route('stektips.show', ['slug' => $slug]);
    }

    private function stektipsIndexUrl(): ?string
    {
        return app(Stektips::class)->all() !== [] ? route('stektips.index') : null;
    }
}
