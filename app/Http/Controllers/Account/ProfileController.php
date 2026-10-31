<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', [
            'title' => 'Profiel',
            'metaDescription' => 'Je profiel en woonplaats bij '.config('site.name').'.',
            'canonical' => route('account.profiel'),
            'user' => $request->user(),
            'countries' => Country::labels(),
        ]);
    }

    public function update(Request $request, LocationGeocoder $geocoder): RedirectResponse
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', Rule::in(Country::codes())],
            'locatie' => ['required', 'string', 'max:120'],
            'telefoon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\s().-]{7,40}$/'],
        ], [
            'voornaam.required' => 'Vul een voornaam of bijnaam in.',
            'country.required' => 'Kies een land.',
            'country.in' => 'Kies Nederland of België.',
            'locatie.required' => 'Vul een postcode of plaats in.',
            'telefoon.regex' => 'Telefoonnummer ziet er niet geldig uit.',
        ]);

        $country = Country::normalize($data['country']);
        $locatie = trim($data['locatie']);

        // Als het op een postcode lijkt: land-specifieke validatie (incl. Waalse afwijzing).
        if ($this->looksLikePostcode($locatie, $country)) {
            $pcValid = PostalCode::validate($locatie, $country);
            if ($pcValid !== true) {
                return back()->withInput()->withErrors(['locatie' => $pcValid]);
            }
        }

        $resolved = $geocoder->resolve($locatie, $country, true);
        if (! ($resolved['ok'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors(['locatie' => $resolved['error'] ?? 'Locatie niet gevonden.']);
        }

        $label = PostalCode::locationLabel($locatie, $country, $resolved['label']);

        $user = $request->user();
        $user->fill([
            'name' => trim($data['voornaam']),
            'country' => $country,
            'locatie_label' => $label,
            'lat' => Geo::roundCoord((float) $resolved['lat']),
            'lon' => Geo::roundCoord((float) $resolved['lon']),
        ]);
        // Onleesbare telefoon (andere APP_KEY) niet wissen met leeg formulier.
        $user->fillEncryptedIfProvided('telefoon', $data['telefoon'] ?? '');
        $user->save();

        return redirect()
            ->route('account.profiel')
            ->with('status', 'Profiel opgeslagen.');
    }

    private function looksLikePostcode(string $input, string $country): bool
    {
        if ($country === Country::BE) {
            return (bool) preg_match('/^\d{4}$/', trim($input));
        }

        return (bool) preg_match('/^\d{4}(\s*[A-Za-z]{2})?$/', trim($input));
    }
}
