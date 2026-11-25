<?php

declare(strict_types=1);

use App\Services\Stektips;

test('aanbodZelfstandigNaam is stekjes alleen als stekken een methode is', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');

    expect($stektips->aanbodZelfstandigNaam($stektips->find('palmkool')))->toBe('planten')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('akelei')))->toBe('planten')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('rabarber')))->toBe('planten')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('peer')))->toBe('planten')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('monstera')))->toBe('stekjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('pelargonium')))->toBe('stekjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('eeuwig-moes')))->toBe('stekjes');
});

test('elke soort zonder stekken-methode krijgt planten als aanbodwoord', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');
    $zonderStek = [];

    foreach ($stektips->all() as $soort) {
        if (! in_array('stekken', $stektips->methodes($soort), true)) {
            $zonderStek[] = $soort['slug'];
            expect($stektips->aanbodZelfstandigNaam($soort))->toBe(
                'planten',
                "{$soort['slug']} heeft geen stek-methode maar werd '{$stektips->aanbodZelfstandigNaam($soort)}'"
            );
        } else {
            expect($stektips->aanbodZelfstandigNaam($soort))->toBe('stekjes');
        }
    }

    expect($zonderStek)->toContain('palmkool')
        ->and($zonderStek)->not->toBeEmpty();
});
