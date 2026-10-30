<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password', [
            'title' => 'Wachtwoord wijzigen',
            'metaDescription' => 'Wijzig je wachtwoord bij '.config('site.name').'.',
            'canonical' => route('account.wachtwoord'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:200', PasswordRule::min(10)],
        ], [
            'current_password.required' => 'Vul je huidige wachtwoord in.',
            'password.required' => 'Kies een nieuw wachtwoord.',
            'password.confirmed' => 'De wachtwoorden komen niet overeen.',
            'password.min' => 'Kies een wachtwoord van minstens 10 tekens.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Je huidige wachtwoord klopt niet.',
            ]);
        }

        // Maakt password_hash in andere sessies ongeldig (AuthenticateSession).
        Auth::logoutOtherDevices($data['current_password']);

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        // Database-sessies van andere apparaten wissen (huidige behouden).
        if (Schema::hasTable('sessions')) {
            $currentId = $request->session()->getId();
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->when($currentId !== '', fn ($q) => $q->where('id', '!=', $currentId))
                ->delete();
        }

        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );

        return redirect()
            ->route('account.wachtwoord')
            ->with('status', 'Wachtwoord gewijzigd. Andere sessies zijn uitgelogd.');
    }
}
