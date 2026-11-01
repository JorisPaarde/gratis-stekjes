<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deelplek;
use App\Models\Vereniging;
use App\Services\AanbodSearch;
use App\Services\Deelplekken;
use App\Services\Organisaties;
use App\Services\VerenigingService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OrganisatieController extends Controller
{
    public function index(Organisaties $organisaties): View
    {
        $verenigingen = VerenigingService::beschikbaar()
            ? Vereniging::query()->approved()->whereNull('json_slug')->withCount('leden')->orderBy('plaats')->orderBy('naam')->get()
            : collect();

        return view('pages.organisaties-index', [
            'verenigingen' => $verenigingen,
            'title' => 'Organisaties',
            'metaDescription' => 'Organisaties en initiatieven rond het delen van gratis planten, met een link naar hun eigen site. Geen officiële partnerschappen.',
            'canonical' => route('organisaties.index'),
            'organisaties' => $organisaties->all(),
        ]);
    }

    public function show(string $slug, Organisaties $organisaties, Deelplekken $deelplekken, VerenigingService $verenigingen, AanbodSearch $search): View|Response
    {
        $org = $organisaties->find($slug);
        $vereniging = $verenigingen->publiek($slug);
        if ($org === null) {
            if ($vereniging === null || $vereniging->json_slug !== null) {
                abort(404);
            }

            return view('pages.vereniging-show', [
                'title' => $vereniging->naam.' in '.$vereniging->plaats,
                'metaDescription' => 'Gratis planten, stekjes en zaden van leden van '.$vereniging->naam.' ('.$vereniging->plaats.'). Zelf ophalen, met een praatje erbij.',
                'canonical' => $vereniging->url(),
                'vereniging' => $vereniging,
                'blok' => $verenigingen->ledenBlok($vereniging, $search),
            ]);
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
            'vereniging' => $vereniging,
            'blok' => $vereniging !== null ? $verenigingen->ledenBlok($vereniging, $search) : null,
        ]);
    }

    /** Printbare poster met QR-code naar de pagina van de vereniging of organisatie. */
    public function poster(string $slug, Organisaties $organisaties, VerenigingService $verenigingen): View
    {
        $org = $organisaties->find($slug);
        $vereniging = $verenigingen->publiek($slug);
        if ($org === null && $vereniging === null) {
            abort(404);
        }
        $naam = $org !== null ? (string) $org['naam'] : $vereniging->naam;

        return view('pages.vereniging-poster', [
            'title' => 'Poster '.$naam,
            'metaDescription' => 'Poster met QR-code voor '.$naam.'.',
            'canonical' => route('organisaties.show', ['slug' => $slug]),
            'naam' => $naam,
            'url' => route('organisaties.show', ['slug' => $slug]),
        ]);
    }
}
