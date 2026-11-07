<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Same-site return paths for auth (login/register/verify).
 * Blocks open redirects (//evil, foreign hosts) and normalizes Laravel’s absolute
 * url.intended values to a relative path.
 */
final class SafeIntended
{
    public const SESSION_KEY = 'url.intended';

    public const DEFAULT = '/account';

    /** Max length mirrors LoginRequest next rule (path only after normalize). */
    private const MAX_LENGTH = 200;

    /**
     * Allow only same-site destinations: relative "/…" or absolute same-origin URLs.
     * Returns a relative path (path + optional query), never a foreign URL.
     */
    public static function sanitize(?string $candidate, string $fallback = self::DEFAULT): string
    {
        $next = trim((string) $candidate);
        if ($next === '') {
            return $fallback;
        }

        if (str_starts_with($next, '//') || str_contains($next, '\\')) {
            return $fallback;
        }

        if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) {
            $root = rtrim((string) config('app.url'), '/');
            if ($root === '' || ! str_starts_with($next, $root.'/')) {
                // Also accept the current request root when available (tests / APP_URL mismatch).
                $requestRoot = app()->bound('request') ? rtrim((string) request()->root(), '/') : '';
                if ($requestRoot === '' || ! str_starts_with($next, $requestRoot.'/')) {
                    return $fallback;
                }
                $root = $requestRoot;
            }
            $path = substr($next, strlen($root));
            $next = $path === '' ? '/' : $path;
        }

        if (! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return $fallback;
        }
        if (str_contains($next, '://') || strlen($next) > self::MAX_LENGTH) {
            return $fallback;
        }

        return $next;
    }

    public static function isAllowed(?string $candidate): bool
    {
        $raw = trim((string) $candidate);
        if ($raw === '') {
            return false;
        }

        $sanitized = self::sanitize($raw, '');

        return $sanitized !== '' && ($sanitized === $raw || str_starts_with($raw, 'http'));
    }

    /**
     * Remember a safe destination in the session (Laravel redirect()->intended()).
     * Stores a relative path. Invalid/external values are ignored.
     */
    public static function remember(Request $request, ?string $candidate): void
    {
        if (! self::isAllowed($candidate)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, self::sanitize($candidate));
    }

    /**
     * Normalize an existing session intended (absolute → relative) when safe.
     */
    public static function normalizeSession(Request $request): void
    {
        $stored = $request->session()->get(self::SESSION_KEY);
        if (! is_string($stored) || $stored === '') {
            return;
        }
        if (! self::isAllowed($stored)) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }
        $path = self::sanitize($stored);
        if ($path !== $stored) {
            $request->session()->put(self::SESSION_KEY, $path);
        }
    }

    /**
     * Read query/body `next` when present and safe; otherwise null (do not invent a default).
     */
    public static function fromRequestInput(Request $request): ?string
    {
        if (! $request->has('next')) {
            return null;
        }

        $raw = (string) $request->input('next', '');
        if (! self::isAllowed($raw)) {
            return null;
        }

        return self::sanitize($raw);
    }

    public static function fromQuery(Request $request): ?string
    {
        if (! $request->query->has('next')) {
            return null;
        }

        $raw = (string) $request->query('next', '');
        if (! self::isAllowed($raw)) {
            return null;
        }

        return self::sanitize($raw);
    }

    public static function peek(Request $request): ?string
    {
        $stored = $request->session()->get(self::SESSION_KEY);
        if (! is_string($stored) || ! self::isAllowed($stored)) {
            return null;
        }

        return self::sanitize($stored);
    }
}
