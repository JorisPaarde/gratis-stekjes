<?php

declare(strict_types=1);

$versionFile = dirname(__DIR__).'/VERSION';
$version = is_readable($versionFile)
    ? trim((string) file_get_contents($versionFile))
    : '';

return [
    'name' => env('SITE_NAME', env('APP_NAME', 'Gratis planten')),
    'version' => $version !== '' ? $version : '0.0.0',
    'theme_color' => '#1F4D2B',
    'background_color' => '#E6F2E1',
    'description' => 'Gratis planten en stekjes delen in Nederland en België. Weggeven en ophalen bij jou in de buurt — de enige tegenprestatie is een praatje.',
    /** Publiek contactadres (zichtbaar in footer/privacy/contact; geen secret). */
    'contact_email' => env('SITE_CONTACT_EMAIL', 'info@gratisplanten.nl'),
    // Staging always noindex; production sets NOINDEX=false
    'noindex' => filter_var(env('NOINDEX', env('APP_ENV') === 'staging'), FILTER_VALIDATE_BOOL),
    /**
     * Canonieke productiehost. Geparkte aliases (gratisstekjes.nl) krijgen
     * via CanonicalHostRedirect een 301 hierheen (niet op staging/local).
     */
    'canonical_host' => env('CANONICAL_HOST', 'gratisplanten.nl'),
    /**
     * Standaard Open Graph / Twitter-beeld (bestaand logo in de repo).
     * Pad relatief t.o.v. public/; absolute URL via CanonicalUrl::asset().
     */
    'og_image' => '/assets/icons/icon-512.png',
];
