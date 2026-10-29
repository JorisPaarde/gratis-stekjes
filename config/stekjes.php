<?php

declare(strict_types=1);

return [
    /** Minimale invultijd (seconden) voor auth-formulieren tegen bots. */
    'form_min_seconds' => (int) env('FORM_MIN_SECONDS', 3),

    /**
     * Bekende wegwerp-domeinen (kleine startlijst; uitbreiden later).
     * Lege registratie weigeren met vaste foutmelding.
     */
    'disposable_email_domains' => [
        'mailinator.com',
        'guerrillamail.com',
        'guerrillamail.net',
        '10minutemail.com',
        'tempmail.com',
        'yopmail.com',
        'trashmail.com',
        'sharklasers.com',
    ],
];
