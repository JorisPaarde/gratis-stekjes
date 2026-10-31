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
