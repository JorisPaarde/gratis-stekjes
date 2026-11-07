<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConversionEvent;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Services\ConversionTracker;
use App\Support\MailThrottle;
use App\Support\SafeIntended;
use App\Support\SafeMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $token = (string) $request->query('token', '');

        // Vroege checks buiten de lock (goedkope rejects).
        $user = User::query()->find($id);
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink is verlopen of ongeldig. Vraag hieronder een nieuwe aan.']);
        }

        /** @var array{status: string, user?: User, wasUnverified?: bool} $outcome */
        $outcome = DB::transaction(function () use ($id, $hash, $token): array {
            $locked = User::query()->whereKey($id)->lockForUpdate()->first();
            if (! $locked || ! hash_equals($hash, sha1($locked->getEmailForVerification()))) {
                return ['status' => 'invalid'];
            }

            $stored = (string) ($locked->email_verification_token ?? '');
            if ($token === '' || $stored === '' || ! hash_equals($stored, $token)) {
                return [
                    'status' => $locked->hasVerifiedEmail() ? 'already' : 'used',
                    'user' => $locked,
                ];
            }

            // Compare-and-clear: parallelle requests → precies één winnaar.
            if (! $locked->claimEmailVerificationToken($token)) {
                $locked->refresh();

                return [
                    'status' => $locked->hasVerifiedEmail() ? 'already' : 'used',
                    'user' => $locked,
                ];
            }

            $wasUnverified = ! $locked->hasVerifiedEmail();
            if ($wasUnverified) {
                $locked->markEmailAsVerified();
            }

            return [
                'status' => 'ok',
                'user' => $locked->fresh() ?? $locked,
                'wasUnverified' => $wasUnverified,
            ];
        });

        if ($outcome['status'] === 'invalid') {
            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer).']);
        }

        if ($outcome['status'] !== 'ok') {
            if (($outcome['status'] ?? '') === 'already') {
                return redirect()
                    ->route('account.inloggen')
                    ->with('status', 'Je e-mailadres is al bevestigd. Log in om verder te gaan.');
            }

            return redirect()
                ->route('account.inloggen')
                ->withErrors(['email' => 'Deze bevestigingslink werkt niet (meer). Vraag een nieuwe aan.']);
        }

        /** @var User $verifiedUser */
        $verifiedUser = $outcome['user'];

        if ($outcome['wasUnverified'] ?? false) {
            event(new Verified($verifiedUser));
            try {
                app(ConversionTracker::class)->recordForUser(
                    ConversionEvent::TYPE_EMAIL_VERIFIED,
                    $verifiedUser,
                    $request,
                    ['path' => '/account/bevestigen']
                );
            } catch (\Throwable) {
                // Meting mag bevestigen nooit blokkeren.
            }
        }

        Auth::login($verifiedUser, false);
        $request->session()->regenerate();

        // Eerste bevestiging → eenmalig welkomstscherm (tenzij al gemarkeerd).
        // Met veilige intended (bijv. /account/verenigingen): markeer welkom en ga daarheen
        // zodat de CTA-return automatisch werkt zonder GP-welcome-loop te openen.
        if (($outcome['wasUnverified'] ?? false) && $verifiedUser->welcome_shown_at === null) {
            $intended = SafeIntended::peek($request);
            if ($intended !== null && $intended !== SafeIntended::DEFAULT) {
                $verifiedUser->forceFill(['welcome_shown_at' => now()])->save();

                return redirect()
                    ->intended(SafeIntended::DEFAULT)
                    ->with('status', 'E-mail bevestigd. Welkom — je account is actief.');
            }

            return redirect()->route('account.welkom');
        }

        if (SafeIntended::peek($request) !== null) {
            return redirect()
                ->intended(SafeIntended::DEFAULT)
                ->with('status', 'E-mail bevestigd. Welkom — je account is actief. Vul op je profiel nog een voornaam of bijnaam in.');
        }

        return redirect()
            ->route('account.index')
            ->with('status', 'E-mail bevestigd. Welkom — je account is actief. Vul op je profiel nog een voornaam of bijnaam in.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Vul een geldig e-mailadres in.']);
        }

        $throttle = MailThrottle::attempt('verify', $email, $request);
        if (! $throttle['ok']) {
            return back()
                ->withInput(['email' => $email])
                ->withErrors(['email' => $throttle['message']]);
        }

        $mailOk = true;
        $user = User::query()->where('email', $email)->where('active', true)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->rotateEmailVerificationToken();
            $mailOk = SafeMail::notify($user, new VerifyEmailDutch);
        }

        if (! $mailOk) {
            return back()
                ->withInput(['email' => $email])
                ->with('status', 'De bevestigingsmail lukte niet. Probeer het zo opnieuw, of probeer later.')
                ->with('status_level', 'warn');
        }

        return back()
            ->withInput(['email' => $email])
            ->with('status', 'Als dit adres bij mij bekend is en nog niet bevestigd, stuur ik opnieuw een link.');
    }
}
