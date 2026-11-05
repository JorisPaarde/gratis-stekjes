<?php

declare(strict_types=1);

return [
    /** Ruwe events bewaren (dagen); daarna alleen conversion_daily. */
    'raw_retention_days' => (int) env('CONVERSION_RAW_RETENTION_DAYS', 30),

    /** Flow-id cookie + user.conversion_flow_id (dagen). */
    'flow_ttl_days' => (int) env('CONVERSION_FLOW_TTL_DAYS', 7),

    /** Cookie met flow-id + first-touch herkomst (functioneel, geen tracking van derden). */
    'flow_cookie' => env('CONVERSION_FLOW_COOKIE', 'gp_flow'),

    /** Dagelijkse HMAC-secret-afgeleide; valt terug op APP_KEY. */
    'visitor_hash_secret' => env('CONVERSION_VISITOR_HASH_SECRET', ''),

    /** Onder dit aantal form_starts geen percentagebesluit in het dashboard. */
    'min_form_starts_for_rates' => (int) env('CONVERSION_MIN_FORM_STARTS', 30),

    /** Beacon rate-limit (requests per minuut per IP-hash-bucket). */
    'beacon_per_minute' => (int) env('CONVERSION_BEACON_PER_MINUTE', 30),

    /** Opties voor «Hoe heb je ons gevonden?» */
    'found_via_options' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'zoeken' => 'Zoeken (Google e.d.)',
        'vriend' => 'Via iemand die ik ken',
        'organisatie' => 'Via een organisatie of initiatief',
        'anders' => 'Anders',
    ],
];
