<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\PartnerAanvraag;
use App\Services\InitiatiefPaginas;
use App\Support\FormGuard;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Help mee: posters, stekjeslabels, deelberichten en partneraanvragen. */
class MeedoenController extends Controller
{
    /**
     * Posters voor /poster?voor=…. Foto's: Unsplash (Unsplash License), self-hosted in
     * public/assets/images/posters/{key}-{480,1600}.{webp,jpg}. Bron per foto ook in
     * docs/afbeeldingen-licenties.md.
     *
     * @var array<string, array{titel: string, kop: string, sub: string, doel: string, foto: array{alt: string, maker: string, bron_url: string, licentie: string, positie: string}}>
     */
    public const POSTERS = [
        'algemeen' => [
            'titel' => 'Algemeen',
            'kop' => 'Plantje over?',
            'sub' => 'Geef planten, stekjes en zaden gratis weg aan iemand in de buurt.',
            'doel' => 'home',
            'foto' => [
                'alt' => 'Twee mensen geven elkaar kleine kamerplanten in potjes door',
                'maker' => 'feey',
                'bron_url' => 'https://unsplash.com/photos/Sh6CfogtXIA',
                'licentie' => 'Unsplash License',
                'positie' => 'center 45%',
            ],
        ],
        'volkstuin' => [
            'titel' => 'Volkstuin of tuinvereniging',
            'kop' => 'Te veel zaailingen of stekjes?',
            'sub' => 'Deel ze met andere tuinders en met de buurt. Gratis, je haalt het zelf op.',
            'doel' => 'volkstuinen',
            'foto' => [
                'alt' => 'Hand met jonge zaailingen boven een kweekbak',
                'maker' => 'Zoe Richardson',
                'bron_url' => 'https://unsplash.com/photos/tbiV-yc903g',
                'licentie' => 'Unsplash License',
                'positie' => 'center 40%',
            ],
        ],
        'buurt' => [
            'titel' => 'Buurt, supermarkt of prikbord',
            'kop' => 'Gratis groen bij jou in de buurt',
            'sub' => 'Buren geven planten, stekjes en zaden weg. Kijk wat er te halen is.',
            'doel' => 'aanbod',
            'foto' => [
                'alt' => 'Planten in potten op de stoep bij een voordeur',
                'maker' => 'Anatol Rurac',
                'bron_url' => 'https://unsplash.com/photos/TBcNquxo1JM',
                'licentie' => 'Unsplash License',
                'positie' => 'center 60%',
            ],
        ],
        'bieb' => [
            'titel' => 'Bibliotheek of buurthuis',
            'kop' => 'Stekje mee, stekje terug',
            'sub' => 'Vind planten, stekjes en zaden die mensen in de buurt gratis weggeven.',
            'doel' => 'home',
            'foto' => [
                'alt' => 'Stekjes met wortels in glazen potten op de vensterbank',
                'maker' => 'Kambiz Daneshjou',
                'bron_url' => 'https://unsplash.com/photos/CFQYiGUfQJc',
                'licentie' => 'Unsplash License',
                'positie' => 'center 55%',
            ],
        ],
        'stekchallenge' => [
            'titel' => 'STEKchallenge',
            'kop' => 'Planten over of op zoek? Ruil ze gratis in je buurt',
            'sub' => 'Alles is gratis. Je spreekt zelf af en haalt de planten op.',
            'doel' => 'stekchallenge',
            'foto' => [
                'alt' => 'Groep mensen met stekken in potten tijdens een stekbijeenkomst',
                'maker' => 'Carpe Vitam',
                'bron_url' => 'https://carpevitam.net/stekchallenge/',
                'licentie' => 'Toestemming Carpe Vitam',
                'positie' => 'center 45%',
            ],
        ],
        'struikroven' => [
            'titel' => 'Struikroven',
            'kop' => 'Planten over of op zoek? Ruil ze gratis in je buurt',
            'sub' => 'Alles is gratis. Je spreekt zelf af en haalt de planten op.',
            'doel' => 'struikroven',
            'foto' => [
                'alt' => 'Planten in potten op de stoep bij een voordeur',
                'maker' => 'Anatol Rurac',
                'bron_url' => 'https://unsplash.com/photos/TBcNquxo1JM',
                'licentie' => 'Unsplash License',
                'positie' => 'center 60%',
            ],
        ],
    ];

    /** @var array<string, string> */
    public const PARTNERSOORTEN = [
        'tuincentrum' => 'Tuincentrum of kwekerij',
        'bieb' => 'Bibliotheek of buurthuis',
        'vereniging' => 'Volkstuin- of tuinvereniging',
        'gemeente' => 'Gemeente of welzijnsorganisatie',
        'school' => 'School of natuureducatie',
        'bedrijf' => 'Bedrijf of maker',
        'anders' => 'Anders',
    ];

