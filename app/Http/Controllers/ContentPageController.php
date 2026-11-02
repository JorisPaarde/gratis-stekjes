<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentPageController extends Controller
{
    public function hoeWerktHet(): View
    {
        return view('pages.hoe-werkt-het', [
            'title' => 'Hoe werkt gratis planten delen',
            'metaDescription' => 'Zo deel je gratis planten, stekjes en zaden: je haalt ze zelf op en de enige tegenprestatie is een praatje. Geen verkoop.',
            'canonical' => route('hoe-werkt-het'),
        ]);
    }

    public function hulpmiddelen(): View
    {
        return view('pages.hulpmiddelen', [
            'title' => 'Wilgenwater en hulpmiddelen voor stekjes',
            'metaDescription' => 'Wilgenwater, stekpoeder, potten, stekgrond en gereedschap: wat je nodig hebt om stekjes te laten wortelen, en wat je zelf kunt maken.',
            'canonical' => route('hulpmiddelen'),
        ]);
    }

    public function over(): View
    {
        return view('pages.over', [
            'title' => 'Wie zit er achter '.config('site.name'),
            'metaDescription' => 'Ik ben '.config('site.maker').', vader van vier, volkstuinder en webbouwer. Waarom ik '.config('site.name').' gratis maak, en hoe je samen met mij meedoet.',
            'canonical' => route('over'),
        ]);
    }

    public function doeMee(): RedirectResponse
    {
        return redirect()->route('account.registreren');
    }
}
