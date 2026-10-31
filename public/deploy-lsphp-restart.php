<?php

declare(strict_types=1);

/**
 * Beveiligd LiteSpeed/lsphp-herstart endpoint (docroot, naast index.php).
 *
 * Wordt via de hook uit public/ naar de docroot gesynchroniseerd, zodat CI
 * ná finish (zelfs met een oude hook die alleen touch() deed) de marker
 * /home/<account>/.lsphp_restart.txt kan herschrijven + opcache_reset.
 *
 * Auth: Bearer DEPLOY_HOOK_TOKEN (zelfde als deploy-hook.php). Geen secrets in output.
 */

$lib = __DIR__.'/lib.php';
if (! is_file($lib)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"missing_lib"}';
    exit;
}
require $lib;

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        deploy_hook_json(405, ['ok' => false, 'error' => 'method']);
        exit;
    }
    if (! deploy_hook_is_https()) {
        deploy_hook_json(403, ['ok' => false, 'error' => 'https_required']);
        exit;
    }

    $config = deploy_hook_load_config(__DIR__);
    if ($config === null) {
        deploy_hook_json(503, ['ok' => false, 'error' => 'not_configured']);
        exit;
    }

    $token = deploy_hook_bearer_token();
    if (! deploy_hook_verify_token($config['token_hash'], $token)) {
        deploy_hook_json(403, ['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    $configuredHome = isset($config['home_dir']) ? (string) $config['home_dir'] : null;

    // Voorkeur: nieuwe lib-helpers; anders inline herschrijven (oude lib op server).
    if (function_exists('deploy_hook_write_lsphp_restart_markers')) {
        $restart = deploy_hook_write_lsphp_restart_markers(
            $config['app_dir'],
            $config['docroot'],
            $configuredHome,
        );
        $ok = $restart['ok'];
    } else {
        $ok = false;
        $homes = [];
        $homes[deploy_hook_resolve_home_dir($config['app_dir'], $configuredHome)] = true;
        $homes[rtrim(dirname(rtrim($config['docroot'], '/')), '/')] = true;
        foreach (array_keys($homes) as $home) {
            if ($home === '' || $home === '/' || ! is_dir($home)) {
                continue;
            }
            $marker = $home.'/.lsphp_restart.txt';
            $payload = 'restart '.gmdate('c').' '.bin2hex(random_bytes(8))."\n";
            if (is_file($marker) || is_link($marker)) {
                @unlink($marker);
            }
            if (@file_put_contents($marker, $payload, LOCK_EX) !== false) {
                $ok = true;
            }
        }
        // Oude lib: touch-fallback als die bestaat.
        if (! $ok && function_exists('deploy_hook_touch_lsphp_restart')) {
            $ok = deploy_hook_touch_lsphp_restart(
                deploy_hook_resolve_home_dir($config['app_dir'], $configuredHome)
            );
        }
    }

    if (function_exists('deploy_hook_bump_entrypoint_mtimes')) {
        deploy_hook_bump_entrypoint_mtimes($config['docroot']);
    } else {
        foreach (['index.php', '.htaccess'] as $name) {
            $path = __DIR__.'/'.$name;
            if (is_file($path)) {
                @touch($path);
            }
        }
    }

    if (function_exists('deploy_hook_invalidate_runtime_caches')) {
        deploy_hook_invalidate_runtime_caches();
    } else {
        clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    deploy_hook_json(200, [
        'ok' => $ok,
        'lsphp_restart' => $ok,
    ]);
} catch (Throwable $e) {
    deploy_hook_json(500, ['ok' => false, 'error' => 'server_error']);
}
