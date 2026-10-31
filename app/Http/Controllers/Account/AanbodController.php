<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Rules\GeenInvasieveSoort;
use App\Services\AanbodFotoService;
use App\Services\InvasieveSoorten;
use App\Services\MilestoneService;
use App\Services\Plantensoorten;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use RuntimeException;

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
            'mine' => $user->aanbod()->with('fotos')->latest()->get(),
            'formStarted' => time(),
            'invasieveTerms' => $invasieve->termsForClient(),
            'invasieveUitlegUrl' => route('invasieve-soorten'),
            // Relatief pad: CSP connect-src 'self' (geen cross-origin fetch).
            'plantensoortenZoekUrl' => '/api/plantensoorten',
            'fotoMaxCount' => (int) config('stekjes.aanbod_fotos.max_count', 3),
            'fotoMaxMb' => (int) round(((int) config('stekjes.aanbod_fotos.max_bytes', 8 * 1024 * 1024)) / 1024 / 1024),
        ]);
    }

    public function store(
        Request $request,
        Plantensoorten $plantensoorten,
        AanbodFotoService $fotos,
        MilestoneService $milestones,
    ): RedirectResponse {
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

        $uploadThrottle = 'aanbod-foto-upload:'.$user->id;
        $incomingFotos = $this->incomingFotoFiles($request);
        if ($incomingFotos !== []) {
            $limit = (int) config('stekjes.aanbod_fotos.upload_rate_per_hour', 30);
            if (RateLimiter::tooManyAttempts($uploadThrottle, $limit)) {
                return back()
                    ->withInput()
                    ->withErrors(['fotos' => ThrottleMessage::forKey($uploadThrottle)]);
            }
        }

        $data = $this->validatedAanbod($request, $plantensoorten, $incomingFotos, hasExistingFotos: false);

        $ttl = (int) config('stekjes.aanbod_ttl_days', 60);
        $aanbod = Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'tekst' => $data['tekst'],
            'plantensoort_id' => $data['plantensoort_id'],
            'visible' => true,
            'expires_at' => now()->addDays($ttl),
        ]);

        if ($incomingFotos !== []) {
            try {
                $fotos->storeForAanbod($aanbod, $incomingFotos, $data['tekst']);
                RateLimiter::hit($uploadThrottle, 3600);
            } catch (RuntimeException $e) {
                $aanbod->delete();

                return back()->withInput()->withErrors(['fotos' => $e->getMessage()]);
            }
        }

        RateLimiter::hit($throttleKey, 3600);

        $milestones->maybeSendEersteStekje($user, 'eerste_aanbod');

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod geplaatst.');
    }

    public function update(
        Request $request,
        Aanbod $aanbod,
        Plantensoorten $plantensoorten,
        AanbodFotoService $fotos,
    ): RedirectResponse {
        abort_unless($aanbod->user_id === $request->user()->id, 403);

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()->route('account.aanbod')->with('status', 'Aanbod bijgewerkt.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()->withInput()->withErrors(['tekst' => 'Even geduld, het formulier ging te snel.']);
        }

        $removeIds = array_values(array_filter(array_map(
            static fn ($v) => (int) $v,
            (array) $request->input('remove_fotos', [])
        ), static fn (int $id) => $id > 0));

        foreach ($removeIds as $fotoId) {
            $foto = AanbodFoto::query()
                ->where('aanbod_id', $aanbod->id)
                ->whereKey($fotoId)
                ->first();
            if ($foto) {
                $fotos->deleteFoto($foto);
            }
        }

        $incomingFotos = $this->incomingFotoFiles($request);
        if ($incomingFotos !== []) {
            $uploadThrottle = 'aanbod-foto-upload:'.$request->user()->id;
            $limit = (int) config('stekjes.aanbod_fotos.upload_rate_per_hour', 30);
            if (RateLimiter::tooManyAttempts($uploadThrottle, $limit)) {
                return back()
                    ->withInput()
                    ->withErrors(['fotos' => ThrottleMessage::forKey($uploadThrottle)]);
            }
        }

        $aanbod->load('fotos');
        $data = $this->validatedAanbod(
            $request,
            $plantensoorten,
            $incomingFotos,
            hasExistingFotos: $aanbod->fotos->isNotEmpty()
        );

        $aanbod->update([
            'type' => $data['type'],
            'tekst' => $data['tekst'],
            'plantensoort_id' => $data['plantensoort_id'],
        ]);

        if ($incomingFotos !== []) {
            try {
                $fotos->storeForAanbod($aanbod, $incomingFotos, $data['tekst']);
                RateLimiter::hit('aanbod-foto-upload:'.$request->user()->id, 3600);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['fotos' => $e->getMessage()]);
            }
        }

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

    public function destroy(Request $request, Aanbod $aanbod, AanbodFotoService $fotos): RedirectResponse
    {
        abort_unless($aanbod->user_id === $request->user()->id, 403);

        $fotos->deleteAllForAanbod($aanbod);
        $aanbod->delete();

        return redirect()
            ->route('account.aanbod')
            ->with('status', 'Aanbod verwijderd.');
    }

    /**
     * @param  list<UploadedFile>  $incomingFotos
     * @return array{type: string, tekst: string, plantensoort_id: int|null, gratis_akkoord: mixed}
     */
    private function validatedAanbod(
        Request $request,
        Plantensoorten $plantensoorten,
        array $incomingFotos,
        bool $hasExistingFotos,
    ): array {
        $maxCount = (int) config('stekjes.aanbod_fotos.max_count', 3);
        $maxBytes = (int) config('stekjes.aanbod_fotos.max_bytes', 8 * 1024 * 1024);
        $maxKb = (int) ceil($maxBytes / 1024);

        $validator = validator(
            $request->all(),
            [
                'type' => ['required', 'in:uitdelen,ophalen'],
                'tekst' => ['nullable', 'string', 'max:2000', new GeenInvasieveSoort],
                'plantensoort_id' => ['nullable', 'integer', 'min:1'],
                'gratis_akkoord' => ['accepted'],
                'fotos' => ['nullable', 'array', 'max:'.$maxCount],
                'fotos.*' => ['file', 'max:'.$maxKb],
            ],
            [
                'type.required' => 'Kies uitdelen of ophalen.',
                'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
                'plantensoort_id.integer' => 'Ongeldige plantensoort.',
                'fotos.max' => 'Je mag maximaal '.$maxCount.' foto’s uploaden.',
                'fotos.*.max' => 'Elke foto mag maximaal '.round($maxBytes / 1024 / 1024).' MB zijn.',
            ]
        );

        $validator->after(function (Validator $validator) use ($request, $plantensoorten, $incomingFotos, $hasExistingFotos): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tekst = trim((string) $request->input('tekst', ''));
            $hasSpecies = $tekst !== '';
            $hasPhoto = $hasExistingFotos || $incomingFotos !== [];

            if (! $hasSpecies && ! $hasPhoto) {
                $validator->errors()->add(
                    'tekst',
                    'Vul een plantensoort in of upload minstens één foto.'
                );
            }

            if ($hasSpecies && mb_strlen($tekst) < 2) {
                $validator->errors()->add('tekst', 'Vul een plantensoort in (minstens 2 tekens).');
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
        $tekst = trim((string) ($data['tekst'] ?? ''));
        $plantensoortId = $tekst !== ''
            ? $this->resolvePlantensoortId($plantensoorten, $tekst, $data['plantensoort_id'] ?? null)
            : null;

        return [
            'type' => $data['type'],
            'tekst' => $tekst,
            'plantensoort_id' => $plantensoortId,
            'gratis_akkoord' => $data['gratis_akkoord'],
        ];
    }

    /** @return list<UploadedFile> */
    private function incomingFotoFiles(Request $request): array
    {
        $files = $request->file('fotos', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn ($f) => $f instanceof UploadedFile && $f->isValid()
        ));
    }

    private function resolvePlantensoortId(Plantensoorten $plantensoorten, string $tekst, mixed $rawId): ?int
    {
        if ($rawId !== null && $rawId !== '') {
            $id = (int) $rawId;
            if ($id > 0 && $plantensoorten->exists($id)) {
                return $id;
            }
        }

        // Vrije tekst koppelt alleen zichtbare soorten; verborgen blijven
        // bereikbaar via bestaand plantensoort_id (aanbod bewerken).
        $exact = $plantensoorten->resolveExact($tekst);
        if ($exact === null || ! ($exact['zichtbaar'] ?? true)) {
            return null;
        }

        return $exact['id'];
    }
}
