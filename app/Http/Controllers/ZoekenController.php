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

        $canonicalQuery = array_filter(['plant' => $plant !== '' ? $plant : null]);

        return view('pages.aanbod', [
            'title' => $plant !== '' ? 'Aanbod: '.$plant : 'Aanbod',
            'metaDescription' => $plant !== ''
                ? 'Gratis '.$plant.'-stekjes en planten in Nederland. Zoek op postcode of plaats.'
                : 'Zoek gratis planten en stekjes op postcode of plaats. Benaderde plekken op de kaart.',
            'canonical' => route('aanbod', $canonicalQuery),
            'nuTeStekken' => $calendar->blockForViews(),
            'plant' => $plant,
            'plantItems' => $plantItems,
        ]);
    }
}
