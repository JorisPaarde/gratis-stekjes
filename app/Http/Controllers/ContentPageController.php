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
            'title' => 'Hoe werkt het',
            'metaDescription' => 'Spelregels: gratis, fysiek ophalen, een praatje als enige tegenprestatie.',
            'canonical' => route('hoe-werkt-het'),
        ]);
    }

    public function hulpmiddelen(): View
    {
        return view('pages.hulpmiddelen', [
            'title' => 'Hulpmiddelen',
            'metaDescription' => 'Wilgenwater, stekpoeder, potten en substraat — praktisch voor stekken.',
            'canonical' => route('hulpmiddelen'),
        ]);
    }

    public function over(): View
    {
        return view('pages.over', [
            'title' => 'Over',
            'metaDescription' => 'Over '.config('site.name').' — gratis stekjes met een praatje.',
            'canonical' => route('over'),
        ]);
    }

    public function doeMee(): RedirectResponse
    {
        return redirect()->route('account.registreren');
    }
}
