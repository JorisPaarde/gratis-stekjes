<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisatie en bruikbaarheid van de openbare plantnaam (`aanbod.tekst`).
 *
 * FORM-whitespace is een expliciete Unicode-set die server (PHP) en client (JS)
 * delen — niet JS `\s` (mist o.a. U+0085 NEL) en niet «alles wat PHP `\s` ooit
 * doet» zonder documentatie. Publieke lijstfilters gebruiken {@see isUsable()}
 * via dezezelfde normalisatie; géén aparte alias-/duplicate-filteruitbreiding.
 */
final class AanbodTekst
{
    public const FOUT_VERPLICHT = 'Vul de naam van je plant in.';

    /**
     * Expliciete FORM-whitespace (PCRE `/u` character class body).
     * Bevat o.a. TAB/LF/VT/FF/CR/SPACE, NEL (U+0085), NBSP, Ogham space,
     * EN/EM quad…hair spaces, LS/PS, NNBSP, MMSP, ideographic space, FEFF.
     */
    public const FORM_WS_PCRE = '\t\n\v\f\r\x{0020}\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    /**
     * Zelfde set als {@see FORM_WS_PCRE}, in JS character-class syntax (`u` flag).
     * Gebruikt door account form-scripts voor unicodeTrim / customValidity.
     */
    public const FORM_WS_JS = '\t\n\v\f\r \u0085\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000\uFEFF';

    /**
     * Codepoints die in regressietests moeten vallen (lege naam na normalize).
     *
     * @return list<string>
     */
    public static function formWhitespaceCodepoints(): array
    {
        return [
            "\t", "\n", "\v", "\f", "\r", ' ',
            "\u{0085}", // NEL
            "\u{00A0}", // NBSP
            "\u{1680}",
            "\u{2000}", "\u{2001}", "\u{2002}", "\u{2003}", "\u{2004}",
            "\u{2005}", "\u{2006}", "\u{2007}", "\u{2008}", "\u{2009}", "\u{200A}",
            "\u{2028}", "\u{2029}",
            "\u{202F}", "\u{205F}", "\u{3000}",
            "\u{FEFF}",
        ];
    }

    /** Trim FORM-whitespace aan begin/eind (gedeelde serverdefinitie). */
    public static function normalize(string $tekst): string
    {
        $pattern = '/^['.self::FORM_WS_PCRE.']+|['.self::FORM_WS_PCRE.']+$/u';
        $trimmed = preg_replace($pattern, '', $tekst);

        return is_string($trimmed) ? $trimmed : trim($tekst);
    }

    /** True als er na normalisatie nog een zichtbare naam overblijft. */
    public static function isUsable(string $tekst): bool
    {
        return self::normalize($tekst) !== '';
    }
}
