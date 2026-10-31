<?php

declare(strict_types=1);

test('homepage configbestand heeft vier hero-varianten en actieve B-teksten', function () {
    $cfg = require dirname(__DIR__, 2).'/config/homepage.php';

    expect($cfg['hero']['active'])->toBe('b')
        ->and($cfg['hero']['variants'])->toHaveKeys(['a', 'b', 'c', 'd'])
        ->and($cfg['hero']['variants']['b']['heading'])->toBe('Gratis planten, stekjes en zaden bij jou in de buurt')
        ->and($cfg['hero']['variants']['b']['tagline'])->toBe('Meer groen, meer contact')
        ->and($cfg['hero']['variants']['b']['primary']['label'])->toBe('Maak gratis een account')
        ->and($cfg['hero']['variants']['b']['primary']['route'])->toBe('account.registreren')
        ->and($cfg['hero']['variants']['b']['secondary']['label'])->toBe('Open de kaart')
        ->and($cfg['hero']['variants']['b']['secondary']['route'])->toBe('aanbod');
});

test('features.php bevat homepage_v2-vlag', function () {
    $src = file_get_contents(dirname(__DIR__, 2).'/config/features.php');
    expect($src)->toContain("'homepage_v2'")
        ->and($src)->toContain('HOMEPAGE_V2');
});

test('staging-deploy zet HOMEPAGE_V2 aan; prod leest optionele var/secret', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/scripts/deploy-hook-upload.sh');
    expect($script)->toContain('dotenv_line HOMEPAGE_V2')
        ->and($script)->toContain('APP_ENV_NAME')
        ->and($script)->toContain('staging');

    $prod = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy-prod-hook.yml');
    expect($prod)->toContain('HOMEPAGE_V2:')
        ->and($prod)->toContain('vars.HOMEPAGE_V2');

    $example = file_get_contents(dirname(__DIR__, 2).'/.env.example');
    expect($example)->toContain('HOMEPAGE_V2');
});

test('foto-credits.json bevat bron, maker en licentie per homepagefoto', function () {
    $root = dirname(__DIR__, 2);
    $path = $root.'/resources/data/foto-credits.json';
    $data = json_decode((string) file_get_contents($path), true);
    expect($data['fotos'] ?? null)->toBeArray()->not->toBeEmpty();

    foreach ($data['fotos'] as $foto) {
        expect($foto)->toHaveKeys(['id', 'bestanden', 'bron', 'bron_url', 'maker', 'licentie', 'licentie_url', 'alt']);
        expect($foto['bestanden'])->not->toBeEmpty();
        $first = $root.'/public/'.$foto['bestanden'][0];
        expect(is_file($first))->toBeTrue("ontbrekend bestand: {$foto['bestanden'][0]}");
        if (! empty($foto['attribution_required'])) {
            $lic = (string) $foto['licentie'];
            expect(
                str_contains($lic, 'CC BY') || str_contains($lic, 'ODbL') || str_contains($lic, 'OpenStreetMap')
            )->toBeTrue("onverwachte licentie voor {$foto['id']}: {$lic}");
        }
    }

    $hashes = [];
    foreach ($data['fotos'] as $foto) {
        $path960 = $root.'/public/assets/images/home-v2/'.$foto['id'].'-960.webp';
        expect(is_file($path960))->toBeTrue();
        $hashes[md5_file($path960)][] = $foto['id'];
    }
    foreach ($hashes as $hash => $ids) {
        expect($ids)->toHaveCount(1, 'dubbele foto: '.implode(',', $ids));
    }
});
