<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Vereniging;
use App\Services\VerenigingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class VerenigingController extends Controller
{
    public function index(Request $request, VerenigingService $service): View
    {
        return view('account.verenigingen', [
            'title' => 'Mijn verenigingen',
            'metaDescription' => 'Koppel je account aan je tuinvereniging, volkstuin of buurttuin.',
            'canonical' => route('account.verenigingen'),
            'mijn' => $request->user()->verenigingen()->orderBy('naam')->get(),
            'opties' => $service->opties(),
            'types' => Vereniging::TYPES,
            'max' => VerenigingService::MAX_PER_LID,
        ]);
    }

    public function store(Request $request, VerenigingService $service): RedirectResponse
    {
        $data = $request->validate([
            'vereniging' => ['required', 'string', 'max:200'],
            'plaats' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
        ], [
            'vereniging.required' => 'Vul de naam van je vereniging in.',
        ]);

        $key = 'vereniging:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withInput()->withErrors(['vereniging' => 'Even rustig aan. Probeer het later opnieuw.']);
        }
        RateLimiter::hit($key, 86400);

        $r = $service->koppel($request->user(), $data['vereniging'], $data['plaats'] ?? null, $data['type'] ?? null);
        if (! $r['ok']) {
            return back()->withInput()->withErrors(['vereniging' => $r['error'] ?? 'Dat lukte niet.']);
        }

        $v = $r['vereniging'];
        $msg = $r['nieuw'] ?? false
            ? 'Toegevoegd. '.$v->naam.' is nieuw voor mij: ik kijk ernaar voordat de vereniging op de site komt.'
            : 'Je bent gekoppeld aan '.$v->naam.'.';

        return redirect()->route('account.verenigingen')->with('status', $msg);
    }

    public function update(Request $request, Vereniging $vereniging, VerenigingService $service): RedirectResponse
    {
        abort_unless($request->user()->verenigingen()->whereKey($vereniging->id)->exists(), 404);
        $service->zetZichtbaar($request->user(), $vereniging->id, $request->boolean('zichtbaar'));

        return redirect()->route('account.verenigingen')->with('status', $request->boolean('zichtbaar')
            ? 'Bij je aanbod staat nu dat je lid bent van '.$vereniging->naam.'.'
            : 'Je lidmaatschap van '.$vereniging->naam.' is niet meer zichtbaar.');
    }

    public function destroy(Request $request, Vereniging $vereniging, VerenigingService $service): RedirectResponse
    {
        $service->ontkoppel($request->user(), $vereniging->id);

        return redirect()->route('account.verenigingen')->with('status', 'Je bent niet meer gekoppeld aan '.$vereniging->naam.'.');
    }
}
