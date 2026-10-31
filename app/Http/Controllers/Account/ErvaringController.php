<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Ervaring;
use App\Services\ErvaringService;
use App\Support\FormGuard;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErvaringController extends Controller
{
    public const RATE_MAX = 5;

    public const RATE_DECAY = 3600;

    public function index(Request $request): View
    {
        $user = $request->user();
        $mine = Ervaring::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return view('account.ervaringen', [
            'title' => 'Mijn ervaringen',
            'metaDescription' => 'Beheer je gedeelde ervaringen op '.config('site.name').'.',
            'canonical' => route('account.ervaringen'),
            'user' => $user,
            'mine' => $mine,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $aanbodOpties = $user->aanbod()->latest()->limit(30)->get(['id', 'tekst', 'type']);

        return view('account.ervaring-nieuw', [
            'title' => 'Ervaring delen',
            'metaDescription' => 'Deel een korte ervaring over planten of stekjes delen.',
            'canonical' => route('account.ervaringen.create'),
            'user' => $user,
            'aanbodOpties' => $aanbodOpties,
            'maxBody' => ErvaringService::MAX_BODY,
            'formStarted' => time(),
            'defaultPlaats' => (string) ($user->locatie_label ?? ''),
            'conversationId' => $request->integer('gesprek') ?: null,
        ]);
    }

    public function store(Request $request, ErvaringService $ervaringen): RedirectResponse
    {
        $user = $request->user();

        if (FormGuard::honeypotFilled($request->input('website'))) {
            return redirect()
                ->route('account.ervaringen')
                ->with('status', 'Dank je — ik bekijk je ervaring eerst vóór die online gaat.');
        }
        if (FormGuard::submittedTooFast($request->input('form_started'))) {
            return back()->withInput()->withErrors(['body' => 'Even geduld, het formulier ging te snel.']);
        }

        $throttleKey = 'ervaring-store:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return back()
                ->withInput()
                ->withErrors(['body' => ThrottleMessage::forKey($throttleKey)]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.ErvaringService::MAX_BODY],
            'plaatsnaam' => ['nullable', 'string', 'max:'.ErvaringService::MAX_PLAATS],
            'plantensoort_id' => ['nullable', 'integer', 'min:1'],
            'aanbod_id' => ['nullable', 'integer', 'min:1'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'foto' => ['nullable', 'file', 'max:8192'],
        ], [
            'body.required' => 'Schrijf een korte ervaring.',
            'body.max' => 'Houd het bij maximaal '.ErvaringService::MAX_BODY.' tekens.',
            'foto.max' => 'De foto mag maximaal 8 MB zijn.',
        ]);

        $result = $ervaringen->create(
            $user,
            [
                'body' => (string) $data['body'],
                'plaatsnaam' => $data['plaatsnaam'] ?? null,
                'plantensoort_id' => $data['plantensoort_id'] ?? null,
                'aanbod_id' => $data['aanbod_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? $request->input('conversation_id'),
            ],
            $request->file('foto')
        );

        if (! $result['ok']) {
            $field = $result['field'] ?? 'body';

            return back()->withInput()->withErrors([$field => $result['error']]);
        }

        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        return redirect()
            ->route('account.ervaringen')
            ->with('status', 'Dank je — ik bekijk je ervaring eerst vóór die online gaat.');
    }

    public function destroy(Request $request, Ervaring $ervaring, ErvaringService $ervaringen): RedirectResponse
    {
        abort_unless((int) $ervaring->user_id === (int) $request->user()->id, 403);

        $ervaringen->deleteOwned($request->user(), $ervaring);

        return redirect()
            ->route('account.ervaringen')
            ->with('status', 'Ervaring verwijderd.');
    }

    public function photo(Request $request, Ervaring $ervaring): StreamedResponse|Response
    {
        abort_unless((int) $ervaring->user_id === (int) $request->user()->id, 403);
        abort_unless($ervaring->hasPhoto(), 404);

        $path = (string) $ervaring->photo_path;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
