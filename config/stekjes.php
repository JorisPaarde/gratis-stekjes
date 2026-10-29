<?php

declare(strict_types=1);

return [
    /** Minimale invultijd (seconden) voor auth-formulieren tegen bots. */
    'form_min_seconds' => (int) env('FORM_MIN_SECONDS', 3),

    /** Gebundelde MIT-lijst: resources/data/disposable/domains.txt */
    'disposable_domains_path' => resource_path('data/disposable/domains.txt'),
];
