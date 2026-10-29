<?php

declare(strict_types=1);

$versionFile = dirname(__DIR__).'/VERSION';
$version = is_readable($versionFile)
    ? trim((string) file_get_contents($versionFile))
    : '';

return [
    'name' => env('SITE_NAME', env('APP_NAME', 'Gratis planten')),
    'version' => $version !== '' ? $version : '0.0.0',
    'theme_color' => '#1f4d2b',
    'background_color' => '#e6f2e1',
    'description' => 'Gratis planten en stekjes weggeven en ophalen. De enige tegenprestatie is een praatje.',
    /** Publiek contactadres (zichtbaar in footer/privacy/contact; geen secret). */
    'contact_email' => env('SITE_CONTACT_EMAIL', 'info@gratisplanten.nl'),
    // Staging always noindex; production sets NOINDEX=false
    'noindex' => filter_var(env('NOINDEX', env('APP_ENV') === 'staging'), FILTER_VALIDATE_BOOL),
];
