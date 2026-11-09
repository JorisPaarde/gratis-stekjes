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
use App\Services\UserLocatieService;
use App\Support\Country;
use App\Support\FormGuard;
use App\Support\SafeIntended;
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
    public function create(Request $request, ConversionTracker $tracker): View
    {
        $tracker->record(ConversionEvent::TYPE_REGISTER_VIEW, $request, [
            'path' => '/account/registreren',
        ]);

        $fromQuery = SafeIntended::fromQuery($request);
        if ($fromQuery !== null) {
            SafeIntended::remember($request, $fromQuery);
        }

        $next = $fromQuery
            ?? SafeIntended::peek($request)
            ?? SafeIntended::DEFAULT;
        $next = SafeIntended::sanitize($next);

        $fout = strtolower(trim((string) $request->query('fout', '')));
        $foutMessage = match ($fout) {
            'snel' => 'Dat ging erg snel. Klik nog een keer op Account maken.',
            // Neutraal: geen vaste 'minuut' — flash met ThrottleMessage heeft voorrang.
            'teveel' => 'Even te veel pogingen. Probeer het straks opnieuw.',
            default => null,
        };

        return view('auth.register', [
            'title' => 'Registreren',
            'metaDescription' => 'Account maken bij '.config('site.name').'. Bevestig je e-mail om verder te gaan.',
            'canonical' => route('account.registreren'),
            'formStarted' => time(),
            'countries' => Country::labels(),
            'foundViaOptions' => (array) config('conversion.found_via_options', []),
            'next' => $next,
            'foutMessage' => $foutMessage,
        ]);
    }

    public function store(
        RegisterRequest $request,
        UserLocatieService $locatie,
        ConversionTracker $tracker,
    ): RedirectResponse {
        $next = SafeIntended::fromRequestInput($request);
        if ($next !== null) {
            SafeIntended::remember($request, $next);
        }
        // Nieuw register: first-mail is geldig — wis eventuele hint van eerdere onbevestigde login.
        SafeIntended::clearAssociationResendHint($request);

        if ($request->isBot()) {
            FormGuard::logBotRejected('register', 'honeypot');

            return redirect()
                ->route('account.bevestiging-nodig')
                ->with('status', 'Check je e-mail om je aanmelding te bevestigen.');
        }

        $key = 'register:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            // Altijd terug naar het formulier (niet back()/previous → /): anders verdwijnt
            // de flash op een pagina zonder error-list en lijkt het formulier leeg.
            // Geen e-mail in query (PII in logs/GA); old() via flash als die blijft.
            return redirect()
                ->route('account.registreren', ['fout' => 'teveel'])
                ->withInput(self::safeOldInput($request))
                ->withErrors(['email' => ThrottleMessage::forKey($key)]);
        }
        RateLimiter::hit($key, 3600);

        $tracker->record(ConversionEvent::TYPE_FORM_SUBMIT, $request, [
            'path' => '/account/registreren',
        ]);

        $email = strtolower(trim((string) $request->validated('email')));
        $password = (string) $request->validated('password');
        $country = Country::normalize((string) $request->validated('country'));
        $postcode = trim((string) $request->validated('postcode'));
        $mailOk = true;

        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $user = new User([
                'name' => '',
                'email' => $email,
                'password' => $password,
                'active' => true,
                'country' => $country,
            ]);

            $geo = $locatie->applyPostcode($user, $postcode, $country, true);
            if (! ($geo['ok'] ?? false)) {
                return redirect()
                    ->route('account.registreren')
                    ->withInput(self::safeOldInput($request))
                    ->withErrors(['postcode' => $geo['error'] ?? 'Locatie niet gevonden.']);
            }

            if (Schema::hasColumn('users', 'acquisition_found_via')) {
                $foundVia = trim((string) $request->validated('acquisition_found_via', ''));
                $user->acquisition_found_via = $foundVia !== '' ? $foundVia : null;
                $other = trim((string) $request->validated('acquisition_found_via_other', ''));
                $user->acquisition_found_via_other = ($foundVia === 'anders' && $other !== '')
                    ? mb_substr($other, 0, 120)
                    : null;
            }

            // Nieuwsbrief: alleen lokaal opslaan; Brevo-DOI pas na e-mailbevestiging.
            if (Schema::hasColumn('users', 'newsletter_opt_in') && $request->boolean('nieuwsbrief')) {
                $user->newsletter_opt_in = true;
                $user->newsletter_opt_in_at = now();
            }

            $user->save();
            $user->rotateEmailVerificationToken();
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
            $existing->rotateEmailVerificationToken();
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
     * Old input voor het registratieformulier: e-mail/land/postcode/checks behouden,
     * nooit wachtwoord of honeypot/timing-velden.
     *
     * @return array<string, mixed>
     */
    private static function safeOldInput(Request $request): array
    {
        $except = array_values(array_unique(array_merge(
            ['current_password', 'password', 'password_confirmation', 'form_started', '_token'],
            FormGuard::HONEYPOT_FIELDS,
        )));

        return $request->except($except);
    }
}
