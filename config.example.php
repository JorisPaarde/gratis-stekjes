<?php

declare(strict_types=1);

/**
 * Voorbeeldconfig. Op de server schrijft de deploy-workflow dit bestand
 * BUITEN de webroot (pad via secrets), chmod 600. Nooit committen.
 * Versie: 0.2.0
 *
 * SITE_NAME en BASE_URL alleen hier / via environment — nergens hardcoded in de app.
 */

return [
    'site_name' => getenv('SITE_NAME') ?: 'Gratis stekjes',
    'site_url' => rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1', '/'),
    'base_path' => getenv('BASE_PATH') ?: '',

    'db_driver' => getenv('DB_DRIVER') ?: 'mysql',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: '',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_charset' => 'utf8mb4',
    'db_path' => getenv('DB_PATH') ?: '',

    'mail' => [
        'from_email' => getenv('SMTP_FROM') ?: '',
        'from_name' => getenv('SITE_NAME') ?: 'Gratis stekjes',
        'smtp_host' => getenv('SMTP_HOST') ?: '',
        'smtp_port' => (int) (getenv('SMTP_PORT') ?: 465),
        'smtp_encryption' => strtolower(getenv('SMTP_SECURE') ?: 'ssl'),
        'smtp_user' => getenv('SMTP_USER') ?: '',
        'smtp_pass' => getenv('SMTP_PASS') ?: '',
    ],

    'admin_password' => getenv('ADMIN_PASSWORD') ?: '',
    'cron_token' => getenv('CRON_TOKEN') ?: '',

    'form_min_seconds' => 3,
    'rate_limit' => [
        'register' => ['max' => 5, 'window' => 3600],
        'login' => ['max' => 20, 'window' => 3600],
        'message' => ['max' => 60, 'window' => 3600],
        'search' => ['max' => 120, 'window' => 3600],
        'password_reset' => ['max' => 5, 'window' => 3600],
    ],
    'rate_limit_retention_seconds' => 172800,

    'session_name' => 'stekjes_sess',
    'session_secure' => (getenv('SESSION_SECURE') ?: '1') === '1',

    'pdok_url' => 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free',
    'coord_decimals' => 3,
    'meedoen_teller_drempel' => 25,

    'theme_color' => '#2f6b45',
    'background_color' => '#eef4ea',

    'private_path' => getenv('PRIVATE_PATH') ?: '',
];
