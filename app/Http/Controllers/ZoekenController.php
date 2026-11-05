<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AanbodSearch;
use App\Services\ConversionTracker;
use App\Services\NuTeStekken;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoekenController extends Controller
{
    public function __invoke(
        Request $request,
        NuTeStekken $calendar,
        AanbodSearch $search,
        ConversionTracker $conversions,
    ): View {
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

        // SSR alleen-plant SEO-URL’s (?plant=… zonder q): één zoekevent.
        // Plant+q of alleen-q: geocode/resultaat via /api/zoeken — niet hier loggen.
        if ($plant !== '' && $q === '') {
            $conversions->recordAanbodSearch(
                $request,
                '',
                $plant,
                null,
                count($plantItems),
                false,
            );
        }

        $title = $plant !== ''
            ? 'Gratis '.$plant.'-stekjes'
            : 'Aanbod: gratis planten en stekjes ophalen';
        $heading = $plant !== ''
            ? 'Gratis '.$plant.'-stekjes'
            : 'Gratis planten en stekjes ophalen';
        $metaDescription = $plant !== ''
            ? 'Gratis '.$plant.'-stekjes en planten in Nederland en België. Zoek op postcode of plaats en haal ze op met een praatje.'
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
}
