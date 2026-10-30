<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InvasieveSoorten;
use Illuminate\View\View;

class InvasieveSoortenController extends Controller
{
    public function __invoke(InvasieveSoorten $invasieve): View
    {
        $site = config('site.name');
        $soorten = $invasieve->all();
        $meta = $invasieve->meta() ?? [];

        return view('pages.invasieve-soorten', [
            'title' => 'Invasieve soorten',
            'metaDescription' => 'Waarom invasieve planten niet op '.$site.' mogen, en welke soorten op de EU-Unielijst staan.',
            'canonical' => route('invasieve-soorten'),
            'soorten' => $soorten,
            'meta' => $meta,
            'aantal' => count($soorten),
        ]);
    }
}
