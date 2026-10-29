<?php

declare(strict_types=1);

return [
    'name' => env('SITE_NAME', env('APP_NAME', 'Gratis planten')),
    'version' => '0.10.0',
    'theme_color' => '#1f6b74',
    'background_color' => '#ffffff',
    'description' => 'Gratis planten en stekjes weggeven en ophalen. De enige tegenprestatie is een praatje.',
    // Staging always noindex; production sets NOINDEX=false
    'noindex' => filter_var(env('NOINDEX', env('APP_ENV') === 'staging'), FILTER_VALIDATE_BOOL),
];
