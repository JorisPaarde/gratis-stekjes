<?php

declare(strict_types=1);

/**
 * Sitenaam en basis-URL op één plek (config). Nergens hardcoden.
 */

function stekjes_site_name(): string
{
    return (string) (stekjes_config()['site_name'] ?? 'Gratis stekjes');
}

function stekjes_site_base_url(): string
{
    $url = (string) (stekjes_config()['site_url'] ?? '');
    if ($url === '') {
        $url = 'http://127.0.0.1';
    }
    return rtrim($url, '/');
}

function stekjes_base_path(): string
{
    $p = (string) (stekjes_config()['base_path'] ?? '');
    if ($p === '' || $p === '/') {
        return '';
    }
    return rtrim($p, '/');
}

function stekjes_path(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return stekjes_base_path() . $path;
}

function stekjes_abs_url(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    // site_url bevat al origin; base_path zit vaak in de subdirectory van site_url
    $base = stekjes_site_base_url();
    $prefix = stekjes_base_path();
    if ($prefix !== '' && str_ends_with($base, $prefix)) {
        return $base . $path;
    }
    return $base . $prefix . $path;
}
