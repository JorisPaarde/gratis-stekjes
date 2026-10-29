<?php

declare(strict_types=1);

namespace App\Support;

final class FormGuard
{
    public static function honeypotFilled(?string $website): bool
    {
        return trim((string) $website) !== '';
    }

    public static function submittedTooFast(mixed $formStarted): bool
    {
        $min = (int) config('stekjes.form_min_seconds', 3);
        $started = (int) $formStarted;
        if ($started <= 0) {
            return true;
        }

        return (time() - $started) < $min;
    }

    public static function isDisposableEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $domain = substr($email, $at + 1);
        $blocked = config('stekjes.disposable_email_domains', []);

        return is_array($blocked) && in_array($domain, $blocked, true);
    }
}
