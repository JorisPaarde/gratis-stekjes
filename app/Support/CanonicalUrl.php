<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonieke absolute URL's altijd uit APP_URL / site-url, nooit uit de request-host.
 */
final class CanonicalUrl
{
    public static function root(): string
    {
        return rtrim((string) config('app.url', ''), '/');
    }

    /**
     * Absolute URL voor een pad (zonder query). Lege / root → site-root.
     */
    public static function absolute(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $root = self::root();
        if ($root === '') {
            return $path === '/' ? '/' : $path;
        }

        return $path === '/' ? $root : $root.$path;
    }

    /**
     * Canonieke URL voor het huidige request-pad, query genegeerd.
     */
    public static function forRequest(?\Illuminate\Http\Request $request = null): string
    {
        $request ??= request();
        $path = $request->getPathInfo() ?: '/';
        // /index.php en /index.php/... horen niet in canonicals.
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return self::absolute($path);
    }

    public static function asset(string $path): string
    {
        return self::absolute('/'.ltrim($path, '/'));
    }
}
