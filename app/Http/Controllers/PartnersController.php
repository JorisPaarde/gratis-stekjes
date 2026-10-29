<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class PartnersController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.partners', [
            'title' => 'Partners',
            'metaDescription' => 'Initiatieven waar '.config('site.name').' naast staat — voedselbossen, bomen en meer.',
            'canonical' => route('partners'),
        ]);
    }
}
