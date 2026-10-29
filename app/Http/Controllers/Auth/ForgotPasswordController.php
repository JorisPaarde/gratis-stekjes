<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password', [
            'title' => 'Wachtwoord vergeten',
            'metaDescription' => 'Vraag een wachtwoordreset aan bij '.config('site.name').'.',
            'canonical' => route('account.wachtwoord.vergeten'),
            'formStarted' => time(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_started' => ['required', 'integer'],
        ], [
            'email.required' => 'Vul een geldig e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
        ]);

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return back()->with('status', __('passwords.sent'));
        }

        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['form_started' => 'Even geduld, het formulier ging te snel.']);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $throttleKey = 'password-reset:'.Str::lower($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => ThrottleMessage::forKey($throttleKey)]);
        }
        RateLimiter::hit($throttleKey, 60);

        // Geen enumeratie: altijd dezelfde succesmelding (behalve throttle).
        Password::sendResetLink(['email' => $email]);

        return back()->with('status', __('passwords.sent'));
    }
}
