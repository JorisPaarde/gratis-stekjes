<?php

declare(strict_types=1);

/**
 * Beveiligd cron-endpoint. Header: X-Cron-Token (constant-time).
 * Jobs: aanbod-herinneringen, rate-limit purge, geocode-cache purge.
 */

require_once __DIR__ . '/lib/bootstrap.php';

$config = stekjes_config();
$expected = (string) ($config['cron_token'] ?? '');
$given = (string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

$jobs = [];
try {
    $jobs['expiry_reminders'] = stekjes_job_expiry_reminders();
    $jobs['purge_rate_limits'] = ['deleted' => stekjes_purge_rate_limits()];
    $jobs['purge_geocode_cache'] = ['deleted' => stekjes_purge_geocode_cache()];
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => $e::class], JSON_UNESCAPED_UNICODE);
    exit;
}

$ran = [
    'ok' => true,
    'version' => STEKJES_VERSION,
    'jobs' => $jobs,
    'at' => stekjes_now(),
];

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($ran, JSON_UNESCAPED_UNICODE);
