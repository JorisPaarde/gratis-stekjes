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
];
