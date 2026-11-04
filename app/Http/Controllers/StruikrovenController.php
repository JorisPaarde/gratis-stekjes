<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Evenementen;
use App\Services\Organisaties;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StruikrovenController extends Controller
{
    public const ORGANISATIE_SLUG = 'struikroven';

    public function __invoke(Evenementen $evenementen, Organisaties $organisaties): View
    {
        $org = $organisaties->find(self::ORGANISATIE_SLUG);
        $acties = $evenementen->upcomingForOrganisatie(self::ORGANISATIE_SLUG);

        $title = 'Struikroven redt planten van de sloop';
        $meta = 'Struikroven redt planten bij sloop en vernieuwing. Via '.config('site.name').' deel je overgebleven planten, stekjes en zaden gratis met iemand in de buurt.';
        $canonical = route('struikroven');

        return view('pages.struikroven', [
            'title' => $title,
            'metaDescription' => $meta,
            'canonical' => $canonical,
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
