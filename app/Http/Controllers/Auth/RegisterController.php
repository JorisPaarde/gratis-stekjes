<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ConversionEvent;
use App\Models\User;
use App\Notifications\AccountBestaatAl;
use App\Notifications\VerifyEmailDutch;
use App\Services\ConversionTracker;
use App\Services\InitiatiefPaginas;
use App\Support\SafeMail;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request, InitiatiefPaginas $initiatieven, ConversionTracker $tracker): View
    {
        $tracker->record(ConversionEvent::TYPE_REGISTER_VIEW, $request, [
            'path' => '/account/registreren',
        ]);

        $vinkjes = $this->aanmeldVinkjes($initiatieven);
        $checked = [];
        foreach ($vinkjes as $vink) {
            $param = $vink['param'];
            $checked[$param] = $request->boolean($param) || (bool) old($param);
        }

        return view('auth.register', [
            'title' => 'Registreren',
            'metaDescription' => 'Account maken bij '.config('site.name').'. Bevestig je e-mail om verder te gaan.',
            'canonical' => route('account.registreren'),
            'formStarted' => time(),
            'aanmeldVinkjes' => $vinkjes,
            'aanmeldChecked' => $checked,
            // BC voor oudere tests/partials.
            'struikroverChecked' => $checked['struikrover'] ?? false,
            'foundViaOptions' => (array) config('conversion.found_via_options', []),
        ]);
    }

    public function store(RegisterRequest $request, InitiatiefPaginas $initiatieven, ConversionTracker $tracker): RedirectResponse
    {
        if ($request->isBot()) {
            return redirect()
                ->route('account.bevestiging-nodig')
                ->with('status', 'Check je e-mail om je aanmelding te bevestigen.');
        }

        $key = 'register:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => ThrottleMessage::forKey($key)]);
        }
        RateLimiter::hit($key, 3600);

        $tracker->record(ConversionEvent::TYPE_FORM_SUBMIT, $request, [
            'path' => '/account/registreren',
        ]);

        $email = strtolower(trim((string) $request->validated('email')));
        $password = (string) $request->validated('password');
        $mailOk = true;

        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $payload = [
                'name' => trim((string) $request->validated('voornaam')),
                'email' => $email,
                'password' => $password,
                'active' => true,
            ];
            // Vinkjes van initiatieven: sla op als users.{param} bestaat (struikrover, stekchallenge, …).
            foreach ($this->aanmeldVinkjes($initiatieven) as $vink) {
                $param = $vink['param'];
                if (Schema::hasColumn('users', $param)) {
                    $payload[$param] = $request->boolean($param);
                }
            }
            // BC: expliciete struikrover-param ook zonder initiatief-rij.
            if (! array_key_exists('struikrover', $payload) && Schema::hasColumn('users', 'struikrover')) {
                $payload['struikrover'] = $request->boolean('struikrover');
            }

            if (Schema::hasColumn('users', 'acquisition_found_via')) {
                $foundVia = trim((string) $request->validated('acquisition_found_via', ''));
                $payload['acquisition_found_via'] = $foundVia !== '' ? $foundVia : null;
                $other = trim((string) $request->validated('acquisition_found_via_other', ''));
                $payload['acquisition_found_via_other'] = ($foundVia === 'anders' && $other !== '')
                    ? mb_substr($other, 0, 120)
                    : null;
            }

            $user = User::query()->create($payload);
            $tracker->attachFlowToUser($user->fresh(), $request);
            $tracker->recordForUser(ConversionEvent::TYPE_ACCOUNT_CREATED, $user->fresh(), $request, [
                'path' => '/account/registreren',
            ]);
            $mailOk = SafeMail::notify($user, new VerifyEmailDutch);
        } elseif (
            $existing->isActive()
            && ! $existing->hasVerifiedEmail()
            && Hash::check($password, $existing->password)
        ) {
            // Half-aangemaakt (bijv. eerdere mailfout): opnieuw versturen, zelfde succespad.
            $mailOk = SafeMail::notify($existing, new VerifyEmailDutch);
        } elseif ($existing->isActive() && $existing->hasVerifiedEmail()) {
            // Bestaand account: scherm blijft gelijk (geen enumeratie), eigenaar krijgt een mail
            // met inlog- en wachtwoordlink. Max. één keer per uur per adres.
            $mailKey = 'account-bestaat:'.sha1($email);
            if (! RateLimiter::tooManyAttempts($mailKey, 1)) {
                RateLimiter::hit($mailKey, 3600);
                SafeMail::notify($existing, new AccountBestaatAl);
            }
        }

        $status = $mailOk
            ? 'Check je e-mail om je aanmelding te bevestigen.'
            : 'Je account is aangemaakt, maar de bevestigingsmail lukte niet. Vul hieronder je e-mail in en kies “Stuur opnieuw”.';

        return redirect()
            ->route('account.bevestiging-nodig')
            ->with('status', $status)
            ->with('status_level', $mailOk ? 'ok' : 'warn')
            ->withInput(['email' => $email]);
    }

    /**
     * @return list<array{param: string, label: string}>
     */
    private function aanmeldVinkjes(InitiatiefPaginas $initiatieven): array
    {
        $out = [];
        $seen = [];
        foreach ($initiatieven->metPagina() as $init) {
            $param = trim((string) $init->aanmeld_param);
            $label = trim((string) $init->aanmeld_label);
            if ($param === '' || $label === '' || isset($seen[$param])) {
                continue;
            }
            $seen[$param] = true;
            $out[] = ['param' => $param, 'label' => $label];
        }

        // Fallback als migratie/seed nog niet draait: oude Struikrover-vinkje.
        if ($out === []) {
            $out[] = ['param' => 'struikrover', 'label' => 'Ik ben Struikrover'];
        }

        return $out;
    }
}
