<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\PartnerAanvraag;
use App\Support\FormGuard;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Help mee: posters, stekjeslabels, deelberichten en partneraanvragen. */
class MeedoenController extends Controller
{
    /** @var array<string, array{titel: string, kop: string, sub: string, doel: string}> */
    public const POSTERS = [
        'algemeen' => [
            'titel' => 'Algemeen',
            'kop' => 'Plantje over?',
            'sub' => 'Geef planten, stekjes en zaden gratis weg aan iemand in de buurt.',
            'doel' => 'home',
        ],
        'volkstuin' => [
            'titel' => 'Volkstuin of tuinvereniging',
            'kop' => 'Te veel zaailingen of stekjes?',
            'sub' => 'Deel ze met andere tuinders en met de buurt. Gratis, je haalt het zelf op.',
            'doel' => 'volkstuinen',
        ],
        'buurt' => [
            'titel' => 'Buurt, supermarkt of prikbord',
            'kop' => 'Gratis groen bij jou in de buurt',
            'sub' => 'Buren geven planten, stekjes en zaden weg. Kijk wat er te halen is.',
            'doel' => 'aanbod',
        ],
        'bieb' => [
            'titel' => 'Bibliotheek of buurthuis',
            'kop' => 'Stekje mee, stekje terug',
            'sub' => 'Vind planten, stekjes en zaden die mensen in de buurt gratis weggeven.',
            'doel' => 'home',
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

    public function poster(Request $request): View
    {
        $voor = array_key_exists((string) $request->query('voor'), self::POSTERS) ? (string) $request->query('voor') : 'algemeen';
        $p = self::POSTERS[$voor];

        return view('pages.poster', [
            'title' => 'Poster: '.$p['kop'],
            'metaDescription' => 'Printbare poster met QR-code voor '.config('site.name').'.',
            'canonical' => route('help-mee'),
            'poster' => $p,
            'voor' => $voor,
            'url' => route($p['doel']).'?bron=poster-'.$voor,
            'kortUrl' => preg_replace('#^https?://#', '', route($p['doel'])),
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
        if (FormGuard::honeypotFilled($request->input('website'))) {
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
