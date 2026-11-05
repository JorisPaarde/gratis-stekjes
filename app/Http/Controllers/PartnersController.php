<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Evenementen;
use App\Services\InitiatiefPaginas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnersController extends Controller
{
    public function __invoke(Request $request, Evenementen $evenementen, InitiatiefPaginas $initiatiefPaginas): View|RedirectResponse
    {
        // Agendafilters wonen sinds 0.73.0 op /agenda.
        if ($request->query('type') !== null || $request->query('provincie') !== null) {
            return redirect()->to(route('agenda', array_filter($request->only(['type', 'provincie']))), 301);
        }

        $site = config('site.name');
        $volgende = $evenementen->splitAndGroup($evenementen->queryVisible(['type' => '', 'provincie' => '']))['upcoming']
            ->flatten(1)
            ->take(3);

        $title = 'Initiatieven: meedoen met plantenacties en ruilinitiatieven';
        $metaDescription = 'Initiatieven en acties rond gratis planten om aan mee te doen, vaste ruilinitiatieven en de eerstvolgende stekjesmarkten.';
        $h1 = 'Initiatieven om aan mee te doen';

        return view('pages.partners', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => route('partners'),
            'h1' => $h1,
            'typeLabels' => $evenementen->typeLabels(),
            'volgende' => $volgende,
            'evenementen' => $evenementen,
            'organisatieInitiatieven' => $initiatiefPaginas->metPagina(),
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
