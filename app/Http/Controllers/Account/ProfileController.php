<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdresOpzoeken;
use App\Services\LocationGeocoder;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;
use Illuminate\Http\JsonResponse;
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
            'voornaam' => ['required', 'string', 'max:80', 'not_regex:/@/'],
            'country' => ['required', 'string', Rule::in(Country::codes())],
            'locatie' => ['required', 'string', 'max:120'],
            'telefoon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\s().-]{7,40}$/'],
            'berichtmail' => ['nullable', 'boolean'],
            'huisnummer' => ['nullable', 'string', 'max:12', 'regex:/^\d{1,5}\s*[A-Za-z0-9-]{0,6}$/'],
            'straat' => ['nullable', 'string', 'max:120'],
            'plaats' => ['nullable', 'string', 'max:120'],
            'adres_delen' => ['nullable', 'boolean'],
        ], [
            'huisnummer.regex' => 'Vul een huisnummer in, eventueel met toevoeging (bijvoorbeeld 12 of 12A).',
            'voornaam.required' => 'Vul een voornaam of bijnaam in.',
            'voornaam.not_regex' => 'Gebruik een voornaam of bijnaam, geen e-mailadres. Die zien anderen.',
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
        $postcodeVol = PostalCode::normalize($locatie, $country);
        // Openbaar alleen het postcodegebied (4 cijfers), ook als je de volledige postcode invult.
        if ($country === Country::NL && $postcodeVol !== null && strlen($postcodeVol) === 6) {
            $label = substr($postcodeVol, 0, 4);
        }

        // Volledig adres (optioneel, versleuteld, nooit openbaar).
        $huisnummer = trim((string) ($data['huisnummer'] ?? ''));
        $straat = trim((string) ($data['straat'] ?? ''));
        $plaats = trim((string) ($data['plaats'] ?? ''));
        $adresIngevuld = $huisnummer !== '' || $straat !== '';
        if ($adresIngevuld) {
            $volledigNl = $country === Country::NL && $postcodeVol !== null && strlen($postcodeVol) === 6;
            $volledigBe = $country === Country::BE && $postcodeVol !== null;
            if (! $volledigNl && ! $volledigBe) {
                return back()->withInput()->withErrors(['locatie' => $country === Country::NL
                    ? 'Vul je volledige postcode in (zoals 2011 AB) om je adres op te slaan.'
                    : 'Vul je postcode in om je adres op te slaan.']);
            }
            if ($huisnummer === '' || $straat === '' || $plaats === '') {
                return back()->withInput()->withErrors(['huisnummer' => 'Vul straat, huisnummer en plaats in, of laat het adres helemaal leeg.']);
            }
        }

        $user = $request->user();
        $user->fill([
            'name' => trim($data['voornaam']),
            'country' => $country,
            'locatie_label' => $label,
            'lat' => Geo::roundCoord((float) $resolved['lat']),
            'lon' => Geo::roundCoord((float) $resolved['lon']),
        ]);
        // Oude formulieren zonder dit veld laten de instelling ongemoeid.
        if ($request->has('berichtmail')) {
            $user->berichtmail = $request->boolean('berichtmail');
        }
        // Onleesbare telefoon (andere APP_KEY) niet wissen met leeg formulier.
        $user->fillEncryptedIfProvided('telefoon', $data['telefoon'] ?? '');
        if ($request->has('huisnummer') || $request->has('straat')) {
            if ($adresIngevuld) {
                $user->forceFill([
                    'adres_straat' => $straat,
                    'adres_huisnummer' => strtoupper(preg_replace('/\s+/', '', $huisnummer) ?? $huisnummer),
                    'adres_postcode' => PostalCode::format((string) $postcodeVol, $country),
                    'adres_plaats' => $plaats,
                ]);
            } else {
                foreach (User::ADRES_VELDEN as $veld) {
                    $user->fillEncryptedIfProvided($veld, '');
                }
            }
            $user->adres_delen = $request->boolean('adres_delen');
        }
        $user->save();

        return redirect()
            ->route('account.profiel')
            ->with('status', 'Profiel opgeslagen.');
    }

    /** Postcode + huisnummer opzoeken (alleen NL), voor het profielformulier. */
    public function adresZoeken(Request $request, AdresOpzoeken $opzoeken): JsonResponse
    {
        $huisnummer = trim((string) $request->query('huisnummer', ''));
        preg_match('/^(\d{1,5})\s*(.*)$/', $huisnummer, $m);

        $r = $opzoeken->zoek(
            (string) $request->query('postcode', ''),
            (string) ($m[1] ?? $huisnummer),
            (string) ($m[2] ?? ''),
        );

        return response()->json($r, $r['ok'] ? 200 : 422);
    }

    private function looksLikePostcode(string $input, string $country): bool
    {
        if ($country === Country::BE) {
            return (bool) preg_match('/^\d{4}$/', trim($input));
        }

        return (bool) preg_match('/^\d{4}(\s*[A-Za-z]{2})?$/', trim($input));
    }
}
