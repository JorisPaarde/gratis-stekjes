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

        $aantal = count($soorten);

        return view('pages.invasieve-soorten', [
            'title' => 'Invasieve exoten: '.$aantal.' planten die je niet deelt',
            'heading' => 'Invasieve exoten: deze planten deel je niet',
            'metaDescription' => 'Invasieve exoten van de EU-Unielijst mag je niet aanbieden op '.$site.'. Bekijk de '.$aantal.' soorten en waarom.',
            'canonical' => route('invasieve-soorten'),
            'soorten' => $soorten,
            'meta' => $meta,
            'aantal' => $aantal,
        ]);
    }
}
