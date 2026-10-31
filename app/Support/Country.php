<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ondersteunde landen voor locatie/adres (NL + Vlaanderen/Brussel via BE).
 */
final class Country
{
    public const NL = 'NL';

    public const BE = 'BE';

    /** @return list<string> */
    public static function codes(): array
    {
        return [self::NL, self::BE];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::NL => 'Nederland',
            self::BE => 'België (Vlaanderen / Brussel)',
        ];
    }

    public static function isValid(?string $code): bool
    {
        return in_array(strtoupper(trim((string) $code)), self::codes(), true);
    }

    /** Normaliseer naar NL|BE; ongeldig/leeg → NL. */
    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return self::isValid($code) ? $code : self::NL;
    }

    public static function label(string $code): string
    {
        $code = self::normalize($code);

        return self::labels()[$code];
    }
}
