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
            'title' => 'Hoe gratis planten delen',
            'metaDescription' => 'Zo deel je gratis planten en stekjes: fysiek ophalen, een praatje als enige tegenprestatie. Geen verkoop.',
            'canonical' => route('hoe-werkt-het'),
        ]);
    }

    public function hulpmiddelen(): View
    {
        return view('pages.hulpmiddelen', [
            'title' => 'Wilgenwater & stekhulpmiddelen',
            'metaDescription' => 'Wilgenwater, stekpoeder, potten en substraat: hulpmiddelen om stekjes te laten wortelen.',
            'canonical' => route('hulpmiddelen'),
        ]);
    }

    public function over(): View
    {
        return view('pages.over', [
            'title' => 'Over gratis planten delen',
            'metaDescription' => 'Waarom '.config('site.name').' bestaat: gratis stekjes delen met een praatje, door heel Nederland en België.',
            'canonical' => route('over'),
        ]);
    }

    public function doeMee(): RedirectResponse
    {
        return redirect()->route('account.registreren');
    }
}
