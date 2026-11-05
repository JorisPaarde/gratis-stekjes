<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Country;
use App\Support\Geo;
use App\Support\PostalCode;

/**
 * Gedeelde postcode→locatie-logica voor registratie en profiel.
 */
final class UserLocatieService
{
    public function __construct(private readonly LocationGeocoder $geocoder) {}

    /**
     * Zet postcode (NL/BE) om in afgeronde locatievelden op de user (nog niet saved).
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function applyPostcode(User $user, string $postcode, string $country, bool $allowNetwork = true): array
    {
        $country = Country::normalize($country);
        $postcode = trim($postcode);

        if (! PostalCode::looksLikePostcode($postcode, $country)) {
            return [
                'ok' => false,
                'error' => $country === Country::BE
                    ? 'Belgische postcode bestaat uit precies 4 cijfers (bijv. 9000).'
                    : 'Nederlandse postcode: 4 cijfers, optioneel gevolgd door 2 letters (bijv. 1234 AB).',
            ];
        }

        $pcValid = PostalCode::validate($postcode, $country);
        if ($pcValid !== true) {
            return ['ok' => false, 'error' => is_string($pcValid) ? $pcValid : 'Deze postcode ken ik niet.'];
        }

        $postcodeVol = PostalCode::normalize($postcode, $country);
        // Zelfde query als het profiel (ruwe invoer), zodat geocode-cache/PDOK matchen.
        $resolved = $this->geocoder->resolve($postcode, $country, $allowNetwork);
        if (! ($resolved['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'Locatie niet gevonden.')];
        }

        $woonplaats = trim((string) ($resolved['woonplaats'] ?? ''));
        if ($woonplaats === '') {
            $woonplaats = PostalCode::plaatsFromGeoLabel((string) ($resolved['label'] ?? ''), (string) $postcodeVol);
        }
        if ($woonplaats === '') {
            return [
                'ok' => false,
                'error' => 'Deze postcode ken ik, maar de plaatsnaam niet. Probeer het later opnieuw.',
            ];
        }

        $postcodeGebied = PostalCode::gebied((string) ($resolved['postcode'] ?? ''), $country)
            ?? PostalCode::gebied((string) $postcodeVol, $country);

        $user->fill([
            'country' => $country,
            'locatie_label' => PostalCode::publicLabel($postcodeGebied, $woonplaats),
            'postcode_gebied' => $postcodeGebied,
            'woonplaats' => mb_substr($woonplaats, 0, 80),
            'lat' => Geo::roundCoord((float) $resolved['lat']),
            'lon' => Geo::roundCoord((float) $resolved['lon']),
        ]);

        return ['ok' => true];
    }
}
