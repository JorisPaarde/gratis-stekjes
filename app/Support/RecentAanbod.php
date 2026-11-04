<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Of een teaser-blok met recent/actueel aanbod getoond mag worden.
 *
 * Drempel: {@see config('gratisplanten.recent_aanbod_min')} (default 8).
 * Telling: actieve (zichtbaar, niet verlopen) aanbiedingen mét minstens één foto.
 * Scope bepaalt de caller (landelijk of straal rond stad).
 */
final class RecentAanbod
{
    public static function min(): int
    {
        return (int) config('gratisplanten.recent_aanbod_min', 8);
    }

    /** Alleen tonen als er genoeg actieve items-met-foto in de scope zijn. */
    public static function shouldShow(int $activeWithFotoCount): bool
    {
        return $activeWithFotoCount >= self::min();
    }
}
