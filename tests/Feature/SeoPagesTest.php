<?php

declare(strict_types=1);

use App\Services\Stektips;

test('contentpagina’s laden', function () {
    $this->get(route('hoe-werkt-het'))->assertOk()->assertSee('Hoe werkt het', false);
    $this->get(route('hulpmiddelen'))->assertOk()->assertSee('Wilgenwater', false);
    $this->get(route('over'))->assertOk()->assertSee('Joris', false);
    $this->get(route('doe-mee'))->assertRedirect(route('account.registreren'));
});

test('stektips index en soortpagina', function () {
    $soorten = app(Stektips::class)->all();
    expect($soorten)->toHaveCount(16);

    $this->get(route('stektips.index'))
        ->assertOk()
        ->assertSee('Zwarte bes', false)
        ->assertSee('Herfstolijfwilg', false);

    $this->get(route('stektips.show', ['slug' => 'aardbei']))
        ->assertOk()
        ->assertSee('Aardbei', false)
        ->assertSee('Aug–sep', false);

    $this->get(route('stektips.show', ['slug' => 'onbestaand']))
        ->assertNotFound();
});

test('sitemap bevat stektips', function () {
    $xml = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)->toContain(route('stektips.index'));
    expect($xml)->toContain(route('stektips.show', ['slug' => 'munt']));
    expect($xml)->toContain(route('hoe-werkt-het'));
});
