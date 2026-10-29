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
