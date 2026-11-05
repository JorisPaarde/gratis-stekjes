<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ConversionEvent;
use App\Models\User;
use App\Services\ConversionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * Eenmalig welkomstscherm na eerste e-mailbevestiging.
     * Zet welcome_shown_at bij weergave (idempotent).
     */
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->welcome_shown_at !== null) {
            return redirect()->route('account.index');
        }

        $tracker = app(ConversionTracker::class);
        // Bots meten/markeren niet: anders verbruikt een crawler-hit het eenmalige scherm.
        $this->markShown($user, $request, $tracker);

        try {
            $tracker->recordForUser(
                ConversionEvent::TYPE_WELCOME_VIEW,
                $user,
                $request,
                ['path' => '/account/welkom']
            );
        } catch (\Throwable) {
            // Meting mag het scherm nooit blokkeren.
        }

        return view('account.welkom', [
            'title' => 'Welkom',
            'metaDescription' => 'Welkom bij '.config('site.name').'.',
            'canonical' => route('account.welkom'),
            'user' => $user,
        ]);
    }

    /**
     * Primaire CTA: meet klik en stuur door naar aanbieden.
     */
    public function cta(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tracker = app(ConversionTracker::class);
        $this->markShown($user, $request, $tracker);

        try {
            $tracker->recordForUser(
                ConversionEvent::TYPE_WELCOME_CTA_CLICK,
                $user,
                $request,
                ['path' => '/account/welkom']
            );
        } catch (\Throwable) {
            // Meting mag doorsturen nooit blokkeren.
        }

        return redirect()->route('aanbieden');
    }

    private function markShown(User $user, Request $request, ConversionTracker $tracker): void
    {
        if ($tracker->isBot($request)) {
            return;
        }

        if ($user->welcome_shown_at !== null) {
            return;
        }

        $user->forceFill(['welcome_shown_at' => now()])->save();
    }
}
