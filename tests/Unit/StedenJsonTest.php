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
    expect($steden->find('onbestaand'))->toBeNull();
    expect($steden->find('Amsterdam'))->toBeNull(); // alleen lowercase slug
});
