<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

final class ThrottleMessage
{
    /**
     * Nederlandse melding met wachttijd op basis van RateLimiter-key.
     */
    public static function forKey(string $key): string
    {
        return self::forSeconds(RateLimiter::availableIn($key));
    }

    public static function forSeconds(int $seconds): string
    {
        $seconds = max(1, $seconds);
        // availableIn is vaak decay-1 (3599 i.p.v. 3600) → toon uren i.p.v. «60 minuten».
        $minutes = (int) ceil($seconds / 60);

        if ($minutes >= 60) {
            $hours = (int) ceil($seconds / 3600);

            return $hours === 1
                ? 'Even te veel pogingen. Probeer het over een uur opnieuw.'
                : "Even te veel pogingen. Probeer het over {$hours} uur opnieuw.";
        }

        if ($minutes >= 1 && $seconds >= 60) {
            return $minutes === 1
                ? 'Even te veel pogingen. Probeer het over een minuut opnieuw.'
                : "Even te veel pogingen. Probeer het over {$minutes} minuten opnieuw.";
        }

        return $seconds === 1
            ? 'Even te veel pogingen. Probeer het over een seconde opnieuw.'
            : "Even te veel pogingen. Probeer het over {$seconds} seconden opnieuw.";
    }

    /**
     * Wachttijd-melding specifiek voor mail opnieuw versturen.
     */
    public static function forMailResend(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds >= 3600) {
            $hours = (int) ceil($seconds / 3600);

            return $hours === 1
                ? 'Je kunt over 1 uur opnieuw een mail laten sturen.'
                : "Je kunt over {$hours} uur opnieuw een mail laten sturen.";
        }

        if ($seconds >= 60) {
            $minutes = (int) ceil($seconds / 60);

            return $minutes === 1
                ? 'Je kunt over 1 minuut opnieuw een mail laten sturen.'
                : "Je kunt over {$minutes} minuten opnieuw een mail laten sturen.";
        }

        return $seconds === 1
            ? 'Je kunt over 1 seconde opnieuw een mail laten sturen.'
            : "Je kunt over {$seconds} seconden opnieuw een mail laten sturen.";
    }
}
