<?php

declare(strict_types=1);

test('deelplekken JSON bestand is parseerbaar en heeft minimaal 30 plekken', function () {
    $path = dirname(__DIR__, 2).'/data/deelplekken.json';
    expect(is_readable($path))->toBeTrue();

    $data = json_decode((string) file_get_contents($path), true);
    expect($data)->toBeArray();
    expect($data['plekken'] ?? null)->toBeArray();
    expect(count($data['plekken']))->toBeGreaterThanOrEqual(30);

    foreach ($data['plekken'] as $plek) {
        expect($plek)->toHaveKeys(['id', 'naam', 'type', 'plaats', 'provincie', 'adres', 'lat', 'lng', 'bron_url', 'geraadpleegd']);
        expect($plek['bron_url'])->toStartWith('http');
        expect($plek['geraadpleegd'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
        expect(is_numeric($plek['lat']))->toBeTrue();
        expect(is_numeric($plek['lng']))->toBeTrue();
    }
});
