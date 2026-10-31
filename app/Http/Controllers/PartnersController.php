<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Evenementen;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnersController extends Controller
{
    public function __invoke(Request $request, Evenementen $evenementen): View
    {
        $site = config('site.name');
        $type = trim((string) $request->query('type', ''));
        $provincie = trim((string) $request->query('provincie', ''));

        if ($type !== '' && ! $evenementen->typeValid($type)) {
            $type = '';
        }
        if ($provincie !== '' && ! $evenementen->provincieValid($provincie)) {
            $provincie = '';
        }

        $filters = [
            'type' => $type,
            'provincie' => $provincie,
        ];

        $items = $evenementen->queryVisible($filters);
        $split = $evenementen->splitAndGroup($items);
        $upcomingFlat = $split['upcoming']->flatten(1);

        $title = 'Stekjesmarkt, plantenruilbeurs en ruilinitiatieven';
        $metaDescription = 'Agenda met stekjesmarkten, plantenruilbeurzen en ruildagen, plus vaste ruilinitiatieven waar '.$site.' naar linkt.';
        $h1 = 'Plantenruilbeurzen, stekjesmarkten en initiatieven';

        $graph = [
            [
                '@type' => 'CollectionPage',
                'name' => $h1,
                'url' => route('partners'),
                'description' => $metaDescription,
                'inLanguage' => 'nl-NL',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ],
        ];
        foreach ($evenementen->schemaEvents($upcomingFlat) as $eventSchema) {
            $graph[] = $eventSchema;
        }

        return view('pages.partners', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => route('partners', array_filter([
                'type' => $type !== '' ? $type : null,
                'provincie' => $provincie !== '' ? $provincie : null,
            ])),
            'h1' => $h1,
            'typeLabels' => $evenementen->typeLabels(),
            'provincies' => $evenementen->provincies(),
            'filters' => $filters,
            'upcomingByMonth' => $split['upcoming'],
            'pastEvents' => $split['past'],
            'hasUpcoming' => $upcomingFlat->isNotEmpty(),
            'hasPast' => $split['past']->isNotEmpty(),
            'evenementen' => $evenementen,
            'schema' => [
                '@context' => 'https://schema.org',
                '@graph' => $graph,
            ],
        ]);
    }
}
