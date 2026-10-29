<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        $next = (string) $request->query('next', '/account');
        if ($next === '' || ! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '/account';
        }

        return view('auth.login', [
            'title' => 'Inloggen',
            'metaDescription' => 'Inloggen bij '.config('site.name').'.',
            'canonical' => route('account.inloggen'),
            'next' => $next,
            'formStarted' => time(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if ($request->isBot()) {
            return redirect($request->safeNext());
        }

        $email = strtolower(trim((string) $request->validated('email')));
        $throttleKey = Str::lower($email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withInput($request->only('email', 'next'))
                ->withErrors(['email' => 'Te veel pogingen. Probeer later opnieuw.']);
        }
        RateLimiter::hit($throttleKey, 60);

        $user = User::query()->where('email', $email)->where('active', true)->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, [
            'password' => $request->validated('password'),
        ])) {
            return back()
                ->withInput($request->only('email', 'next'))
                ->withErrors(['email' => 'E-mail of wachtwoord klopt niet.']);
        }

        if (! $user->hasVerifiedEmail()) {
            return back()
                ->withInput($request->only('email', 'next'))
                ->withErrors(['email' => 'Bevestig eerst je e-mail via de link in je inbox.']);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

        return redirect()->intended($request->safeNext());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('status', 'Je bent uitgelogd.');
    }
}