    public function poster(Request $request, InitiatiefPaginas $initiatieven): View
    {
        $voorQuery = (string) $request->query('voor', 'algemeen');
        $init = $initiatieven->findByInitiatiefSlug($voorQuery);
        $isInitiatief = $init !== null;

        if (array_key_exists($voorQuery, self::POSTERS)) {
            $voor = $voorQuery;
            $p = self::POSTERS[$voor];
            $path = (string) (parse_url(route($p['doel']), PHP_URL_PATH) ?: '/');
        } elseif ($isInitiatief) {
            // Onbekende initiatief-slug: hergebruik algemene posterfoto, QR naar /{slug}.
            $voor = $voorQuery;
            $basis = self::POSTERS['algemeen'];
            $p = array_merge($basis, [
                'titel' => $init->korte_naam !== '' ? $init->korte_naam : $voor,
                'kop' => 'Planten over of op zoek? Ruil ze gratis in je buurt',
                'sub' => 'Alles is gratis. Je spreekt zelf af en haalt de planten op.',
                'doel' => 'initiatief.show',
            ]);
            $path = '/'.$init->initiatief_slug;
        } else {
            $voor = 'algemeen';
            $p = self::POSTERS[$voor];
            $path = (string) (parse_url(route($p['doel']), PHP_URL_PATH) ?: '/');
        }

        // Struikroven heeft eigen posterassets; andere initiatieven zonder assets → algemeen-beeld.
        $fotoKey = array_key_exists($voor, self::POSTERS) ? $voor : 'algemeen';

        $formaat = strtolower((string) $request->query('formaat', 'a4'));
        $isA5 = $formaat === 'a5';

        // Printbare posters: QR en leesbaar adres altijd naar productie,
        // nooit naar staging/local APP_URL (anders scannen bezoekers de verkeerde site).
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        $host = (string) config('site.canonical_host', 'gratisplanten.nl');
        $prodUrl = 'https://'.$host.($path === '/' ? '' : $path);

        $stappen = [
            'Scan de code en kijk wat er bij jou in de buurt te halen is.',
            'Iets over? Zet het er gratis op, het kost je een minuut.',
            'Je haalt het zelf op. Een praatje is de enige prijs.',
        ];
        $bron = 'poster-'.$voor;

        if ($isA5 && ($voor === 'struikroven' || $voor === 'stekchallenge' || $isInitiatief)) {
            $p = array_merge($p, [
                'kop' => 'Planten over na de actie?',
                'sub' => 'Deel planten, stekjes en zaden gratis met iemand in de buurt.',
            ]);
            $stappen = [
                'Planten over na een actie? Zet ze er gratis op.',
                'Iets nodig voor je tuin? Zoek wat er in de buurt is.',
                'Spreek af met de aanbieder en haal het op.',
            ];
            $bron = 'flyer-'.$voor;
        }

        return view('pages.poster', [
            'title' => ($isA5 ? 'Flyer A5: ' : 'Poster: ').$p['kop'],
            'metaDescription' => 'Printbare '.($isA5 ? 'A5-flyer' : 'poster').' met QR-code voor '.config('site.name').'.',
            'canonical' => route('help-mee'),
            'poster' => $p,
            'voor' => $voor,
            'fotoKey' => $fotoKey,
            'initiatiefTerug' => $isInitiatief ? $init : null,
            'formaat' => $isA5 ? 'a5' : 'a4',
            'stappen' => $stappen,
            'url' => $prodUrl.'?bron='.$bron,
            'kortUrl' => $host.($path === '/' ? '' : $path),
        ]);
    }

    public function labels(): View
    {
        return view('pages.stekjeslabels', [
            'title' => 'Stekjeslabels om te printen',
            'metaDescription' => 'Labels met QR-code om bij een weggegeven stekje te steken.',
            'canonical' => route('help-mee'),
            'url' => route('home').'?bron=label',
            'kortUrl' => preg_replace('#^https?://#', '', route('home')),
        ]);
    }

    public function partner(Request $request): RedirectResponse
    {
        if (FormGuard::honeypotFilledIn($request)) {
            return redirect()->to(route('help-mee').'#partner')->with('partner_status', 'Bedankt! Ik neem contact met je op.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()->withInput()->withErrors(['partner' => 'Even geduld, het formulier ging te snel.']);
        }

        $data = $request->validate([
            'naam' => ['required', 'string', 'max:120'],
            'organisatie' => ['required', 'string', 'max:160'],
            'soort' => ['required', 'string', 'in:'.implode(',', array_keys(self::PARTNERSOORTEN))],
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'bericht' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'naam.required' => 'Vul je naam in.',
            'organisatie.required' => 'Vul de naam van je organisatie in.',
            'soort.required' => 'Kies wat voor organisatie het is.',
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'bericht.required' => 'Vertel kort hoe je wilt helpen.',
            'bericht.min' => 'Vertel iets meer over hoe je wilt helpen.',
        ]);

        $key = 'partner:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withInput()->withErrors(['partner' => 'Je hebt al een paar aanvragen gestuurd. Probeer het later opnieuw.']);
        }
        RateLimiter::hit($key, 3600);

        $admin = trim((string) config('stekjes.admin_email', ''));
        if ($admin !== '') {
            SafeMail::toAddress($admin, new PartnerAanvraag(
                naam: trim($data['naam']),
                organisatie: trim($data['organisatie']),
                soort: self::PARTNERSOORTEN[$data['soort']],
                email: mb_strtolower(trim($data['email'])),
                bericht: trim($data['bericht']),
            ));
        }

        return redirect()->to(route('help-mee').'#partner')
            ->with('partner_status', 'Bedankt! Ik lees je bericht zelf en neem contact met je op.');
    }
}
