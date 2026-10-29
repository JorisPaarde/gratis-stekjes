<?php

declare(strict_types=1);

namespace App\Support;

final class FormGuard
{
    /** @var array<string, true>|null */
    private static ?array $disposableDomains = null;

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
        if ($domain === '') {
            return false;
        }

        $domains = self::disposableDomains();
        if (isset($domains[$domain])) {
            return true;
        }

        // Parent-domeinen (bijv. a.b.mailinator.com)
        $parts = explode('.', $domain);
        while (count($parts) > 2) {
            array_shift($parts);
            $parent = implode('.', $parts);
            if (isset($domains[$parent])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private static function disposableDomains(): array
    {
        if (self::$disposableDomains !== null) {
            return self::$disposableDomains;
        }

        $path = (string) config('stekjes.disposable_domains_path', resource_path('data/disposable/domains.txt'));
        $set = [];
        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = strtolower(trim($line));
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $set[$line] = true;
            }
        }

        return self::$disposableDomains = $set;
    }
}
