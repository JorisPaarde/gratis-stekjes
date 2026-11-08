<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisatie en bruikbaarheid van de openbare plantnaam (`aanbod.tekst`).
 *
 * Unicode-whitespace (incl. NBSP e.d.) telt als leeg; hoeveelheid is géén naam.
 */
final class AanbodTekst
{
    public const FOUT_VERPLICHT = 'Vul de naam van je plant in.';

    /** Trim alle Unicode-whitespace aan begin/eind (PCRE `/u` + `\s`). */
    public static function normalize(string $tekst): string
    {
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $tekst);

        return is_string($trimmed) ? $trimmed : trim($tekst);
    }

    /** True als er na normalisatie nog een zichtbare naam overblijft. */
    public static function isUsable(string $tekst): bool
    {
        return self::normalize($tekst) !== '';
    }
}
