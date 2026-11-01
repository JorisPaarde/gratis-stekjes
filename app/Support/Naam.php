<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Voornaam of bijnaam is uniek (hoofdletters en spaties tellen niet mee).
 * Bij een bezette naam stellen we vrije varianten voor, zoals Bert2.
 */
final class Naam
{
    public static function normaal(string $naam): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $naam) ?? $naam));
    }

    public const MELDING_EMAIL = 'Gebruik een voornaam of bijnaam, geen e-mailadres. Je naam zien anderen; je e-mailadres blijft privé.';

    /**
     * Lijkt de naam op een e-mailadres (met @ of een maildomein)? Dan niet als openbare naam.
     */
    public static function lijktOpEmail(string $naam, ?string $email = null): bool
    {
        $n = self::normaal($naam);
        if ($n === '') {
            return false;
        }
        if (str_contains($n, '@')) {
            return true;
        }
        // Domein in de naam: gmail.com, hotmail.nl, iets.be …
        if (preg_match('/\.(com|nl|be|net|org|eu|info|io|de|fr)\b/u', $n) || preg_match('/\b(gmail|hotmail|outlook|live|yahoo|icloud|kpnmail|ziggo|telenet|skynet|proton(mail)?)\b/u', $n)) {
            return true;
        }

        return false;
    }

    public static function bezet(string $naam, ?int $behalveUserId = null): bool
    {
        $n = self::normaal($naam);
        if ($n === '') {
            return false;
        }

        return User::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$n])
            ->when($behalveUserId !== null, fn ($q) => $q->whereKeyNot($behalveUserId))
            ->exists();
    }

    /** @return list<string> */
    public static function suggesties(string $naam, int $aantal = 2, ?int $behalveUserId = null): array
    {
        $basis = trim(preg_replace('/\s+/u', ' ', $naam) ?? $naam);
        $basis = (string) preg_replace('/\d+$/', '', $basis);
        $basis = mb_substr($basis !== '' ? $basis : 'Plantenvriend', 0, 70);

        $uit = [];
        for ($i = 2; count($uit) < $aantal && $i < 200; $i++) {
            $kandidaat = $basis.$i;
            if (! self::bezet($kandidaat, $behalveUserId)) {
                $uit[] = $kandidaat;
            }
        }

        return $uit;
    }

    public static function melding(string $naam, ?int $behalveUserId = null): string
    {
        $s = self::suggesties($naam, 2, $behalveUserId);
        $voorbeeld = trim(preg_replace('/\s+/u', ' ', $naam) ?? $naam);

        return 'Er is al iemand met de naam «'.$voorbeeld.'». Kies bijvoorbeeld '
            .implode(' of ', array_map(static fn ($x) => '«'.$x.'»', $s))
            .', of voeg een letter toe, zoals «'.$voorbeeld.' B.».';
    }
}
