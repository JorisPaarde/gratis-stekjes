<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Postcodes NL (1234 AB) en BE (4 cijfers, alleen Vlaanderen + Brussel).
 *
 * BE toegestaan: 1000–1299, 1500–3999, 8000–9999.
 * BE geweigerd (Wallonië e.d.): 1300–1499, 4000–7999.
 */
final class PostalCode
{
    public const OUTSIDE_FLANDERS_MESSAGE = 'Dit Belgische adres ligt buiten Vlaanderen en het Brussels Hoofdstedelijk Gewest. Gratis planten is beschikbaar in Nederland, Vlaanderen en Brussel — niet in Wallonië.';

    /**
     * Vlaanderen + Brussels Hoofdstedelijk Gewest (postcodereeksen).
     */
    public static function isFlandersPostcode(int|string $pc): bool
    {
        $n = self::digits4($pc);
        if ($n === null) {
            return false;
        }

        return ($n >= 1000 && $n <= 1299)
            || ($n >= 1500 && $n <= 3999)
            || ($n >= 8000 && $n <= 9999);
    }

    /** Wallonië / uitgesloten Belgische reeksen. */
    public static function isExcludedBelgianPostcode(int|string $pc): bool
    {
        $n = self::digits4($pc);
        if ($n === null) {
            return false;
        }

        return ($n >= 1300 && $n <= 1499)
            || ($n >= 4000 && $n <= 7999);
    }

    /**
     * Normaliseer voor opslag. Null = ongeldig formaat (niet: Wallonië — dat is apart).
     *
     * NL: "1234 AB" / "1234ab" → "1234AB"; alleen "1234" → "1234" (bestaande PC4).
     * BE: "9000" → "9000".
     */
    public static function normalize(string $input, string $country): ?string
    {
        $country = Country::normalize($country);
        $input = trim($input);

        if ($country === Country::BE) {
            if (! preg_match('/^\d{4}$/', $input)) {
                return null;
            }

            return $input;
        }

        // NL
        if (preg_match('/^(\d{4})\s*([A-Za-z]{2})$/', $input, $m)) {
            return $m[1].strtoupper($m[2]);
        }
        if (preg_match('/^(\d{4})$/', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Weergave per land. */
    public static function format(string $normalized, string $country): string
    {
        $country = Country::normalize($country);
        $normalized = trim($normalized);

        if ($country === Country::NL && preg_match('/^(\d{4})([A-Z]{2})$/', $normalized, $m)) {
            return $m[1].' '.$m[2];
        }

        return $normalized;
    }

    /**
     * Valideer postcode voor gekozen land.
     *
     * @return true|string true of foutmelding
     */
    public static function validate(string $input, string $country): true|string
    {
        $country = Country::normalize($country);
        $input = trim($input);

        if ($input === '') {
            return 'Vul een postcode in.';
        }

        if ($country === Country::BE) {
            if (! preg_match('/^\d{4}$/', $input)) {
                return 'Belgische postcode bestaat uit precies 4 cijfers (bijv. 9000).';
            }
            if (self::isExcludedBelgianPostcode($input)) {
                return self::OUTSIDE_FLANDERS_MESSAGE;
            }
            if (! self::isFlandersPostcode($input)) {
                return 'Deze Belgische postcode ken ik niet als Vlaanderen of Brussel.';
            }

            return true;
        }

        // NL: 1234 of 1234 AB
        if (self::normalize($input, Country::NL) === null) {
            return 'Nederlandse postcode: 4 cijfers, optioneel gevolgd door 2 letters (bijv. 1234 AB).';
        }

        return true;
    }

    /**
     * Bouw publiek locatielabel (postcode of plaats) voor opslag/weergave.
     */
    public static function locationLabel(string $raw, string $country, ?string $geoLabel = null): string
    {
        $country = Country::normalize($country);
        $raw = trim($raw);

        $normalized = self::normalize($raw, $country);
        if ($normalized !== null) {
            return self::format($normalized, $country);
        }

        // Plaatsnaam: bij BE geocoder-label (gemeente/deelgemeente), anders eerste segment.
        if ($geoLabel !== null && trim($geoLabel) !== '') {
            $plaats = trim(explode(',', $geoLabel)[0]);

            return mb_substr($plaats, 0, 80);
        }

        return mb_substr($raw, 0, 80);
    }

    private static function digits4(int|string $pc): ?int
    {
        if (is_int($pc)) {
            return ($pc >= 1000 && $pc <= 9999) ? $pc : null;
        }
        $digits = preg_replace('/\D/', '', (string) $pc) ?? '';
        if (strlen($digits) < 4) {
            return null;
        }
        $n = (int) substr($digits, 0, 4);

        return ($n >= 1000 && $n <= 9999) ? $n : null;
    }
}
