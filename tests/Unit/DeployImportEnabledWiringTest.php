<?php

declare(strict_types=1);

test('deploy-prod-hook heeft import_enabled input en geeft IMPORT_ENABLED door', function () {
    $yml = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy-prod-hook.yml');
    expect($yml)->toContain('import_enabled:')
        ->and($yml)->toContain('IMPORT_ENABLED:')
        ->and($yml)->toContain("github.event_name == 'workflow_dispatch' && inputs.import_enabled");
});

test('deploy-hook-upload schrijft DEPLOY_TOKEN en DEPLOY_IMPORT_ENABLED', function () {
    $script = dirname(__DIR__, 2).'/scripts/deploy-hook-upload.sh';
    $src = file_get_contents($script);
    expect($src)->toContain('dotenv_line DEPLOY_TOKEN')
        ->and($src)->toContain('DEPLOY_IMPORT_ENABLED')
        ->and($src)->toContain('IMPORT_ENABLED');

    $map = static function (string $raw): string {
        $v = strtolower($raw);

        return in_array($v, ['1', 'true', 'yes'], true) ? 'true' : 'false';
    };
    expect($map('true'))->toBe('true')
        ->and($map('false'))->toBe('false')
        ->and($map(''))->toBe('false');
});

test('deploy-workflows schrijven optioneel ADMIN_DASHBOARD_EMAILS (gemaskeerd)', function () {
    $root = dirname(__DIR__, 2);
    $script = file_get_contents($root.'/scripts/deploy-hook-upload.sh');
    expect($script)->toContain('ADMIN_DASHBOARD_EMAILS')
        ->and($script)->toContain('dotenv_line ADMIN_DASHBOARD_EMAILS')
        ->and($script)->toMatch('/if \[ -n "\$\{ADMIN_DASHBOARD_EMAILS:-\}" \]/');

    foreach ([
        'deploy-staging-cpanel.yml',
        'deploy-prod-hook.yml',
        'deploy-production.yml',
    ] as $file) {
        $yml = file_get_contents($root.'/.github/workflows/'.$file);
        expect($yml)->toContain('ADMIN_DASHBOARD_EMAILS: ${{ secrets.ADMIN_DASHBOARD_EMAILS }}')
            ->and($yml)->toContain('::add-mask::${ADMIN_DASHBOARD_EMAILS}');
    }

    $hook = file_get_contents($root.'/deploy/hook/lib.php');
    expect($hook)->toContain("deploy_hook_preserve_env_key(\$finalEnv, \$existingEnv, 'ADMIN_DASHBOARD_EMAILS')");
});

test('migrate-workflow gebruikt repo-secrets zonder production-environment', function () {
    $yml = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/migrate-stekjes-to-prod.yml');
    expect($yml)->toContain('Fingerprint oude staging')
        ->and($yml)->toContain('app_url_host')
        ->and($yml)->toContain('source_VERSION')
        ->and($yml)->toContain('PROD_DEPLOY_TOKEN')
        ->and($yml)->toContain('gratisplanten.nl')
        ->and($yml)->toContain('old_app_dir:')
        ->and($yml)->toContain('resolve-old-staging-app-dir.sh')
        ->and($yml)->toContain('DB_NAME')
        ->and($yml)->toContain('DB_USER')
        ->and($yml)->toContain('DB_PASS')
        ->and($yml)->not->toContain('environment: production')
        ->and($yml)->not->toContain('secrets.STAGING_APP_DIR')
        ->and($yml)->not->toContain('secrets.DB_DATABASE')
        ->and($yml)->not->toContain('secrets.DB_USERNAME')
        ->and($yml)->not->toContain('secrets.DB_PASSWORD')
        ->and($yml)->not->toContain('secrets.DEPLOY_TOKEN')
        ->and($yml)->not->toContain('stekjes-staging-app');

    $agents = file_get_contents(dirname(__DIR__, 2).'/AGENTS.md');
    expect($agents)->toContain('import_enabled=true')
        ->and($agents)->toContain('PROD_DEPLOY_TOKEN')
        ->and($agents)->toContain('old_app_dir')
        ->and($agents)->toContain('resolve-old-staging-app-dir.sh')
        ->and($agents)->not->toContain('tijdelijk `DEPLOY_IMPORT_ENABLED=true` (na VERHUIS weer');
});
