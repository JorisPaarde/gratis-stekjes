<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deelplek;
use App\Services\Deelplekken;
use App\Services\Organisaties;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OrganisatieController extends Controller
{
    public function index(Organisaties $organisaties): View
    {
        return view('pages.organisaties-index', [
            'title' => 'Organisaties',
            'metaDescription' => 'Organisaties en initiatieven rond gratis planten delen — met link naar hun eigen site.',
            'canonical' => route('organisaties.index'),
            'organisaties' => $organisaties->all(),
        ]);
    }

    public function show(string $slug, Organisaties $organisaties, Deelplekken $deelplekken): View|Response
    {
        $org = $organisaties->find($slug);
        if ($org === null) {
            abort(404);
        }

        $deelplekSlugs = is_array($org['deelplek_slugs'] ?? null) ? $org['deelplek_slugs'] : [];
        $deelplekkenRows = $deelplekSlugs === []
            ? collect()
            : collect(Deelplek::query()
                ->visible()
                ->whereIn('slug', $deelplekSlugs)
                ->orderBy('plaats')
                ->orderBy('naam')
                ->get());

        $naam = (string) $org['naam'];
        $kort = (string) ($org['kort'] ?? '');
        $meta = (string) ($org['meta_description'] ?? '');
        if ($meta === '') {
            $meta = $kort !== '' ? $kort : $naam.' — organisatie rond gratis planten delen.';
        }

        return view('pages.organisatie-show', [
            'title' => $naam,
            'metaDescription' => $meta,
            'canonical' => route('organisaties.show', ['slug' => $slug]),
            'org' => $org,
            'deelplekken' => $deelplekkenRows,
            'typeLabels' => $deelplekken->typeLabels(),
        ]);
    }
}
