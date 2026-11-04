<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Postcodes NL (1234 AB) en BE (4 cijfers, heel België 1000–9999).
 * Openbaar label is altijd «postcodegebied plaats» (bijv. «1326 Almere»), nooit een
 * volledige postcode of huisnummer.
 */
final class PostalCode
{
    /**
     * Geldige Belgische postcode (1000–9999).
     */
    public static function isBelgianPostcode(int|string $pc): bool
    {
        $n = self::digits4($pc);

        return $n !== null && $n >= 1000 && $n <= 9999;
    }

    /**
     * Normaliseer voor opslag. Null = ongeldig formaat.
     *
     * NL: "1234 AB" / "1234ab" → "1234AB"; alleen "1234" → "1234" (bestaande PC4).
     * BE: "9000" / "90 00" → "9000".
     */
    public static function normalize(string $input, string $country): ?string
    {
        $country = Country::normalize($country);
        $input = trim($input);

        if ($country === Country::BE) {
            $digits = preg_replace('/\s+/', '', $input) ?? '';
            if (! preg_match('/^\d{4}$/', $digits)) {
                return null;
            }
            if (! self::isBelgianPostcode($digits)) {
                return null;
            }

            return $digits;
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

    /** Weergave per land (volledige postcode; niet voor openbaar label). */
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
     * Eerste 4 cijfers van een genormaliseerde postcode (= openbaar postcodegebied).
     */
    public static function gebied(?string $normalizedOrRaw, string $country = Country::NL): ?string
    {
        if ($normalizedOrRaw === null || trim($normalizedOrRaw) === '') {
            return null;
        }
        $normalized = self::normalize(trim($normalizedOrRaw), $country)
            ?? (preg_match('/^(\d{4})/', trim($normalizedOrRaw), $m) ? $m[1] : null);
        if ($normalized === null) {
            return null;
        }
        $digits = substr(preg_replace('/\D/', '', $normalized) ?? '', 0, 4);

        return strlen($digits) === 4 ? $digits : null;
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
            $digits = preg_replace('/\s+/', '', $input) ?? '';
            if (! preg_match('/^\d{4}$/', $digits)) {
                return 'Belgische postcode bestaat uit precies 4 cijfers (bijv. 9000).';
            }
            if (! self::isBelgianPostcode($digits)) {
                return 'Belgische postcode moet tussen 1000 en 9999 liggen.';
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
     * Openbaar locatielabel: «1326 Almere», of alleen de plaats bij fallback zonder postcode.
     * Nooit een volledige postcode (1234 AB) of huisnummer.
     */
    public static function publicLabel(?string $postcodeGebied, ?string $woonplaats): string
    {
        $gebied = trim((string) $postcodeGebied);
        $plaats = trim((string) $woonplaats);

        if ($gebied !== '' && preg_match('/^\d{4}$/', $gebied) && $plaats !== '') {
            return mb_substr($gebied.' '.$plaats, 0, 120);
        }
        if ($plaats !== '') {
            return mb_substr($plaats, 0, 80);
        }
        if ($gebied !== '' && preg_match('/^\d{4}$/', $gebied)) {
            return $gebied;
        }

        return '';
    }

    /**
     * Bouw publiek locatielabel uit ruwe invoer + geocoder-label (profiel/legacy).
     * Bij postcode → postcodegebied (+ plaats uit geo indien bekend).
     * Bij plaatsnaam → alleen de plaats.
     */
    public static function locationLabel(string $raw, string $country, ?string $geoLabel = null, ?string $woonplaats = null): string
    {
        $country = Country::normalize($country);
        $raw = trim($raw);
        $plaats = trim((string) ($woonplaats ?? ''));
        if ($plaats === '' && $geoLabel !== null && trim($geoLabel) !== '') {
            $plaats = self::plaatsFromGeoLabel($geoLabel, $raw);
        }

        $normalized = self::normalize($raw, $country);
        if ($normalized !== null) {
            $gebied = self::gebied($normalized, $country);

            return self::publicLabel($gebied, $plaats !== '' ? $plaats : null);
        }

        if ($plaats !== '') {
            return self::publicLabel(null, $plaats);
        }

        return mb_substr($raw, 0, 80);
    }

    /**
     * Haal woonplaats uit een geocoder-label (PDOK/Nominatim), zonder straat/huisnummer.
     */
    public static function plaatsFromGeoLabel(string $geoLabel, ?string $queryHint = null): string
    {
        $geoLabel = trim($geoLabel);
        if ($geoLabel === '') {
            return '';
        }

        // Alleen 4 cijfers → geen plaats.
        if (preg_match('/^\d{4}$/', $geoLabel)) {
            return '';
        }

        // «2037KB, Haarlem» / «Adlerstraat, 2037KB Haarlem» / «1326 Almere»
        if (preg_match('/\b\d{4}\s*[A-Za-z]{0,2},?\s+(.+)$/u', $geoLabel, $m)) {
            $plaats = trim(explode(',', $m[1])[0]);

            return mb_substr(self::stripHouseNumber($plaats), 0, 80);
        }

        // «Haarlem, Noord-Holland, Nederland» of Nominatim display
        $first = trim(explode(',', $geoLabel)[0]);
        $first = self::stripHouseNumber($first);
        if ($queryHint !== null && self::normalize($queryHint, Country::NL) !== null) {
            // eerste deel was misschien de postcode zelf
            if (preg_match('/^\d{4}/', $first)) {
                return '';
            }
        }

        return mb_substr($first, 0, 80);
    }

    /** Ziet de invoer eruit als postcode voor dit land? */
    public static function looksLikePostcode(string $input, string $country): bool
    {
        $country = Country::normalize($country);
        $input = trim($input);

        if ($country === Country::BE) {
            $digits = preg_replace('/\s+/', '', $input) ?? '';

            return (bool) preg_match('/^\d{4}$/', $digits);
        }

        return (bool) preg_match('/^\d{4}(\s*[A-Za-z]{2})?$/', $input);
    }

    /** Label of tekst bevat een huisnummer (half adres). */
    public static function looksLikeAddressWithNumber(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }
        // «Straat 12», «Kerkstraat 12A», «12 Kerkstraat»
        if (preg_match('/\b\d{1,5}\s*[A-Za-z]?\b/u', $label) && preg_match('/[A-Za-zÀ-ÿ]{2,}/u', $label)) {
            // Geen kale postcode+plaats («1326 Almere»): 4 cijfers + één woord zonder huisnummer-patroon ergens anders
            if (preg_match('/^\d{4}\s+[A-Za-zÀ-ÿ\'\-\s]+$/u', $label) && ! preg_match('/\b\d{1,5}\s*[A-Za-z]\b/u', $label)) {
                return false;
            }
            if (preg_match('/^\d{4}\s+[A-Za-zÀ-ÿ\'\-]+$/u', $label)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private static function stripHouseNumber(string $s): string
    {
        $s = trim($s);
        // «Kerkstraat 12A» → liever niet als woonplaats; maar «Haarlem» blijft
        if (preg_match('/^(.+?)\s+\d{1,5}[A-Za-z]?$/u', $s, $m) && preg_match('/\s/', $m[1])) {
            return trim($m[1]);
        }

        return $s;
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
};
