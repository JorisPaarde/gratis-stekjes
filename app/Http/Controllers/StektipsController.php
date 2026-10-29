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
        return view('pages.stektips-index', [
            'title' => 'Stektips',
            'metaDescription' => 'Praktische stektips per soort — gratis planten delen.',
            'canonical' => route('stektips.index'),
            'soorten' => $stektips->all(),
        ]);
    }

    public function show(string $slug, Stektips $stektips): View|Response
    {
        $soort = $stektips->find($slug);
        if ($soort === null) {
            abort(404);
        }

        return view('pages.stektips-show', [
            'title' => $soort['naam'],
            'metaDescription' => ($soort['kort'] ?? $soort['naam']).' — stektip op '.config('site.name').'.',
            'canonical' => route('stektips.show', ['slug' => $slug]),
            'soort' => $soort,
        ]);
    }
}
