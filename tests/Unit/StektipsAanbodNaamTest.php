<?php

declare(strict_types=1);

use App\Services\Stektips;

test('aanbodZelfstandigNaam: stekjes, zaad of plantjes', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');

    expect($stektips->aanbodZelfstandigNaam($stektips->find('palmkool')))->toBe('zaad')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('akelei')))->toBe('zaad')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('rabarber')))->toBe('plantjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('peer')))->toBe('plantjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('aardbei')))->toBe('plantjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('monstera')))->toBe('stekjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('pelargonium')))->toBe('stekjes')
        ->and($stektips->aanbodZelfstandigNaam($stektips->find('eeuwig-moes')))->toBe('stekjes');
});

test('elke soort krijgt stekjes, zaad of plantjes volgens de methodes', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');
    $zaad = [];
    $plantjes = [];
    $stekjes = [];

    foreach ($stektips->all() as $soort) {
        $woord = $stektips->aanbodZelfstandigNaam($soort);
        $methodes = $stektips->methodes($soort);
        if (in_array('stekken', $methodes, true)) {
            expect($woord)->toBe('stekjes', $soort['slug']);
            $stekjes[] = $soort['slug'];
        } elseif ($methodes === ['zaaien']) {
            expect($woord)->toBe('zaad', $soort['slug']);
            $zaad[] = $soort['slug'];
        } else {
            expect($woord)->toBe('plantjes', $soort['slug']);
            $plantjes[] = $soort['slug'];
        }
    }

    expect($zaad)->toContain('palmkool')
        ->and($plantjes)->toContain('rabarber')
        ->and($stekjes)->toContain('monstera')
        ->and($zaad)->not->toBeEmpty()
        ->and($plantjes)->not->toBeEmpty()
        ->and($stekjes)->not->toBeEmpty();
});
