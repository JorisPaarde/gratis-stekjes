<?php

declare(strict_types=1);

return [
    /** Minimale invultijd (seconden) voor auth-formulieren tegen bots. */
    'form_min_seconds' => (int) env('FORM_MIN_SECONDS', 3),

    /** Gebundelde MIT-lijst: resources/data/disposable/domains.txt */
    'disposable_domains_path' => resource_path('data/disposable/domains.txt'),

    /** Afronding coördinaten (privacy: geen exact adres). */
    'coord_decimals' => (int) env('COORD_DECIMALS', 3),

    /** PDOK Locatieserver free endpoint. */
    'pdok_url' => env('PDOK_URL', 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free'),

    /** Aanbod zichtbaar in zoeken/kaart (dagen). */
    'aanbod_ttl_days' => 60,

    /** Herinneringsmail zoveel dagen vóór expires_at. */
    'aanbod_reminder_days' => 7,

    /** Geldigheid eenklik-verlengtoken (dagen). */
    'aanbod_renew_token_days' => 14,

    /** Geocode-cache opruimen na zoveel dagen. */
    'geocode_cache_retention_days' => (int) env('GEOCODE_CACHE_RETENTION_DAYS', 90),

    /** Gedeeld secret voor /cron (header X-Cron-Token). */
    'cron_token' => env('CRON_TOKEN', ''),

    /** Types voor partnermarkers op de kaart. */
    'partner_types' => [
        'partner' => 'Partner',
        'voedselbos' => 'Voedselbos',
        'plantenasiel' => 'Plantenasiel',
        'plantenbieb' => 'Plantenbieb',
        'ruilkast' => 'Ruilkast',
    ],
];
