<?php

declare(strict_types=1);

use App\Services\Plantensoorten;
use App\Services\Stektips;

test('voorZoekterm vindt stektip via naam, synoniem en Latijn', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');
    $catalogus = new Plantensoorten(dirname(__DIR__, 2).'/data/plantensoorten.json');

    $daglelie = $stektips->voorZoekterm('Daglelie', $catalogus);
    expect($daglelie)->not->toBeNull()
        ->and($daglelie['slug'])->toBe('daglelie')
        ->and($daglelie['methode'])->toContain('Scheuren');

    $viaLatijn = $stektips->voorZoekterm('Hemerocallis', $catalogus);
    expect($viaLatijn['slug'])->toBe('daglelie');

    $viaSynoniem = $stektips->voorZoekterm('Aardbeienplant', $catalogus);
    expect($viaSynoniem['slug'])->toBe('aardbei');
});

test('voorZoekterm is leeg bij soort zonder stektip of onbekende naam', function () {
    $stektips = new Stektips(dirname(__DIR__, 2).'/data/stektips.json');
    $catalogus = new Plantensoorten(dirname(__DIR__, 2).'/data/plantensoorten.json');

    expect($stektips->voorZoekterm('Yucca', $catalogus))->toBeNull()
        ->and($stektips->voorZoekterm('xyzonbekendeplant', $catalogus))->toBeNull()
        ->and($stektips->voorZoekterm('  ', $catalogus))->toBeNull();
});
