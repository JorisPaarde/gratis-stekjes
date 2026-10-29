<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Stektips;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StektipsController extends Controller
{
    public function index(Stektips $stektips): View
    {
        $soorten = $stektips->all();
        $count = count($soorten);

        return view('pages.stektips-index', [
            'title' => 'Stektips',
            'metaDescription' => 'Stektips per soort — eetbaar, kruiden, sier- en kamerplanten gratis delen.',
            'canonical' => route('stektips.index'),
            'soorten' => $soorten,
            'soortenCount' => $count,
        ]);
    }

    public function show(string $slug, Stektips $stektips): View|Response
    {
        $soort = $stektips->find($slug);
        if ($soort === null) {
            abort(404);
        }

        $naam = (string) ($soort['naam'] ?? $slug);
        $latijn = (string) ($soort['latijn'] ?? '');
        $kort = (string) ($soort['kort'] ?? '');
        $metaLatijn = ($latijn !== '' && $latijn !== 'Volgt binnenkort') ? " ({$latijn})" : '';
        $metaKort = $kort !== '' ? $kort.'. ' : '';

        return view('pages.stektips-show', [
            'title' => $naam.' stekken en gratis delen',
            'metaDescription' => $naam.$metaLatijn.' stekken en gratis delen. '.$metaKort.'Methode, wanneer, moeilijkheid, licht en water — zoek aanbod op '.config('site.name').'.',
            'canonical' => route('stektips.show', ['slug' => $slug]),
            'soort' => $soort,
            'verwanten' => $stektips->related($soort),
            'aanbodUrl' => route('aanbod'),
        ]);
    }
}
