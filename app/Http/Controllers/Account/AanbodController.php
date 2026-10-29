<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Support\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AanbodController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasLocation()) {
            return redirect()
                ->route('account.profiel')
                ->with('status', 'Vul eerst je postcode of plaats in om aanbod te plaatsen.');
        }

        return view('account.aanbod', [
            'title' => 'Mijn aanbod',
            'metaDescription' => 'Beheer je gratis aanbod op '.config('site.name').'.',
            'canonical' => route('account.aanbod'),
            'user' => $user,
            'mine' => $user->aanbod()->latest()->get(),
            'formStarted' => time(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasLocation()) {
            return redirect()->route('account.profiel');
        }

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()->route('account.aanbod')->with('status', 'Aanbod geplaatst.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()->withInput()->withErrors(['tekst' => 'Even geduld, het formulier ging te snel.']);
        }

        $data = $request->validate([
            'type' => ['required', 'in:uitdelen,ophalen'],
            'tekst' => ['required', 'string', 'min:5', 'max:2000'],
            'gratis_akkoord' => ['accepted'],
        ], [
            'type.required' => 'Kies uitdelen of ophalen.',
            'tekst.min' => 'Schrijf kort wat je hebt of zoekt (minstens 5 tekens).',
            'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
        ]);

        $ttl = (int) config('stekjes.aanbod_ttl_days', 60);
        Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'tekst' => trim($data['tekst']),
            'visible' => true,
            'expires_at' => now()->addDays($ttl),
        ]);

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod geplaatst.');
    }

    public function renew(Request $request, Aanbod $aanbod): RedirectResponse
    {
        abort_unless($aanbod->user_id === $request->user()->id, 403);

        $ttl = (int) config('stekjes.aanbod_ttl_days', 60);
        $aanbod->update([
            'expires_at' => now()->addDays($ttl),
            'expiry_reminder_sent_at' => null,
            'renew_token' => null,
            'renew_token_expires' => null,
            'visible' => true,
        ]);

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod verlengd met '.$ttl.' dagen.');
    }
}
