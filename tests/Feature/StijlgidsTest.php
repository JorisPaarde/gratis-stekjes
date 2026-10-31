<?php

declare(strict_types=1);

test('stijlgids is niet publiek: gast wordt naar beheer-login gestuurd', function () {
    $this->get('/stijlgids')
        ->assertRedirect(route('beheer.login'));
});

test('stijlgids laadt voor beheer, is noindex en toont iconen', function () {
    $html = $this->withSession(['admin_ok' => true])
        ->get(route('stijlgids'))
        ->assertOk()
        ->assertSee('Stijlgids', false)
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('icon-stekje', false)
        ->assertSee('icon-waarschuwing', false)
        ->assertSee('class="page-title"', false)
        ->getContent();

    expect($html)->toContain('Bosgroen');
    expect($html)->toContain('#1F4D2B');
    expect($html)->toContain('<h1 class="page-title">Stijlgids</h1>');
});

test('stijlgids staat niet in sitemap of navigatie', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->not->toContain('/stijlgids');

    $home = $this->get('/')->assertOk()->getContent();
    expect($home)->not->toMatch('/href="[^"]*stijlgids"/');
});
