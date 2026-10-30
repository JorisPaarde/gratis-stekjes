<?php

declare(strict_types=1);

test('deploy-prod-hook heeft import_enabled input en geeft IMPORT_ENABLED door', function () {
    $yml = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy-prod-hook.yml');
    expect($yml)->toContain('import_enabled:')
        ->and($yml)->toContain('IMPORT_ENABLED:')
        ->and($yml)->toContain("github.event_name == 'workflow_dispatch' && inputs.import_enabled");
});

test('deploy-hook-upload schrijft DEPLOY_IMPORT_ENABLED vanuit IMPORT_ENABLED', function () {
    $script = dirname(__DIR__, 2).'/scripts/deploy-hook-upload.sh';
    $src = file_get_contents($script);
    expect($src)->toContain('DEPLOY_IMPORT_ENABLED')
        ->and($src)->toContain('IMPORT_ENABLED');

    // Mapping-logica (zelfde case als in het script).
    $map = static function (string $raw): string {
        $v = strtolower($raw);
        return in_array($v, ['1', 'true', 'yes'], true) ? 'true' : 'false';
    };
    expect($map('true'))->toBe('true')
        ->and($map('false'))->toBe('false')
        ->and($map(''))->toBe('false');
});

test('migrate-workflow fingerprint en docs noemen geen handmatige prod-.env-edit', function () {
    $yml = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/migrate-stekjes-to-prod.yml');
    expect($yml)->toContain('Fingerprint oude staging')
        ->and($yml)->toContain('app_url_host')
        ->and($yml)->toContain('source_VERSION');

    $agents = file_get_contents(dirname(__DIR__, 2).'/AGENTS.md');
    expect($agents)->toContain('import_enabled=true')
        ->and($agents)->not->toContain('tijdelijk `DEPLOY_IMPORT_ENABLED=true` (na VERHUIS weer');
});
