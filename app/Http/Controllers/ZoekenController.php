<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NuTeStekken;
use Illuminate\View\View;

class ZoekenController extends Controller
{
    public function __invoke(NuTeStekken $calendar): View
    {
        return view('pages.aanbod', [
            'title' => 'Aanbod',
            'metaDescription' => 'Zoek gratis planten en stekjes op postcode of plaats. Benaderde plekken op de kaart.',
            'canonical' => route('aanbod'),
            'nuTeStekken' => $calendar->blockForViews(),
        ]);
    }
}
