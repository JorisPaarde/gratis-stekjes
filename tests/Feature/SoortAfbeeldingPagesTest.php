<?php

declare(strict_types=1);

use App\Services\SoortAfbeeldingen;

test('soortpagina toont afbeelding met attribution', function () {
    $this->get(route('stektips.show', ['slug' => 'monstera']))
        ->assertOk()
        ->assertSee('soort-afbeelding', false)
        ->assertSee('assets/images/soorten/monstera.jpg', false)
        ->assertSee('Wikimedia Commons', false)
        ->assertSee('CC', false);
});

test('stektips-index toont soortfoto of credit', function () {
    $this->get(route('stektips.index'))
        ->assertOk()
        ->assertSee('stektips-entry-img', false)
        ->assertSee('assets/images/soorten/', false);
});

test('onbekende soort-slug in service krijgt fallback-illustratie', function () {
    $img = app(SoortAfbeeldingen::class)->forSlug('bestaat-niet', 'Onbekend');
    expect($img['is_fallback'])->toBeTrue()
        ->and($img['url'])->toContain('soort-fallback.svg');
});

test('zwarte-bes toont Ribes-foto met content-hash, geen Cassis-schelp', function () {
    $img = app(SoortAfbeeldingen::class)->forSlug('zwarte-bes', 'Zwarte bes');
    expect($img['is_fallback'])->toBeFalse()
        ->and($img['url'])->toContain('assets/images/soorten/zwarte-bes.jpg')
        ->and($img['url'])->toContain('h=7e3dfe151039')
        ->and($img['bron_url'])->toContain('Ribes_nigrum')
        ->and($img['bron_url'])->not->toContain('Cassis');

    $this->get(route('stektips.show', ['slug' => 'zwarte-bes']))
        ->assertOk()
        ->assertSee('assets/images/soorten/zwarte-bes.jpg?h=7e3dfe151039', false)
        ->assertSee('Ribes nigrum', false)
        ->assertDontSee('Cassis cornuta', false)
        ->assertDontSee('Giant Helmet', false);
});

test('goedgekeurde soort-url bevat sha256-cachebuster', function () {
    $img = app(SoortAfbeeldingen::class)->forSlug('munt', 'Munt');
    expect($img['is_fallback'])->toBeFalse()
        ->and($img['url'])->toMatch('/assets\/images\/soorten\/munt\.jpg\?h=[a-f0-9]{12}/');
});
