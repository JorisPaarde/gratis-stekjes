<?php

declare(strict_types=1);

use App\Services\Steden;

test('steden.json is geldig en uniek', function () {
    $steden = new Steden(dirname(__DIR__, 2).'/data/steden.json');
    $errors = $steden->validateJson();
    expect($errors)->toBe([]);

    $all = $steden->all();
    expect(count($all))->toBeGreaterThanOrEqual(35);

    $intros = array_column($all, 'intro');
    expect(count(array_unique($intros)))->toBe(count($intros));

    $slugs = array_column($all, 'slug');
    expect(count(array_unique($slugs)))->toBe(count($slugs));
});

test('find kent bekende en onbekende slugs', function () {
    $steden = new Steden(dirname(__DIR__, 2).'/data/steden.json');
    expect($steden->find('amsterdam'))->not->toBeNull()
        ->and($steden->find('amsterdam')['naam'])->toBe('Amsterdam');
    expect($steden->find('antwerpen'))->not->toBeNull()
        ->and($steden->find('antwerpen')['country'])->toBe('BE')
        ->and($steden->find('antwerpen')['naam'])->toBe('Antwerpen');
    expect($steden->find('onbestaand'))->toBeNull();
    expect($steden->find('Amsterdam'))->toBeNull(); // alleen lowercase slug
});

test('BE-stad en Arlon-achtige coördinaten valideren in NL+BE-bbox', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'steden-');
    expect($tmp)->not->toBeFalse();

    $payload = [
        'meta' => ['titel' => 'test'],
        'steden' => [
            [
                'naam' => 'Amsterdam',
                'slug' => 'amsterdam',
                'provincie' => 'Noord-Holland',
                'lat' => 52.3676,
                'lng' => 4.9041,
                'straal_km' => 20,
                'intro' => 'Eerste zin over Amsterdam. Tweede zin blijft lokaal en feitelijk.',
            ],
            [
                'naam' => 'Arlon',
                'slug' => 'arlon',
                'provincie' => 'Luxemburg (BE)',
                'lat' => 49.6833,
                'lng' => 5.8167,
                'straal_km' => 10,
                'country' => 'BE',
                'intro' => 'Eerste zin over Arlon in het zuiden. Tweede zin noemt alleen echte data.',
            ],
        ],
    ];
    // Pad naar 35+ rijen voor minimale count-check: herhaal NL-dummies uniek.
    for ($i = 2; $i < 36; $i++) {
        $payload['steden'][] = [
            'naam' => "Stad $i",
            'slug' => 'stad-'.$i,
            'provincie' => 'Utrecht',
            'lat' => 52.0 + ($i * 0.01),
            'lng' => 5.0,
            'straal_km' => 10,
            'intro' => "Unieke intro nummer {$i} eerste zin. Tweede zin houdt het kort.",
        ];
    }

    file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE));
    $steden = new Steden($tmp);
    expect($steden->validateJson())->toBe([]);
    expect($steden->find('arlon')['country'])->toBe('BE');
    @unlink($tmp);
});

test('NL-stedenrijen blijven ongewijzigd (snapshot)', function () {
    $root = dirname(__DIR__, 2);
    $data = json_decode((string) file_get_contents($root.'/data/steden.json'), true);
    $expected = json_decode(
        (string) file_get_contents($root.'/tests/fixtures/steden-nl-v0.28.json'),
        true
    );
    expect($expected)->toBeArray()->toHaveCount(42);

    $nl = array_values(array_filter(
        $data['steden'],
        static fn (array $row): bool => ($row['country'] ?? 'NL') !== 'BE'
    ));
    expect($nl)->toHaveCount(42);
    expect($nl)->toBe($expected);
});
