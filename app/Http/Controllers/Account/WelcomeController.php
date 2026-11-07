<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ConversionEvent;
use App\Models\User;
use App\Services\ConversionTracker;
use App\Support\SafeIntended;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public const SESSION_RECOVERY_DONE = 'welcome_recovery_done';

    /**
     * Eenmalig welkomstscherm na eerste e-mailbevestiging.
     * Zet welcome_shown_at bij weergave (idempotent; ook bij bot-UA).
     */
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->welcome_shown_at !== null) {
            return redirect()->route('account.index');
        }

        $this->markShown($user);

        try {
            app(ConversionTracker::class)->recordForUser(
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

        $this->markShown($user);

        try {
            app(ConversionTracker::class)->recordForUser(
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

    /**
     * Escape: markeer en ga naar account (of veilige intended return).
     */
    public function continue(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->markShown($user);

        return redirect()->intended(SafeIntended::DEFAULT);
    }

    private function markShown(User $user): void
    {
        if ($user->welcome_shown_at !== null) {
            return;
        }

        $user->forceFill(['welcome_shown_at' => now()])->save();
    }
}
