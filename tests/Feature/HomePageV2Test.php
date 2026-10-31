<?php

declare(strict_types=1);

test('homepage v2 is 200 met vlag aan en toont hero B', function () {
    config([
        'features.homepage_v2' => true,
        'homepage.hero.active' => 'b',
        'site.name' => 'Gratis planten',
        'site.noindex' => false,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('page-home-v2', false);
    $response->assertSee('>Gratis planten, stekjes en zaden bij jou in de buurt</h1>', false);
    $response->assertSee('Meer groen, meer contact', false);
    $response->assertSee('Maak gratis een account', false);
    $response->assertSee('Open de kaart', false);
    $response->assertSee(route('account.registreren', absolute: false), false);
    $response->assertSee(route('aanbod', absolute: false), false);
    $response->assertSee('Bied planten of zaden aan', false);
    $response->assertSee('plantenasiel', false);
    $response->assertSee('zadenbibliotheek', false);
    $response->assertSee('plantenruil', false);
    $response->assertSee('gratis zaden', false);
    $response->assertSee('planten gratis afhalen in Vlaanderen en Brussel', false);
    $response->assertSee('Doe mee — het is gratis', false);
    $response->assertSee('assets/images/home-v2/hero-960.webp', false);
    $response->assertSee('Stadtbibliothek Neuss', false);
    $response->assertDontSee('zadenbieb', false);
    $response->assertDontSee('server-rendered', false);
    $response->assertDontSee('placeholder', false);
    $response->assertDontSee('SEO', false);
});

test('homepage met vlag uit blijft de bestaande prod-homepage', function () {
    config([
        'features.homepage_v2' => false,
        'site.name' => 'Gratis planten',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('class="hero-brand"', false);
    $response->assertSee('>Gratis planten en stekjes in je buurt</h1>', false);
    $response->assertSee('Waarom?', false);
    $response->assertSee('Doe mee', false);
    $response->assertDontSee('page-home-v2', false);
    $response->assertDontSee('Gratis planten, stekjes en zaden bij jou in de buurt', false);
});

test('homepage v2 en v1 geven beide 200', function (bool $v2) {
    config(['features.homepage_v2' => $v2, 'site.name' => 'Gratis planten']);

    $this->get('/')->assertOk()->assertSee('Gratis planten', false);
})->with([
    'vlag uit' => false,
    'vlag aan' => true,
]);

test('homepage v2 hero-variant wisselt via config', function () {
    config([
        'features.homepage_v2' => true,
        'homepage.hero.active' => 'c',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Deel planten, stekjes en zaden met je buren', false)
        ->assertSee('Gratis ophalen, een praatje als dank', false);
});
