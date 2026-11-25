<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AanbodSearch;
use App\Services\NuTeStekken;
use App\Services\Plantensoorten;
use App\Services\Stektips;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoekenController extends Controller
{
    public function __invoke(Request $request, NuTeStekken $calendar, AanbodSearch $search, Stektips $stektips, Plantensoorten $plantensoorten): View
    {
        $plant = trim((string) $request->query('plant', ''));
        if (mb_strlen($plant) > 80) {
            $plant = mb_substr($plant, 0, 80);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > 120) {
            $q = mb_substr($q, 0, 120);
        }

        $plantItems = [];
        $plantSortering = null;
        if ($plant !== '' && $q === '') {
            // Alleen het 2e veld: dichtstbij het eigen adres van het ingelogde lid, anders nieuwste eerst.
            $user = $request->user();
            $plantSortering = AanbodSearch::plantSortering($user);
            $plantItems = $search->zoekOpPlant($plant, null, AanbodSearch::accountOrigin($user));
        } elseif ($plant !== '') {
            $plantItems = $search->search(null, null, false, $plant)['items'];
        }

        // Zoekmeting alleen via /api/zoeken (productkeuze A: API-only).

        $deelwoord = $plant !== ''
            ? $this->aanbodZelfstandigNaamVoorPlant($plant, $stektips, $plantensoorten)
            : 'stekjes';
        $title = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord
            : 'Aanbod: gratis planten en stekjes ophalen';
        $heading = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord
            : 'Gratis planten en stekjes ophalen';
        $metaDescription = $plant !== ''
            ? 'Gratis '.$plant.'-'.$deelwoord.' in Nederland en België. Zoek op postcode of plaats en haal ze op met een praatje.'
            : 'Gratis planten en stekjes ophalen: zoek op postcode of plaats, bekijk de kaart en haal ze zelf op. De enige tegenprestatie is een praatje.';

        return view('pages.aanbod', [
            'title' => $title,
            'heading' => $heading,
            'metaDescription' => $metaDescription,
            // Filter-URL's (?plant=) canonicaliseren naar de kale aanbodpagina.
            'canonical' => route('aanbod'),
            'nuTeStekken' => $calendar->blockForViews(),
            'plant' => $plant,
            'q' => $q,
            'plantItems' => $plantItems,
            'plantSortering' => $plantSortering,
        ]);
    }

    /**
     * «stekjes» alleen als de gezochte plant een stektip heeft waarin stekken een methode is.
     * Anders «planten»: /aanbod filtert alleen op plantnaam, niet op type (stekjes/zaden).
     */
    private function aanbodZelfstandigNaamVoorPlant(string $plant, Stektips $stektips, Plantensoorten $plantensoorten): string
    {
        $catalogus = $plantensoorten->resolveExact($plant);
        if (is_array($catalogus) && ! empty($catalogus['slug'])) {
            $soort = $stektips->find((string) $catalogus['slug']);
            if ($soort !== null) {
                return $stektips->aanbodZelfstandigNaam($soort);
            }
        }

        $norm = mb_strtolower($plant, 'UTF-8');
        foreach ($stektips->all() as $soort) {
            $naam = mb_strtolower((string) ($soort['naam'] ?? ''), 'UTF-8');
            $seo = mb_strtolower(trim((string) ($soort['seo_naam'] ?? '')), 'UTF-8');
            if ($naam === $norm || ($seo !== '' && $seo === $norm)) {
                return $stektips->aanbodZelfstandigNaam($soort);
            }
        }

        return 'planten';
    }
}
