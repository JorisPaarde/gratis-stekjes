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
     *   intro: string,
     *   country: string
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
     *   intro: string,
     *   country: string
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
     * Groepeer eerst op land (Nederland / België), daarna op provincie.
     *
     * @return array<string, array<string, list<array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string
     * }>>>
     */
    public function groupedByLand(): array
    {
        $landen = [
            'Nederland' => [],
            'België' => [],
        ];
        foreach ($this->all() as $stad) {
            $land = ($stad['country'] ?? 'NL') === 'BE' ? 'België' : 'Nederland';
            $prov = $stad['provincie'];
            $landen[$land][$prov] ??= [];
            $landen[$land][$prov][] = $stad;
        }
        foreach ($landen as $land => $provs) {
            ksort($landen[$land], SORT_NATURAL | SORT_FLAG_CASE);
            if ($landen[$land] === []) {
                unset($landen[$land]);
            }
        }

        return $landen;
    }

    /**
     * @return array{
     *   naam: string,
     *   slug: string,
     *   provincie: string,
     *   lat: float,
     *   lng: float,
     *   straal_km: float,
     *   intro: string,
     *   country: string
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
     * Document-/H1-titel per land (BE noemt zaden expliciet).
     *
     * @param  array{naam: string, country?: string}  $stad
     */
    public function pageTitle(array $stad): string
    {
        $naam = (string) $stad['naam'];
        if (($stad['country'] ?? 'NL') === 'BE') {
            return 'Gratis planten, stekjes en zaden in '.$naam;
        }

        return 'Gratis planten en stekjes in '.$naam;
    }

    /**
     * Nabije steden op haversine-afstand (exclusief huidige), zelfde land.
     *
     * @param  array{lat: float, lng: float, slug: string, country?: string}  $stad
     * @return list<array{naam: string, slug: string, provincie: string, country: string, afstand_km: float}>
     */
    public function nearby(array $stad, int $limit = 6): array
    {
        $lat = (float) $stad['lat'];
        $lng = (float) $stad['lng'];
        $slug = (string) $stad['slug'];
        $country = (string) ($stad['country'] ?? 'NL');
        $rows = [];
        foreach ($this->all() as $other) {
            if ($other['slug'] === $slug) {
                continue;
            }
            if (($other['country'] ?? 'NL') !== $country) {
                continue;
            }
            $km = Geo::haversineKm($lat, $lng, $other['lat'], $other['lng']);
            $rows[] = [
                'naam' => $other['naam'],
                'slug' => $other['slug'],
                'provincie' => $other['provincie'],
                'country' => $other['country'],
                'afstand_km' => round($km, 1),
            ];
        }
        usort($rows, static fn ($a, $b) => $a['afstand_km'] <=> $b['afstand_km']);

        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * FAQ-items voor een stad (FAQPage-schema + UI).
     *
     * @param  array{naam: string, straal_km: float, country?: string, slug?: string}  $stad
     * @return list<array{vraag: string, antwoord: string}>
     */
    public function faqFor(array $stad): array
    {
        $naam = $stad['naam'];
        $straal = (int) round((float) $stad['straal_km']);
        $site = (string) config('site.name');
        $country = (string) ($stad['country'] ?? 'NL');
        $slug = (string) ($stad['slug'] ?? '');

        if ($country === 'BE') {
            return $this->faqForBelgie($naam, $straal, $site, $slug);
        }

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
     * @return list<array{vraag: string, antwoord: string}>
     */
    private function faqForBelgie(string $naam, int $straal, string $site, string $slug): array
    {
        $faq = [
            [
                'vraag' => 'Wat is een zadenbib?',
                'antwoord' => 'Een zadenbib (ook grainothèque) is een openbare plek — vaak in een bibliotheek — waar je gratis zaden van groenten, kruiden of bloemen kunt meenemen en zelf geoogste zaden mag achterlaten. Op '.$site.' toon ik alleen zadenbibs die in mijn openbare lijst staan.',
            ],
            [
                'vraag' => "Hoe ver zoekt deze pagina rond {$naam}?",
                'antwoord' => "Ik toon vaste deelplekken en eventueel aanbod van mensen binnen ongeveer {$straal} km. Kaartpunten zijn benaderd; voor openingsuren en regels kijk je op de bronpagina van elke zadenbib.",
            ],
        ];

        if ($slug === 'lommel') {
            $faq[] = [
                'vraag' => 'Moet ik lid zijn van de bib?',
                'antwoord' => 'Nee. Bij de zadenbibs van Noord-Limburg (Lommel, Pelt en omgeving) mag iedereen meedoen, ook zonder lidmaatschap van de bibliotheek.',
            ];
            $faq[] = [
                'vraag' => 'Hoeveel zakjes mag ik meenemen?',
                'antwoord' => 'Bij de zadenbibs van Noord-Limburg mag je per bezoek maximaal drie zakjes meenemen. Er is geen uitleentermijn en er zijn geen boetes; de bib vraagt wel om zelf geoogste zaden terug te schenken als dat lukt.',
            ];
        } elseif ($slug === 'antwerpen') {
            $faq[] = [
                'vraag' => 'Hoe werkt de zadenbib in Antwerpen?',
                'antwoord' => 'Je neemt gratis zaden mee en schenkt bij voorkeur zelf geoogste zaden terug. De Antwerpse zadenbibs zijn open tijdens de normale openingsuren van de bib, jaarlijks van eind februari/begin maart tot oktober (daarna winterrust).',
            ];
            $faq[] = [
                'vraag' => 'Hoe bied ik zelf iets aan?',
                'antwoord' => 'Voor vaste zadenbibs breng je zaden naar de bib. Voor stekjes of planten van mens tot mens maak je een account op '.$site.', bevestig je e-mail en plaats aanbod met een benaderde locatie.',
            ];
        } elseif ($slug === 'namen') {
            $faq[] = [
                'vraag' => 'Hoe werkt een grainothèque in Namen?',
                'antwoord' => 'In de bibliotheken van Flawinne, Malonne en La Célestine kun je gratis zakjes zaden meenemen tijdens de openingsuren. Gebruikers laten zelf geoogste zaden achter; oude, lokale en reproductieve rassen krijgen voorrang.',
            ];
            $faq[] = [
                'vraag' => 'Hoe bied ik zelf iets aan?',
                'antwoord' => 'Voor de grainothèque lever je zaden in bij een van de drie bibliotheken. Voor stekjes of planten via buren maak je een account op '.$site.' en plaats je aanbod met een benaderde locatie.',
            ];
        } else {
            $faq[] = [
                'vraag' => 'Moet ik lid zijn van de bib?',
                'antwoord' => 'Dat verschilt per zadenbib. Op de bronpagina bij elke plek op deze pagina lees je de lokale regels; ik verzin geen lidmaatschapseisen die niet in die bron staan.',
            ];
            $faq[] = [
                'vraag' => 'Hoe bied ik zelf iets aan?',
                'antwoord' => 'Voor een zadenbib volg je de schenkregels op de bronpagina van die bib. Voor stekjes of planten van mens tot mens maak je een account op '.$site.', bevestig je e-mail en plaats aanbod.',
            ];
        }

        return $faq;
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
            $country = $row['country'] ?? 'NL';
            if (! in_array($country, ['NL', 'BE'], true)) {
                $errors[] = "steden[$i] country moet NL of BE zijn";
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
                // Zelfde bbox als Deelplekken: NL + heel België.
                if ($latF < 49.4 || $latF > 53.7 || $lngF < 2.4 || $lngF > 7.4) {
                    $errors[] = "steden[$i] coördinaten buiten NL+België-bbox";
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
     *   intro: string,
     *   country: string
     * }
     */
    private function normalize(array $row): array
    {
        $country = strtoupper(trim((string) ($row['country'] ?? 'NL')));
        if ($country !== 'BE') {
            $country = 'NL';
        }

        return [
            'naam' => (string) $row['naam'],
            'slug' => (string) $row['slug'],
            'provincie' => (string) $row['provincie'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'straal_km' => (float) $row['straal_km'],
            'intro' => (string) $row['intro'],
            'country' => $country,
        ];
    }
}
