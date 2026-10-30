<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\PdokGeocoder;
use App\Support\Geo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', [
            'title' => 'Profiel',
            'metaDescription' => 'Je profiel en benaderde locatie bij '.config('site.name').'.',
            'canonical' => route('account.profiel'),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, PdokGeocoder $geocoder): RedirectResponse
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:80'],
            'locatie' => ['required', 'string', 'max:120'],
            'telefoon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\s().-]{7,40}$/'],
        ], [
            'voornaam.required' => 'Vul een voornaam of bijnaam in.',
            'locatie.required' => 'Vul een postcode (4 cijfers) of plaats in.',
            'telefoon.regex' => 'Telefoonnummer ziet er niet geldig uit.',
        ]);

        $locatie = trim($data['locatie']);
        $geo = $geocoder->geocode($locatie, true);
        if (! $geo) {
            return back()
                ->withInput()
                ->withErrors(['locatie' => 'Locatie niet gevonden. Gebruik een postcode (4 cijfers) of plaatsnaam in Nederland.']);
        }

        if (preg_match('/^\d{4}/', $locatie)) {
            $label = substr(preg_replace('/\D/', '', $locatie) ?? '', 0, 4);
        } else {
            $label = trim(explode(',', $geo['label'])[0]);
            $label = mb_substr($label, 0, 80);
        }

        $request->user()->update([
            'name' => trim($data['voornaam']),
            'telefoon' => trim((string) ($data['telefoon'] ?? '')),
            'locatie_label' => $label,
            // Dubbele afronding: geocoder rondt al af; hier hard afdwingen voor opslag.
            'lat' => Geo::roundCoord((float) $geo['lat']),
            'lon' => Geo::roundCoord((float) $geo['lon']),
        ]);

        return redirect()
            ->route('account.profiel')
            ->with('status', 'Profiel opgeslagen.');
    }
}
