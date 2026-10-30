<?php

declare(strict_types=1);

/**
 * HTTPS deploy-hook voor cPanel noshell (geen Laravel-boot, geen Composer).
 *
 * Eenmalig handmatig in de docroot plaatsen samen met lib.php.
 * Config staat BUITEN de docroot: /home/<user>/.deploy/<env>.php
 * (token_hash, app_dir, docroot, env). Zie INSTALL.md.
 */

$lib = __DIR__.'/lib.php';
if (! is_file($lib)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"missing_lib"}';
    exit;
}
require $lib;

try {
    $config = deploy_hook_load_config(__DIR__);
    if ($config === null) {
        deploy_hook_json(503, ['ok' => false, 'error' => 'not_configured']);
        exit;
    }

    $result = deploy_hook_handle($config);
    deploy_hook_json($result['status'], $result['body']);
} catch (Throwable $e) {
    deploy_hook_json(500, ['ok' => false, 'error' => 'server_error']);
}
