<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Evenementen;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
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
        $filters = ['type' => $type, 'provincie' => $provincie];

        $split = $evenementen->splitAndGroup($evenementen->queryVisible($filters));
        $upcomingFlat = $split['upcoming']->flatten(1);

        $h1 = 'Agenda: stekjesmarkten en plantenruilbeurzen';
        $title = 'Agenda stekjesmarkten, plantenruilbeurzen en ruildagen';
        $metaDescription = 'Agenda met stekjesmarkten, plantenruilbeurzen en ruildagen in Nederland en België. Filter op provincie of type.';
        $canonical = route('agenda', array_filter(['type' => $type ?: null, 'provincie' => $provincie ?: null]));

        $graph = [[
            '@type' => 'CollectionPage',
            'name' => $h1,
            'url' => route('agenda'),
            'description' => $metaDescription,
            'inLanguage' => 'nl-NL',
            'isPartOf' => ['@type' => 'WebSite', 'name' => $site, 'url' => url('/')],
        ]];
        foreach ($evenementen->schemaEvents($upcomingFlat) as $eventSchema) {
            $graph[] = $eventSchema;
        }

        return view('pages.agenda', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonical' => $canonical,
            'h1' => $h1,
            'typeLabels' => $evenementen->typeLabels(),
            'provincies' => $evenementen->provincies(),
            'filters' => $filters,
            'upcomingByMonth' => $split['upcoming'],
            'pastEvents' => $split['past'],
            'hasUpcoming' => $upcomingFlat->isNotEmpty(),
            'hasPast' => $split['past']->isNotEmpty(),
            'evenementen' => $evenementen,
            'schema' => ['@context' => 'https://schema.org', '@graph' => $graph],
        ]);
    }
}
