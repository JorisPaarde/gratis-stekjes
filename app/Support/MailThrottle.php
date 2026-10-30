<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Gedeelde mail-throttle: max 1 per 60 s en 5 per uur, per genormaliseerd
 * e-mailadres én per IP (voor bevestiging-opnieuw, wachtwoord-vergeten, e-mailwijziging).
 */
final class MailThrottle
{
    public const BURST_MAX = 1;

    public const BURST_DECAY = 60;

    public const HOUR_MAX = 5;

    public const HOUR_DECAY = 3600;

    /**
     * @return array{ok: bool, message: ?string}
     */
    public static function attempt(string $scope, string $email, Request $request): array
    {
        $email = Str::lower(trim($email));
        $ip = (string) $request->ip();

        /** @var array<string, array{0: int, 1: int}> $keys */
        $keys = [
            "mail:{$scope}:burst:email:{$email}" => [self::BURST_MAX, self::BURST_DECAY],
            "mail:{$scope}:hour:email:{$email}" => [self::HOUR_MAX, self::HOUR_DECAY],
            "mail:{$scope}:burst:ip:{$ip}" => [self::BURST_MAX, self::BURST_DECAY],
            "mail:{$scope}:hour:ip:{$ip}" => [self::HOUR_MAX, self::HOUR_DECAY],
        ];

        $wait = 0;
        foreach ($keys as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = max($wait, RateLimiter::availableIn($key));
            }
        }

        if ($wait > 0) {
            return [
                'ok' => false,
                'message' => ThrottleMessage::forMailResend($wait),
            ];
        }

        foreach ($keys as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return ['ok' => true, 'message' => null];
    }
}
