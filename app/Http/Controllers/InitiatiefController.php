<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganisatieInitiatief;
use App\Services\Evenementen;
use App\Services\InitiatiefPaginas;
use App\Services\Organisaties;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class InitiatiefController extends Controller
{
    public function show(
        string $slug,
        InitiatiefPaginas $initiatieven,
        Evenementen $evenementen,
        Organisaties $organisaties,
    ): View|Response {
        $init = $initiatieven->findByInitiatiefSlug($slug);
        if ($init === null) {
            abort(404);
        }

        return $this->render($init, $evenementen, $organisaties);
    }

    /** Vaste route /struikroven (zelfde content als catch-all). */
    public function struikroven(
        InitiatiefPaginas $initiatieven,
        Evenementen $evenementen,
        Organisaties $organisaties,
    ): View|Response {
        $init = $initiatieven->findByInitiatiefSlug('struikroven');
        if ($init === null) {
            abort(404);
        }

        return $this->render($init, $evenementen, $organisaties);
    }

    private function render(
        OrganisatieInitiatief $init,
        Evenementen $evenementen,
        Organisaties $organisaties,
    ): View {
        $org = $organisaties->find($init->organisatie_slug);
        $acties = $evenementen->upcomingForOrganisatie($init->organisatie_slug);
        $title = $init->pagina_titel !== '' ? $init->pagina_titel : (string) ($org['naam'] ?? $init->korte_naam);
        $meta = $init->meta_description !== ''
            ? $init->meta_description
            : $title.'. Via '.config('site.name').' deel je planten, stekjes en zaden gratis met iemand in de buurt.';
        $canonical = route('initiatief.show', ['slug' => $init->initiatief_slug]);
        // Behoud named route('struikroven') canonical voor Struikroven (0.64.1-pariteit).
        if ($init->initiatief_slug === 'struikroven') {
            $canonical = route('struikroven');
        }

        return view('pages.initiatief', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
            'init' => $init,
            'org' => $org,
            'acties' => $acties,
            'evenementen' => $evenementen,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'url' => $canonical,
                'description' => $meta,
                'inLanguage' => 'nl-NL',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => config('site.name'),
                    'url' => url('/'),
                ],
            ],
            'today' => Carbon::now('Europe/Amsterdam')->startOfDay(),
        ]);
    }
}
