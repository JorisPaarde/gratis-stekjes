<?php

declare(strict_types=1);

test('stijlgids laadt, is noindex en toont iconen', function () {
    $html = $this->get(route('stijlgids'))
        ->assertOk()
        ->assertSee('Stijlgids', false)
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertSee('icon-stekje', false)
        ->assertSee('icon-waarschuwing', false)
        ->getContent();

    expect($html)->toContain('Bosgroen');
    expect($html)->toContain('#1F4D2B');
});

test('stijlgids staat niet in sitemap of navigatie', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->not->toContain('/stijlgids');

    $home = $this->get('/')->assertOk()->getContent();
    expect($home)->not->toMatch('/href="[^"]*stijlgids"/');
});
