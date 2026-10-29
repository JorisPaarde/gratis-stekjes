<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmailChangeNoticeDutch;
use App\Notifications\VerifyPendingEmailDutch;
use App\Support\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.email', [
            'title' => 'E-mailadres wijzigen',
            'metaDescription' => 'Wijzig je e-mailadres bij '.config('site.name').'.',
            'canonical' => route('account.email'),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:254',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'pending_email')->ignore($user->id),
            ],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vul een geldig e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'email.unique' => 'Dit e-mailadres is al in gebruik.',
            'password.required' => 'Bevestig met je wachtwoord.',
        ]);

        $newEmail = strtolower(trim($data['email']));

        if (FormGuard::isDisposableEmail($newEmail)) {
            throw ValidationException::withMessages([
                'email' => 'Gebruik geen wegwerpmailadres.',
            ]);
        }

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Wachtwoord klopt niet.',
            ]);
        }

        if ($newEmail === strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'email' => 'Dit is al je huidige e-mailadres.',
            ]);
        }

        $oldEmail = (string) $user->email;

        $user->forceFill([
            'pending_email' => $newEmail,
        ])->save();

        $user->notify(new VerifyPendingEmailDutch($newEmail));

        Notification::route('mail', $oldEmail)
            ->notify(new EmailChangeNoticeDutch($newEmail));

        return redirect()
            ->route('account.email')
            ->with('status', 'Check je nieuwe inbox om het adres te bevestigen. Je oude adres blijft actief tot je bevestigt.');
    }

    public function confirm(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);
        $pending = $user ? strtolower(trim((string) $user->pending_email)) : '';

        if (! $user || $pending === '' || ! hash_equals($hash, sha1($pending))) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if (User::query()->where('email', $pending)->where('id', '!=', $user->id)->exists()) {
            $user->forceFill(['pending_email' => null])->save();

            return redirect()
                ->route('account.email')
                ->withErrors(['email' => 'Dit e-mailadres is inmiddels in gebruik. Vraag opnieuw een wijziging aan.']);
        }

        $user->forceFill([
            'email' => $pending,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        return redirect()
            ->route('account.email')
            ->with('status', 'Je nieuwe e-mailadres is bevestigd en actief.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['pending_email' => null])->save();

        return redirect()
            ->route('account.email')
            ->with('status', 'E-mailwijziging geannuleerd.');
    }
}
