<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PostalCode;

/**
 * Herkent in één zoekveld (hero) of er een plaats/postcode, een plant of allebei is ingevuld,
 * zoals «Raalte», «8102», «monstera», «gatenplant» of «monstera haarlem».
 *
 * Volgorde (eerst lokaal, dan pas netwerk):
 *  1. postcode → plaats
 *  2. bekende stad (steden.json) → plaats
 *  3. catalogusnaam, volksnaam of Latijnse naam (plantensoorten.json) → plant
 *  4. woord uit publiek aanbod (bv. «vetplant») → plant
 *  5. combinatie: plant + plaats in willekeurige volgorde («monstera haarlem», «haarlem monstera»)
 *  6. rest: geocoder (PDOK/Nominatim, gecachet) → plaats; anders onbekend, met suggesties
 */
final class ZoekIntent
{
    public const LEEG = 'leeg';

    public const PLAATS = 'plaats';

    public const PLANT = 'plant';

    public const PLANT_PLAATS = 'plant_plaats';

    public const ONBEKEND = 'onbekend';

    /** Verbindingswoorden tussen plant en plaats («monstera in haarlem»). */
    private const VERBINDERS = ['in', 'bij', 'te', 'nabij', 'rond', 'uit', 'om'];

    private const MAX_WOORDEN = 6;

    public function __construct(
        private readonly Plantensoorten $plantensoorten,
        private readonly AanbodSearch $aanbod,
        private readonly Steden $steden,
        private readonly LocationGeocoder $geocoder,
    ) {}

    /**
     * @param  bool  $allowNetwork  false = geen geocoder-aanroep; de rest gaat als plaats door
     * @return array{soort: string, q: ?string, plant: ?string, tekst: string, suggesties: list<string>}
     */
    public function bepaal(string $invoer, bool $allowNetwork = true): array
    {
        $tekst = trim((string) preg_replace('/\s+/u', ' ', $invoer));
        if (mb_strlen($tekst) > 120) {
            $tekst = trim(mb_substr($tekst, 0, 120));
        }
        if ($tekst === '') {
            return $this->uitkomst(self::LEEG, '');
        }

        if ($this->isPostcode($tekst) || $this->isStad($tekst)) {
            return $this->uitkomst(self::PLAATS, $tekst, q: $tekst);
        }
        if ($this->isCatalogusPlant($tekst)) {
            return $this->uitkomst(self::PLANT, $tekst, plant: $tekst);
        }
        if ($this->isAanbodPlant($tekst)) {
            return $this->uitkomst(self::PLANT, $tekst, plant: $tekst);
        }

        $woorden = $this->woorden($tekst);
        if (count($woorden) >= 2) {
            foreach ([true, false] as $metAanbod) {
                $split = $this->splits($woorden, $metAanbod);
                if ($split !== null) {
                    return $this->uitkomst(self::PLANT_PLAATS, $tekst, q: $split['plaats'], plant: $split['plant']);
                }
            }
        }

        if (! $allowNetwork) {
            return $this->uitkomst(self::PLAATS, $tekst, q: $tekst);
        }

        // Dichtbij een plantnaam? Dan mag een zwak geocoder-resultaat («monstra» → Meise) niet winnen.
        $suggesties = array_map(
            static fn (array $s): string => $s['naam'],
            $this->plantensoorten->suggestClose($tekst, 3),
        );

        $gevonden = $this->geocoder->resolve($tekst, null, true);
        if (($gevonden['ok'] ?? false) && ($suggesties === [] || $this->lijktOpPlaats($tekst, $gevonden))) {
            return $this->uitkomst(self::PLAATS, $tekst, q: $tekst);
        }

        return $this->uitkomst(self::ONBEKEND, $tekst, suggesties: $suggesties);
    }

    /**
     * @param  list<string>  $woorden  zonder verbindingswoorden
     * @return array{plant: string, plaats: string}|null
     */
    private function splits(array $woorden, bool $alleenCatalogus): ?array
    {
        $n = count($woorden);
        // Plant voorop («monstera haarlem»), dan plaats voorop («haarlem monstera»).
        // Langste plantdeel eerst: «gouden regen haarlem» is niet «gouden» + «regen haarlem».
        for ($k = $n - 1; $k >= 1; $k--) {
            $plant = implode(' ', array_slice($woorden, 0, $k));
            $plaats = implode(' ', array_slice($woorden, $k));
            if ($this->isPlantDeel($plant, $alleenCatalogus)) {
                return ['plant' => $plant, 'plaats' => $plaats];
            }
        }
        for ($k = 1; $k <= $n - 1; $k++) {
            $plaats = implode(' ', array_slice($woorden, 0, $k));
            $plant = implode(' ', array_slice($woorden, $k));
            if ($this->isPlantDeel($plant, $alleenCatalogus)) {
                return ['plant' => $plant, 'plaats' => $plaats];
            }
        }

        return null;
    }

    private function isPlantDeel(string $deel, bool $alleenCatalogus): bool
    {
        if ($this->isPostcode($deel) || $this->isStad($deel)) {
            return false;
        }

        return $this->isCatalogusPlant($deel) || (! $alleenCatalogus && $this->isAanbodPlant($deel));
    }

    /**
     * Komt het gevonden label overeen met wat er getypt is? PDOK/Nominatim zijn tolerant
     * en geven bij een typfout soms een willekeurige plaats terug.
     *
     * @param  array<string, mixed>  $gevonden
     */
    private function lijktOpPlaats(string $tekst, array $gevonden): bool
    {
        $label = $this->plantensoorten->normalize(trim(($gevonden['label'] ?? '').' '.($gevonden['woonplaats'] ?? '')));
        if ($label === '') {
            return false;
        }
        $labelWoorden = explode(' ', $label);
        foreach (explode(' ', $this->plantensoorten->normalize($tekst)) as $woord) {
            if (mb_strlen($woord) < 3) {
                continue;
            }
            if (str_contains($label, $woord)) {
                return true;
            }
            foreach ($labelWoorden as $labelWoord) {
                if ($labelWoord !== '' && levenshtein($woord, $labelWoord) <= 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isCatalogusPlant(string $tekst): bool
    {
        return $this->plantensoorten->resolveExact($tekst) !== null;
    }

    private function isAanbodPlant(string $tekst): bool
    {
        return mb_strlen($tekst) >= 3 && $this->aanbod->heeftAanbodMet($tekst);
    }

    private function isPostcode(string $tekst): bool
    {
        return PostalCode::looksLikePostcode($tekst, 'NL');
    }

    private function isStad(string $tekst): bool
    {
        $norm = $this->plantensoorten->normalize($tekst);
        if ($norm === '') {
            return false;
        }
        foreach ($this->steden->all() as $stad) {
            if ($this->plantensoorten->normalize($stad['naam']) === $norm) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function woorden(string $tekst): array
    {
        $delen = preg_split('/[\s,]+/u', $tekst, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $delen = array_values(array_filter(
            $delen,
            static fn (string $w): bool => ! in_array(mb_strtolower($w, 'UTF-8'), self::VERBINDERS, true),
        ));

        return array_slice($delen, 0, self::MAX_WOORDEN);
    }

    /**
     * @param  list<string>  $suggesties
     * @return array{soort: string, q: ?string, plant: ?string, tekst: string, suggesties: list<string>}
     */
    private function uitkomst(string $soort, string $tekst, ?string $q = null, ?string $plant = null, array $suggesties = []): array
    {
        return ['soort' => $soort, 'q' => $q, 'plant' => $plant, 'tekst' => $tekst, 'suggesties' => $suggesties];
    }
}
