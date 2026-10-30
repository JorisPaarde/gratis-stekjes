<?php

declare(strict_types=1);

use App\Services\Plantensoorten;

beforeEach(function () {
    $this->planten = new Plantensoorten(dirname(__DIR__, 2).'/data/plantensoorten.json');
});

test('catalogus bevat basis-48 en honderden soorten', function () {
    $all = $this->planten->all();
    expect(count($all))->toBeGreaterThanOrEqual(200)
        ->and(count($all))->toBeLessThan(800);

    $withSlug = array_values(array_filter($all, fn ($r) => ! empty($r['slug'])));
    expect(count($withSlug))->toBe(48);

    $monstera = $this->planten->resolveExact('Monstera');
    expect($monstera)->not->toBeNull()
        ->and($monstera['slug'])->toBe('monstera')
        ->and($monstera['url'])->toBe('/stektips/monstera');

    $tomaat = $this->planten->resolveExact('Tomaat');
    expect($tomaat)->not->toBeNull()
        ->and($tomaat['slug'])->toBeNull()
        ->and($tomaat['url'])->toBeNull();
});

test('search: begint-met vóór bevat, accentongevoelig', function () {
    $items = $this->planten->search('mun', 10);
    expect($items)->not->toBeEmpty()
        ->and($items[0]['naam'])->toBe('Munt');

    $accent = $this->planten->search('pelarg', 5);
    expect(collect($accent)->pluck('naam')->all())->toContain('Geranium');
    expect($this->planten->resolveExact('Pelargonium')['slug'] ?? null)->toBe('pelargonium');

    $latijn = $this->planten->search('mentha', 5);
    expect(collect($latijn)->pluck('naam')->all())->toContain('Munt');
});

test('search ranking: exacte naam, naam-prefix, syn/lat-prefix, dan bevat', function () {
    $monst = $this->planten->search('monst', 10);
    expect($monst)->not->toBeEmpty()
        ->and($monst[0]['naam'])->toBe('Monstera');

    $namen = collect($monst)->pluck('naam')->all();
    $posMonstera = array_search('Monstera', $namen, true);
    $posApen = array_search('Apenmaskerplant', $namen, true);
    expect($posMonstera)->toBeInt()
        ->and($posApen)->toBeInt()
        ->and($posMonstera)->toBeLessThan($posApen);

    $exact = $this->planten->search('Monstera', 5);
    expect($exact[0]['naam'])->toBe('Monstera')
        ->and($exact[0]['match'])->toBeNull();
});

test('search geeft match-veld bij synoniem-treffer', function () {
    $items = $this->planten->search('gatenplant', 5);
    $monstera = collect($items)->firstWhere('naam', 'Monstera');
    expect($monstera)->not->toBeNull()
        ->and($monstera['match'])->toBe('Gatenplant');

    $viaNaam = $this->planten->search('Monstera', 5);
    $row = collect($viaNaam)->firstWhere('naam', 'Monstera');
    expect($row)->not->toBeNull()
        ->and($row['match'])->toBeNull();
});

test('resolveExact matcht naam, synoniem en latijn', function () {
    expect($this->planten->resolveExact('Monstera')['id'] ?? null)->toBeInt()
        ->and($this->planten->resolveExact('gatenplant')['naam'] ?? null)->toBe('Monstera')
        ->and($this->planten->resolveExact('Monstera deliciosa')['naam'] ?? null)->toBe('Monstera')
        ->and($this->planten->resolveExact('Muntstekjes uit de tuin'))->toBeNull();
});

test('textForInvasieveCheck voegt soortnamen toe bij id', function () {
    $m = $this->planten->resolveExact('Monstera');
    $text = $this->planten->textForInvasieveCheck('stekje', $m['id']);
    expect($text)->toContain('Monstera')
        ->and($text)->toContain('Monstera deliciosa')
        ->and($text)->toContain('Gatenplant');
});
