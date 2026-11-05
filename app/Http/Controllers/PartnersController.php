<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vereniging;
use App\Services\Evenementen;
use App\Services\InitiatiefPaginas;
use App\Services\Organisaties;
use App\Services\UitgelichteInitiatieven;
use App\Services\VerenigingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnersController extends Controller
{
    public function __invoke(
        Request $request,
        Evenementen $evenementen,
        InitiatiefPaginas $initiatiefPaginas,
        Organisaties $organisaties,
        UitgelichteInitiatieven $uitgelicht,
    ): View|RedirectResponse {
        // Agendafilters wonen sinds 0.73.0 op /agenda.
        if ($request->query('type') !== null || $request->query('provincie') !== null) {
            return redirect()->to(route('agenda', array_filter($request->only(['type', 'provincie']))), 301);
        }

        $site = config('site.name');
        $volgende = $evenementen->splitAndGroup($evenementen->queryVisible(['type' => '', 'provincie' => '']))['upcoming']
            ->flatten(1)
            ->take(3);

        // Gids: alle organisaties uit data/organisaties.json, filterbaar op soort.
        $soortLabels = ['initiatief' => 'Initiatief'] + (array) config('stekjes.deelplek_types', []);
        $ankers = $uitgelicht->ankers();
        $gids = [];
        foreach ($organisaties->all() as $org) {
            $init = $initiatiefPaginas->findByOrganisatieSlug((string) $org['slug']);
            if ($init !== null && in_array($init->initiatief_slug, $ankers, true)) {
                continue; // staat al bij «Doe mee»
            }
            $org['url'] = $initiatiefPaginas->publiekeUrlVoorOrganisatie((string) $org['slug']);
            $gids[] = $org;
        }
        $soortenAanwezig = array_values(array_unique(array_column($gids, 'type')));
        $soorten = array_intersect_key($soortLabels, array_flip($soortenAanwezig));
        $soort = (string) $request->query('soort', '');
        if (! array_key_exists($soort, $soorten)) {
            $soort = '';
        }
        $tellingen = array_count_values(array_column($gids, 'type'));
        if ($soort !== '') {
            $gids = array_values(array_filter($gids, static fn (array $o): bool => $o['type'] === $soort));
        }
        usort($gids, static fn (array $a, array $b): int => strcasecmp((string) $a['naam'], (string) $b['naam']));

        $verenigingen = VerenigingService::beschikbaar()
            ? Vereniging::query()->approved()->whereNull('json_slug')
                ->withCount(['leden' => fn ($q) => $q->members()])
                ->orderBy('plaats')->orderBy('naam')->get()
            : collect();

        $title = 'Initiatieven: meedoen met plantenacties en ruilinitiatieven';
        $metaDescription = 'Initiatieven en acties rond gratis planten om aan mee te doen, plus een gids met organisaties: plantenasiels, zadenbibliotheken, voedselbossen en meer.';
        $h1 = 'Initiatieven om aan mee te doen';

        return view('pages.partners', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => route('partners'),
            'h1' => $h1,
            'typeLabels' => $evenementen->typeLabels(),
            'volgende' => $volgende,
            'evenementen' => $evenementen,
            'gids' => $gids,
            'soorten' => $soorten,
            'soort' => $soort,
            'tellingen' => $tellingen,
            'soortLabels' => $soortLabels,
            'verenigingen' => $verenigingen,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $h1,
                'url' => route('partners'),
                'description' => $metaDescription,
                'inLanguage' => 'nl-NL',
                'isPartOf' => ['@type' => 'WebSite', 'name' => $site, 'url' => url('/')],
            ],
        ]);
    }
}
