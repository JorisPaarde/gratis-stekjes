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
            'metaDescription' => 'Organisaties en initiatieven rond het delen van gratis planten, met een link naar hun eigen site. Geen officiële partnerschappen.',
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

        $gerelateerd = [];
        foreach ($org['gerelateerde_slugs'] ?? [] as $relatedSlug) {
            if (! is_string($relatedSlug) || $relatedSlug === '' || $relatedSlug === $slug) {
                continue;
            }
            $related = $organisaties->find($relatedSlug);
            if ($related !== null) {
                $gerelateerd[] = $related;
            }
        }

        $extraLinks = [];
        foreach ($org['links'] ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($label === '' || $url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $extraLinks[] = ['label' => $label, 'url' => $url];
        }

        $naam = (string) $org['naam'];
        $kort = (string) ($org['kort'] ?? '');
        $meta = (string) ($org['meta_description'] ?? '');
        if ($meta === '') {
            $meta = $kort !== '' ? $kort : $naam.': organisatie rond gratis planten delen.';
        }

        return view('pages.organisatie-show', [
            'title' => $naam,
            'metaDescription' => $meta,
            'canonical' => route('organisaties.show', ['slug' => $slug]),
            'org' => $org,
            'deelplekken' => $deelplekkenRows,
            'gerelateerd' => $gerelateerd,
            'extraLinks' => $extraLinks,
            'typeLabels' => $deelplekken->typeLabels(),
        ]);
    }
}
