<?php

declare(strict_types=1);

use App\Services\Plantensoorten;

beforeEach(function () {
    $this->planten = new Plantensoorten(dirname(__DIR__).'/fixtures/plantensoorten-zoek.json');
});

test('fixture-search vindt op synoniem en Latijn', function () {
    $viaSyn = $this->planten->search('gatenplant', 5);
    expect(collect($viaSyn)->pluck('naam')->all())->toContain('Monstera')
        ->and(collect($viaSyn)->firstWhere('naam', 'Monstera')['match'])->toBe('Gatenplant');

    $viaTong = $this->planten->search('vrouwentong', 5);
    expect(collect($viaTong)->pluck('naam')->all())->toContain('Tongplant');

    $viaLatijn = $this->planten->search('Sansevieria fixturea', 5);
    expect(collect($viaLatijn)->pluck('naam')->all())->toContain('Tongplant');
});

test('suggestClose tolereert kleine tikfouten binnen de limiet', function () {
    // monstra → Monstera (1 letter verschil na normalize)
    $hits = $this->planten->suggestClose('monstra', 3, onlyWithSlug: true);
    expect($hits)->not->toBeEmpty()
        ->and($hits[0]['naam'])->toBe('Monstera')
        ->and($hits[0]['distance'])->toBe(1)
        ->and($hits[0]['slug'])->toBe('fixture-monstera');
});

test('stektipsSlugsForQuery: synoniem en tikfout leveren slug', function () {
    expect($this->planten->stektipsSlugsForQuery('vrouwentong'))->toBe(['fixture-tongplant'])
        ->and($this->planten->stektipsSlugsForQuery('gatenplant'))->toBe(['fixture-monstera'])
        ->and($this->planten->stektipsSlugsForQuery('monstra'))->toBe(['fixture-monstera']);
});

test('suggestClose negeert verborgen soorten en soorten zonder slug bij onlyWithSlug', function () {
    $hidden = $this->planten->suggestClose('Geheime naam', 5);
    expect(collect($hidden)->pluck('naam')->all())->not->toContain('Verborgen Plant');

    $withSlug = $this->planten->suggestClose('Alleen Aanbod', 5, onlyWithSlug: true);
    expect(collect($withSlug)->pluck('naam')->all())->not->toContain('Zonder Pagina');

    $without = $this->planten->suggestClose('Alleen Aanbod', 5, onlyWithSlug: false);
    expect(collect($without)->pluck('naam')->all())->toContain('Zonder Pagina');
});

test('maxEditDistance: 1 bij korte termen, 2 bij langere', function () {
    expect($this->planten->maxEditDistance('munt'))->toBe(1)
        ->and($this->planten->maxEditDistance('monstra'))->toBe(2);
});
