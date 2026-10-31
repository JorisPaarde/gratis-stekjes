<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeelplekAanmeldenRequest;
use App\Notifications\DeelplekAanmeldingAdminMelding;
use App\Notifications\DeelplekAanmeldingBevestiging;
use App\Services\DeelplekAanmelding;
use App\Services\Deelplekken;
use App\Services\Organisaties;
use App\Support\Country;
use App\Support\SafeMail;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class VoorOrganisatiesController extends Controller
{
    public function index(Organisaties $organisaties): View
    {
        $site = config('site.name');

        return view('pages.voor-organisaties', [
            'title' => 'Voor organisaties',
            'metaDescription' => 'Voedselbos, tuinvereniging, plantenasiel of plantenbieb? Meld je plek aan bij '.$site.' zodat mensen je vinden.',
            'canonical' => route('voor-organisaties'),
            'organisaties' => $organisaties->all(),
        ]);
    }

    public function create(Deelplekken $deelplekken): View
    {
        $site = config('site.name');

        return view('pages.voor-organisaties-aanmelden', [
            'title' => 'Plek aanmelden',
            'metaDescription' => 'Meld je voedselbos, plantenasiel, plantenbieb of andere deelplek aan bij '.$site.' — gratis vindbaar op de kaart.',
            'canonical' => route('voor-organisaties.aanmelden'),
            'typeLabels' => $deelplekken->typeLabels(),
            'countries' => Country::labels(),
            'formStarted' => time(),
        ]);
    }

    public function store(DeelplekAanmeldenRequest $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        if ($request->isBot()) {
            return redirect()
                ->route('voor-organisaties.aanmelden')
                ->with('status', 'Bedankt. Ik heb je aanmelding ontvangen en kijk ernaar.');
        }

        $key = 'deelplek-aanmelden:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput($request->except(['website']))
                ->withErrors(['contact_email' => ThrottleMessage::forKey($key)]);
        }
        RateLimiter::hit($key, 3600);

        $result = $aanmelding->submit($request->validated());
        if (empty($result['ok']) || ! isset($result['deelplek'])) {
            return back()
                ->withInput($request->except(['website']))
                ->withErrors(['plaats' => $result['error'] ?? 'Aanmelden mislukt. Probeer het later opnieuw.']);
        }

        $plek = $result['deelplek'];
        SafeMail::toAddress((string) $plek->contact_email, new DeelplekAanmeldingBevestiging($plek));

        $adminEmail = trim((string) config('stekjes.admin_email', ''));
        if ($adminEmail !== '' && strcasecmp($adminEmail, (string) $plek->contact_email) !== 0) {
            SafeMail::toAddress($adminEmail, new DeelplekAanmeldingAdminMelding($plek));
        }

        return redirect()
            ->route('voor-organisaties.aanmelden')
            ->with('status', 'Bedankt. Ik heb je aanmelding ontvangen en kijk ernaar. Je krijgt een bevestiging per e-mail.');
    }
}
