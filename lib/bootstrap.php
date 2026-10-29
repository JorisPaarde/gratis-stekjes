<?php

declare(strict_types=1);

/**
 * Bootstrap: config + helpers. PHP 8.2+.
 * Versie: 0.2.0
 */

const STEKJES_VERSION = '0.2.0';

function stekjes_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $candidates = [];
    $env = getenv('STEKJES_CONFIG');
    if (is_string($env) && $env !== '') {
        $candidates[] = $env;
    }
    $candidates[] = dirname(__DIR__) . '/config.php';
    $candidates[] = dirname(__DIR__, 2) . '/stekjes-private/config.php';
    $candidates[] = dirname(__DIR__, 2) . '/private/stekjes-config.php';

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
                return $config;
            }
        }
    }

    $example = dirname(__DIR__) . '/config.example.php';
    if (is_file($example)) {
        $config = require $example;
        if (getenv('STEKJES_TEST_DB')) {
            $config['db_driver'] = 'sqlite';
            $config['db_path'] = getenv('STEKJES_TEST_DB');
            $config['session_secure'] = false;
            $config['mail']['smtp_pass'] = 'test';
            $config['admin_password'] = 'test-admin';
            $config['cron_token'] = 'test-cron-token';
            $config['site_name'] = getenv('SITE_NAME') ?: ($config['site_name'] ?? 'Test');
            $config['site_url'] = rtrim(getenv('BASE_URL') ?: ($config['site_url'] ?? 'http://127.0.0.1'), '/');
        }
        return $config;
    }

    throw new RuntimeException('Geen config.php gevonden. Kopieer config.example.php buiten de webroot.');
}

require_once __DIR__ . '/site.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/geo.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/disposable.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/privacy.php';
require_once __DIR__ . '/aanbod.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/avg.php';
require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/layout.php';
