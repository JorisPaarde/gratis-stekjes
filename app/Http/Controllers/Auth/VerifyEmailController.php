<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ThrottleMessage;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function notice(): View
    {
        return view('auth.verify-notice', [
            'title' => 'Bevestig je e-mail',
            'metaDescription' => 'Bevestig je e-mailadres om verder te gaan bij '.config('site.name').'.',
            'canonical' => route('account.bevestiging-nodig'),
        ]);
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()
            ->route('account.index')
            ->with('status', 'E-mail bevestigd. Welkom — je account is actief.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Vul een geldig e-mailadres in.']);
        }

        $throttleKey = 'verify-resend:'.Str::lower($email).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            return back()
                ->withInput(['email' => $email])
                ->withErrors(['email' => ThrottleMessage::forKey($throttleKey)]);
        }
        RateLimiter::hit($throttleKey, 60);

        $user = User::query()->where('email', $email)->where('active', true)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Als dit adres bij ons bekend is en nog niet bevestigd, sturen we opnieuw een link.');
    }
}
