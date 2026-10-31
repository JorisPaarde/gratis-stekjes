<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AanbodSearch;
use App\Services\NuTeStekken;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoekenController extends Controller
{
    public function __invoke(Request $request, NuTeStekken $calendar, AanbodSearch $search): View
    {
        $plant = trim((string) $request->query('plant', ''));
        if (mb_strlen($plant) > 80) {
            $plant = mb_substr($plant, 0, 80);
        }

        $plantItems = [];
        if ($plant !== '') {
            $plantItems = $search->search(null, null, false, $plant)['items'];
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
            'plantItems' => $plantItems,
        ]);
    }
}
