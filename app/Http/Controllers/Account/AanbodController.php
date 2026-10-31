<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Rules\GeenInvasieveSoort;
use App\Services\InvasieveSoorten;
use App\Services\MilestoneService;
use App\Services\Plantensoorten;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class AanbodController extends Controller
{
    public function index(Request $request, InvasieveSoorten $invasieve): View|RedirectResponse
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
            'invasieveTerms' => $invasieve->termsForClient(),
            'invasieveUitlegUrl' => route('invasieve-soorten'),
            // Relatief pad: CSP connect-src 'self' (geen cross-origin fetch).
            'plantensoortenZoekUrl' => '/api/plantensoorten',
        ]);
    }

    public function store(Request $request, Plantensoorten $plantensoorten, MilestoneService $milestones): RedirectResponse
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

        $throttleKey = 'aanbod-store:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 20)) {
            return back()
                ->withInput()
                ->withErrors(['tekst' => ThrottleMessage::forKey($throttleKey)]);
        }

        $data = $this->validatedAanbod($request, $plantensoorten);

        $ttl = (int) config('stekjes.aanbod_ttl_days', 60);
        Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'tekst' => $data['tekst'],
            'plantensoort_id' => $data['plantensoort_id'],
            'visible' => true,
            'expires_at' => now()->addDays($ttl),
        ]);
        RateLimiter::hit($throttleKey, 3600);

        $milestones->maybeSendEersteStekje($user, 'eerste_aanbod');

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod geplaatst.');
    }

    public function update(Request $request, Aanbod $aanbod, Plantensoorten $plantensoorten): RedirectResponse
    {
        abort_unless($aanbod->user_id === $request->user()->id, 403);

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()->route('account.aanbod')->with('status', 'Aanbod bijgewerkt.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()->withInput()->withErrors(['tekst' => 'Even geduld, het formulier ging te snel.']);
        }

        $data = $this->validatedAanbod($request, $plantensoorten);

        $aanbod->update([
            'type' => $data['type'],
            'tekst' => $data['tekst'],
            'plantensoort_id' => $data['plantensoort_id'],
        ]);

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod bijgewerkt.');
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

    public function destroy(Request $request, Aanbod $aanbod): RedirectResponse
    {
        abort_unless($aanbod->user_id === $request->user()->id, 403);

        $aanbod->delete();

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod verwijderd.');
    }

    /** @return array{type: string, tekst: string, plantensoort_id: int|null, gratis_akkoord: mixed} */
    private function validatedAanbod(Request $request, Plantensoorten $plantensoorten): array
    {
        $validator = validator(
            $request->all(),
            [
                'type' => ['required', 'in:uitdelen,ophalen'],
                'tekst' => ['required', 'string', 'min:2', 'max:2000', new GeenInvasieveSoort],
                'plantensoort_id' => ['nullable', 'integer', 'min:1'],
                'gratis_akkoord' => ['accepted'],
            ],
            [
                'type.required' => 'Kies uitdelen of ophalen.',
                'tekst.min' => 'Vul een plantensoort in (minstens 2 tekens).',
                'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
                'plantensoort_id.integer' => 'Ongeldige plantensoort.',
            ]
        );

        $validator->after(function (Validator $validator) use ($request, $plantensoorten): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rawId = $request->input('plantensoort_id');
            if ($rawId !== null && $rawId !== '') {
                $id = (int) $rawId;
                if (! $plantensoorten->exists($id)) {
                    $validator->errors()->add('plantensoort_id', 'Deze plantensoort ken ik niet.');
                }
            }
        });

        $data = $validator->validate();
        $tekst = trim($data['tekst']);
        $plantensoortId = $this->resolvePlantensoortId($plantensoorten, $tekst, $data['plantensoort_id'] ?? null);

        return [
            'type' => $data['type'],
            'tekst' => $tekst,
            'plantensoort_id' => $plantensoortId,
            'gratis_akkoord' => $data['gratis_akkoord'],
        ];
    }

    private function resolvePlantensoortId(Plantensoorten $plantensoorten, string $tekst, mixed $rawId): ?int
    {
        if ($rawId !== null && $rawId !== '') {
            $id = (int) $rawId;
            if ($id > 0 && $plantensoorten->exists($id)) {
                return $id;
            }
        }

        $exact = $plantensoorten->resolveExact($tekst);

        return $exact['id'] ?? null;
    }
}
