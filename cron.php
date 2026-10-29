<?php

declare(strict_types=1);

/**
 * Beveiligd cron-endpoint. Aanroepen met header X-Cron-Token (constant-time).
 * Jobs: herinneringsmails voor aanbod dat over ≤7 dagen verloopt.
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
$jobs['expiry_reminders'] = stekjes_job_expiry_reminders();

$ran = [
    'ok' => true,
    'version' => STEKJES_VERSION,
    'jobs' => $jobs,
    'at' => stekjes_now(),
];

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($ran, JSON_UNESCAPED_UNICODE);
