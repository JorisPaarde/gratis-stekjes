<?php

declare(strict_types=1);

use App\Services\InvasieveSoorten;

beforeEach(function () {
    $this->invasieve = new InvasieveSoorten(dirname(__DIR__, 2).'/data/niet-aanbieden.json');
});

test('JSON bevat precies 47 soorten met verplichte velden', function () {
    $soorten = $this->invasieve->all();
    expect($soorten)->toHaveCount(47);

    foreach ($soorten as $soort) {
        expect($soort['wetenschappelijke_naam'] ?? null)->not->toBeEmpty();
        expect($soort['nederlandse_naam'] ?? null)->not->toBeEmpty();
        expect($soort['bron_url'] ?? null)->not->toBeEmpty();
    }
});

test('normalisatie: spaties, hoofdletters, accenten, × en apostrof', function () {
    expect($this->invasieve->normalize('  Reuzenberenklauw  '))->toBe('reuzenberenklauw');
    expect($this->invasieve->normalize('Reynoutria × bohemica'))->toBe('reynoutria x bohemica');
    expect($this->invasieve->normalize("Sosnowsky's berenklauw"))->toBe('sosnowskys berenklauw');
});

test('matcht Nederlandse naam ongeacht hoofdletters', function () {
    $matches = $this->invasieve->matches('Gratis stekje van REUZENBERENKLAUW uit de tuin');
    expect($matches)->toHaveCount(1);
    expect($matches[0]['nederlandse_naam'])->toBe('Reuzenberenklauw');
});

test('matcht wetenschappelijke naam', function () {
    $matches = $this->invasieve->matches('IMPATIENS GLANDULIFERA stekjes');
    expect($matches)->toHaveCount(1);
    expect($matches[0]['nederlandse_naam'])->toBe('Reuzenbalsemien');
});

test('matcht synoniem', function () {
    $matches = $this->invasieve->matches('Fallopia japonica wortels');
    expect($matches)->toHaveCount(1);
    expect($matches[0]['nederlandse_naam'])->toBe('Japanse duizendknoop');
});

test('geen valse treffer bij gewone plant', function () {
    expect($this->invasieve->matches('Monstera deliciosa en Pothos stekjes te geef'))->toBe([]);
    expect($this->invasieve->matches('Zwarte bessenstekken en munt'))->toBe([]);
    expect($this->invasieve->findsMatch('Tomatenplantjes voor de moestuin'))->toBeFalse();
});

test('woordgrens: korte namen geven geen substring-treffer', function () {
    expect($this->invasieve->matches('hakeachtige struik zonder soortnaam'))->toBe([]);
    expect($this->invasieve->matches('Hakea stekje uit de tuin'))->not->toBeEmpty();
});

test('blokkeermelding noemt soort en uitleg-URL', function () {
    $msg = $this->invasieve->blockMessage('zijdeplant Asclepias syriaca', 'https://example.test/invasieve-soorten');
    expect($msg)->toContain('Zijdeplant');
    expect($msg)->toContain('niet worden verhandeld of verspreid');
    expect($msg)->toContain('https://example.test/invasieve-soorten');
});
