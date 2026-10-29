<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Support\SafeMail;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        $rol = request('rol');
        if (! in_array($rol, ['uitdelen', 'ophalen'], true)) {
            $rol = '';
        }

        return view('auth.register', [
            'title' => 'Registreren',
            'metaDescription' => 'Account maken bij '.config('site.name').'. Bevestig je e-mail om verder te gaan.',
            'canonical' => route('account.registreren'),
            'rol' => $rol,
            'formStarted' => time(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
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

        $email = strtolower(trim((string) $request->validated('email')));
        $password = (string) $request->validated('password');
        $mailOk = true;

        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $user = User::query()->create([
                'name' => trim((string) $request->validated('voornaam')),
                'email' => $email,
                'password' => $password,
                'active' => true,
            ]);
            $mailOk = SafeMail::notify($user, new VerifyEmailDutch);
        } elseif (
            $existing->isActive()
            && ! $existing->hasVerifiedEmail()
            && Hash::check($password, $existing->password)
        ) {
            // Half-aangemaakt (bijv. eerdere mailfout): opnieuw versturen, zelfde succespad.
            $mailOk = SafeMail::notify($existing, new VerifyEmailDutch);
        }
        // Anders: bestaand (bevestigd) adres → geen enumeratie, geen mail.

        $status = $mailOk
            ? 'Check je e-mail om je aanmelding te bevestigen.'
            : 'Je account is aangemaakt, maar de bevestigingsmail lukte niet. Vul hieronder je e-mail in en kies “Stuur opnieuw”.';

        return redirect()
            ->route('account.bevestiging-nodig')
            ->with('status', $status)
            ->with('status_level', $mailOk ? 'ok' : 'warn')
            ->withInput(['email' => $email]);
    }
}
