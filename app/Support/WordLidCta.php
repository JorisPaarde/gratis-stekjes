<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Centrale CTA «Word lid» / «Bied iets aan».
 * Gast-URL gaat via GET /ga/word-lid (release A) met utm_content per plek.
 */
final class WordLidCta
{
    public static function label(?User $user = null): string
    {
        $user ??= auth()->user();

        return $user instanceof User ? 'Bied iets aan' : 'Word lid';
    }

    public static function href(string $utmContent, ?User $user = null, ?string $from = null): string
    {
        $user ??= auth()->user();
        if ($user instanceof User) {
            return route('aanbieden');
        }

        return self::guestUrl($utmContent, $from);
    }

    /** Gast-URL via /ga/word-lid (cta_click) met utm_content. */
    public static function guestUrl(string $utmContent, ?string $from = null): string
    {
        $from = $from ?? (request()->getPathInfo() ?: '/');
        $params = [
            'from' => $from,
            'utm_content' => $utmContent,
            'utm_source' => 'site',
            'utm_medium' => 'cta',
        ];

        if (Route::has('ga.word-lid')) {
            return route('ga.word-lid', $params);
        }

        return route('account.registreren', $params);
    }
}
