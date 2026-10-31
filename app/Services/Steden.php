<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Geo;
use RuntimeException;

final class Steden
{
    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /**
     * @return list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string
     * }>
     */
    public function all(): array
    {
        $data = $this->load();
        $out = [];
        foreach ($data['steden'] as $row) {
            if (! is_array($row) || empty($row['slug']) || empty($row['naam'])) {
                continue;
            }
            $out[] = $this->normalize($row);
        }

        usort($out, static fn ($a, $b) => strcasecmp($a['naam'], $b['naam']));

        return $out;
    }

    /**
     * @return array<string, list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string
     * }>>
     */
    public function groupedByProvincie(): array
    {
        $groups = [];
        foreach ($this->all() as $stad) {
            $prov = $stad['provincie'];
            $groups[$prov] ??= [];
            $groups[$prov][] = $stad;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $groups;
    }

    /**
     * @return array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string
     * }|null
     */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        foreach ($this->all() as $stad) {
            if ($stad['slug'] === $slug) {
                return $stad;
            }
        }

        return null;
    }

    /**
     * Nabije steden op haversine-afstand (exclusief huidige).
     *
     * @param  array{lat: float, lng: float, slug: string}  $stad
     * @return list<array{naam: string, slug: string, provincie: string, afstand_km: float}>
     */
    public function nearby(array $stad, int $limit = 6): array
    {
        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $slug = (string) $stad['slug'];
        $rows = [];
        foreach ($this->all() as $other) {
            if ($other['slug'] === $slug) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $other['lat'], $other['lng']);
            $rows[] = [
                'naam' => $other['naam'],
                'slug' => $other['slug'],
                'provincie' => $other['provincie'],
                'afstand_km' => round($km, 1),
            ];
        }
        usort($rows, static fn ($a, $b) => $a['afstand_km'] <=> $b['afstand_km']);

        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * FAQ-items voor een stad (FAQPage-schema + UI).
     *
     * @param  array{naam: string, straal_km: float}  $stad
     * @return list<array{vraag: string, antwoord: string}>
     */
    public function faqFor(array $stad): array
    {
        $naam = $stad['naam'];
        $straal = (int) round((float) $stad['straal_km']);
        $site = (string) config('site.name');

        return [
            [
                'vraag' => "Wat betekent ‘gratis planten in {$naam}’?",
                'antwoord' => "Op {$site} delen mensen in en rond {$naam} stekjes en planten zonder verkoop. Je haalt ze fysiek op; de enige tegenprestatie is een praatje.",
            ],
            [
                'vraag' => "Hoe ver zoekt deze pagina rond {$naam}?",
                'antwoord' => "Ik toon actueel aanbod binnen ongeveer {$straal} km van het centrum van {$naam}. Locaties op de kaart zijn benaderd (postcode of plaats), nooit een straatadres.",
            ],
            [
                'vraag' => 'Hoe bied ik zelf iets aan?',
                'antwoord' => 'Maak een account, bevestig je e-mail, vul een benaderde locatie in je profiel in en plaats aanbod. Contactgegevens zie je pas na wederzijds akkoord via de site.',
            ],
            [
                'vraag' => 'Zijn er ook vaste deelplekken?',
                'antwoord' => 'Als ik openbare deelplekken (zoals plantenasiel, plantenbieb of ruilkast) in of bij deze stad ken, toon ik die hier. Anders kun je landelijk zoeken op de aanbodkaart of de deelplekkenpagina — als die beschikbaar is.',
            ],
        ];
    }

    /**
     * Valideer JSON-structuur (CI / tests).
     *
     * @return list<string>
     */
    public function validateJson(): array
    {
        $errors = [];
        try {
            $data = $this->load();
        } catch (RuntimeException $e) {
            return [$e->getMessage()];
        }

        $steden = $data['steden'];
        if (count($steden) < 35) {
            $errors[] = 'Verwacht minstens 35 steden, kreeg '.count($steden);
        }

        $seen = [];
        foreach ($steden as $i => $row) {
            if (! is_array($row)) {
                $errors[] = "steden[$i] is geen object";

                continue;
            }
            foreach (['naam', 'slug', 'provincie', 'lat', 'lng', 'straal_km', 'intro'] as $key) {
                if (! array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === null) {
                    $errors[] = "steden[$i] mist veld $key";
                }
            }
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $errors[] = "steden[$i] ongeldige slug: $slug";
            }
            if ($slug !== '') {
                if (isset($seen[$slug])) {
                    $errors[] = "dubbele slug: $slug";
                } else {
                    $seen[$slug] = true;
                }
            }
            $lat = $row['lat'] ?? null;
            $lng = $row['lng'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                $errors[] = "steden[$i] lat/lng niet numeriek";
            } else {
                $latF = (float) $lat;
                $lngF = (float) $lng;
                // Stadspagina's blijven NL-only; bbox dekt NL (+ marge richting België).
                if ($latF < 50.5 || $latF > 53.7 || $lngF < 2.4 || $lngF > 7.4) {
                    $errors[] = "steden[$i] coördinaten buiten NL-bbox";
                }
            }
            $straal = $row['straal_km'] ?? null;
            if (! is_numeric($straal) || (float) $straal < 5 || (float) $straal > 40) {
                $errors[] = "steden[$i] straal_km moet tussen 5 en 40 liggen";
            }
            $intro = trim((string) ($row['intro'] ?? ''));
            $zinnen = preg_split('/(?<=[.!?])\s+/u', $intro) ?: [];
            $zinnen = array_values(array_filter($zinnen, static fn ($z) => trim($z) !== ''));
            if (count($zinnen) < 2 || count($zinnen) > 4) {
                $errors[] = "steden[$i] intro moet 2–4 zinnen zijn";
            }
        }

        return $errors;
    }

    /**
     * @return array{meta: array<string, mixed>, steden: list<array<string, mixed>>}
     */
    public function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/steden.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Steden-bestand niet leesbaar: '.$path);
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ! isset($data['steden']) || ! is_array($data['steden'])) {
            throw new RuntimeException('Ongeldig steden-JSON.');
        }
        if (! isset($data['meta']) || ! is_array($data['meta'])) {
            throw new RuntimeException('Steden-JSON mist meta.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string
     * }
     */
    private function normalize(array $row): array
    {
        return [
            'naam' => (string) $row['naam'],
            'slug' => (string) $row['slug'],
            'provincie' => (string) $row['provincie'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'straal_km' => (float) $row['straal_km'],
            'intro' => (string) $row['intro'],
        ];
    }
}
