<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Deelplekken;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeelplekkenController extends Controller
{
    public function __invoke(Request $request, Deelplekken $deelplekken): View
    {
        $type = trim((string) $request->query('type', ''));
        $provincie = trim((string) $request->query('provincie', ''));
        $plaats = trim((string) $request->query('plaats', ''));

        if ($type !== '' && ! $deelplekken->typeValid($type)) {
            $type = '';
        }

        $items = $deelplekken->queryVisible([
            'type' => $type,
            'provincie' => $provincie,
            'plaats' => $plaats,
        ]);

        $site = config('site.name');
        $title = 'Plantenasiel, plantenbieb en ruilkastje in je buurt';
        $metaDescription = 'Vind een plantenasiel, plantenbieb, ruilkast of zadenbibliotheek bij jou in de buurt — overzicht in Nederland en België.';

        $canonical = route('deelplekken');

        return view('pages.deelplekken', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            // Filter-URL's (?plaats= / ?type= / ?provincie=) → canonical zonder query.
            'canonical' => $canonical,
            'typeLabels' => $deelplekken->typeLabels(),
            'provincies' => $deelplekken->provincies(),
            'filters' => [
                'type' => $type,
                'provincie' => $provincie,
                'plaats' => $plaats,
            ],
            'items' => $items,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => $canonical,
                'description' => $metaDescription,
                'inLanguage' => 'nl',
                'areaServed' => [
                    ['@type' => 'Country', 'name' => 'NL'],
                    ['@type' => 'Country', 'name' => 'BE'],
                ],
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => $site,
                    'url' => url('/'),
                ],
            ],
        ]);
    }
}
