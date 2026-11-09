<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdresOpzoeken;
use App\Services\InitiatiefPaginas;
use App\Services\LocationGeocoder;
use App\Services\UserLocatieService;
use App\Support\Country;
use App\Support\Geo;
use App\Support\Naam;
use App\Support\PostalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, InitiatiefPaginas $initiatieven): View
    {
        $user = $request->user();
        $modus = old('locatie_modus');
        if (! in_array($modus, ['postcode', 'plaats'], true)) {
            // Bestaande leden met alleen een plaatsnaam openen in plaats-modus.
            $heeftGebied = trim((string) ($user->postcode_gebied ?? '')) !== '';
            $label = trim((string) ($user->locatie_label ?? ''));
            $modus = (! $heeftGebied && $label !== '' && ! PostalCode::looksLikePostcode($label, (string) ($user->country ?? Country::NL)))
                ? 'plaats'
                : 'postcode';
        }

        return view('account.profile', [
            'title' => 'Profiel',
            'metaDescription' => 'Je profiel en woonplaats bij '.config('site.name').'.',
            'canonical' => route('account.profiel'),
            'user' => $user,
            'countries' => Country::labels(),
            'locatieModus' => $modus,
            'aanmeldVinkjes' => $this->aanmeldVinkjes($initiatieven),
        ]);
    }

    public function update(Request $request, LocationGeocoder $geocoder, UserLocatieService $locatieService): RedirectResponse
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:80', 'not_regex:/@/', function (string $attr, mixed $value, \Closure $fail) use ($request): void {
                $uid = (int) $request->user()->id;
                if (is_string($value) && Naam::lijktOpEmail($value, (string) $request->user()->email)) {
                    $fail(Naam::MELDING_EMAIL);

                    return;
                }
                // Alleen bij een andere naam dan je al had: bestaande dubbele namen blijven werken.
                if (is_string($value) && Naam::normaal($value) !== Naam::normaal((string) $request->user()->name) && Naam::bezet($value, $uid)) {
                    $fail(Naam::melding($value, $uid));
                }
            }],
            'country' => ['required', 'string', Rule::in(Country::codes())],
            'locatie_modus' => ['nullable', 'string', Rule::in(['postcode', 'plaats'])],
            'locatie' => ['required', 'string', 'max:120'],
            'telefoon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\s().-]{7,40}$/'],
            'berichtmail' => ['nullable', 'boolean'],
            'activatieherinnering' => ['nullable', 'boolean'],
            'huisnummer' => ['nullable', 'string', 'max:12', 'regex:/^\d{1,5}\s*[A-Za-z0-9-]{0,6}$/'],
            'straat' => ['nullable', 'string', 'max:120'],
            'plaats' => ['nullable', 'string', 'max:120'],
            'adres_delen' => ['nullable', 'boolean'],
            'struikrover' => ['nullable', 'boolean'],
            'stekchallenge' => ['nullable', 'boolean'],
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
        // Expliciete modus uit het formulier; anders afleiden (oude clients/tests).
        $modus = match ($data['locatie_modus'] ?? null) {
            'plaats' => 'plaats',
            'postcode' => 'postcode',
            default => PostalCode::looksLikePostcode($locatie, $country) ? 'postcode' : 'plaats',
        };

        $user = $request->user();
        $postcodeVol = null;

        if ($modus === 'postcode') {
            // Zelfde NL/BE-postcodepad als registratie (geen parallelle duplicaatlogica).
            $geo = $locatieService->applyPostcode($user, $locatie, $country, true);
            if (! ($geo['ok'] ?? false)) {
                return back()
                    ->withInput()
                    ->withErrors(['locatie' => $geo['error'] ?? 'Locatie niet gevonden.']);
            }
            $postcodeVol = PostalCode::normalize($locatie, $country);
        } else {
            // Plaatsnaam-fallback: geen kale postcode als «plaats».
            if (PostalCode::looksLikePostcode($locatie, $country)) {
                return back()->withInput()->withErrors([
                    'locatie' => 'Dat lijkt op een postcode. Kies «Ik weet mijn postcode» of vul een plaatsnaam in.',
                ]);
            }

            $resolved = $geocoder->resolve($locatie, $country, true);
            if (! ($resolved['ok'] ?? false)) {
                return back()
                    ->withInput()
                    ->withErrors(['locatie' => $resolved['error'] ?? 'Locatie niet gevonden.']);
            }

            $woonplaats = trim((string) ($resolved['woonplaats'] ?? ''));
            if ($woonplaats === '') {
                $woonplaats = PostalCode::plaatsFromGeoLabel((string) ($resolved['label'] ?? ''), $locatie);
            }
            if ($woonplaats === '') {
                $woonplaats = mb_substr($locatie, 0, 80);
            }
            $user->fill([
                'country' => $country,
                'locatie_label' => PostalCode::publicLabel(null, $woonplaats),
                'postcode_gebied' => null, // bewust: openbaar alleen de plaatsnaam
                'woonplaats' => mb_substr($woonplaats, 0, 80),
                'lat' => Geo::roundCoord((float) $resolved['lat']),
                'lon' => Geo::roundCoord((float) $resolved['lon']),
            ]);
        }

        // Volledig adres (optioneel, versleuteld, nooit openbaar).
        $huisnummer = trim((string) ($data['huisnummer'] ?? ''));
        $straat = trim((string) ($data['straat'] ?? ''));
        $plaats = trim((string) ($data['plaats'] ?? ''));
        $adresIngevuld = $huisnummer !== '' || $straat !== '';
        if ($adresIngevuld) {
            if ($modus !== 'postcode' || $postcodeVol === null) {
                return back()->withInput()->withErrors(['locatie' => $country === Country::NL
                    ? 'Vul je volledige postcode in (zoals 2011 AB) om je adres op te slaan.'
                    : 'Vul je postcode in om je adres op te slaan.']);
            }
            $volledigNl = $country === Country::NL && strlen($postcodeVol) === 6;
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

        $user->fill([
            'name' => trim($data['voornaam']),
        ]);
        // Oude formulieren zonder dit veld laten de instelling ongemoeid.
        if ($request->has('berichtmail')) {
            $user->berichtmail = $request->boolean('berichtmail');
        }
        if ($request->has('activatieherinnering') && Schema::hasColumn('users', 'activatieherinnering')) {
            $user->activatieherinnering = $request->boolean('activatieherinnering');
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
        foreach (['struikrover', 'stekchallenge'] as $vlag) {
            if ($request->has($vlag) && Schema::hasColumn('users', $vlag)) {
                $user->{$vlag} = $request->boolean($vlag);
            }
        }
        $user->save();

        // Kwam je via «Bied iets aan» zonder naam/locatie? Dan na opslaan terug naar het formulier.
        if ($user->heeftGekozenNaam() && $user->hasLocation() && $request->session()->get('url.intended') === route('aanbieden')) {
            $request->session()->forget('url.intended');

            return redirect()->route('aanbieden')->with('status', 'Profiel opgeslagen. Nu kun je iets aanbieden.');
        }

        return redirect()
            ->route('account.profiel')
            ->with('status', 'Profiel opgeslagen.');
    }

    /**
     * @return list<array{param: string, label: string}>
     */
    private function aanmeldVinkjes(InitiatiefPaginas $initiatieven): array
    {
        $out = [];
        $seen = [];
        foreach ($initiatieven->metPagina() as $init) {
            $param = trim((string) $init->aanmeld_param);
            $label = trim((string) $init->aanmeld_label);
            if ($param === '' || $label === '' || isset($seen[$param]) || ! Schema::hasColumn('users', $param)) {
                continue;
            }
            $seen[$param] = true;
            $out[] = ['param' => $param, 'label' => $label];
        }

        return $out;
    }

    /** Live controle of een naam vrij is (registratie en profiel). */
    public function naamCheck(Request $request): JsonResponse
    {
        $naam = mb_substr(trim((string) $request->query('naam', '')), 0, 80);
        $uid = $request->user()?->id;
        if ($naam === '') {
            return response()->json(['vrij' => true]);
        }
        $email = $request->user()?->email ?? (is_string($request->query('email')) ? $request->query('email') : null);
        if (Naam::lijktOpEmail($naam, $email)) {
            return response()->json(['vrij' => false, 'email' => true, 'melding' => Naam::MELDING_EMAIL, 'suggesties' => []]);
        }
        if ($uid !== null && Naam::normaal($naam) === Naam::normaal((string) $request->user()->name)) {
            return response()->json(['vrij' => true]);
        }
        if (! Naam::bezet($naam, $uid !== null ? (int) $uid : null)) {
            return response()->json(['vrij' => true]);
        }

        return response()->json([
            'vrij' => false,
            'melding' => Naam::melding($naam, $uid !== null ? (int) $uid : null),
            'suggesties' => Naam::suggesties($naam, 2, $uid !== null ? (int) $uid : null),
        ]);
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

    /**
     * Kleine preview: postcode → woonplaats (geen opslag, geen PII in logs).
     */
    public function locatiePreview(Request $request, LocationGeocoder $geocoder): JsonResponse
    {
        $country = Country::normalize((string) $request->query('country', Country::NL));
        $postcode = trim((string) $request->query('postcode', ''));

        if ($postcode === '') {
            return response()->json(['ok' => false, 'error' => 'Vul een postcode in.']);
        }
        if (! PostalCode::looksLikePostcode($postcode, $country)) {
            return response()->json(['ok' => false, 'error' => 'Geen geldige postcode.']);
        }
        $valid = PostalCode::validate($postcode, $country);
        if ($valid !== true) {
            return response()->json(['ok' => false, 'error' => $valid]);
        }

        $resolved = $geocoder->resolve($postcode, $country, true);
        if (! ($resolved['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $resolved['error'] ?? 'Niet gevonden.'], 422);
        }

        $gebied = PostalCode::gebied($postcode, $country)
            ?? PostalCode::gebied((string) ($resolved['postcode'] ?? ''), $country);
        $woonplaats = trim((string) ($resolved['woonplaats'] ?? ''));
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel((string) ($resolved['label'] ?? ''), $postcode);
        }

        return response()->json([
            'ok' => true,
            'postcode_gebied' => $gebied,
            'woonplaats' => $woonplaats,
            'label' => PostalCode::publicLabel($gebied, $woonplaats !== '' ? $woonplaats : null),
        ]);
    }
};
