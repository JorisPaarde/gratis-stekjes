<?php

declare(strict_types=1);

test('homepage is 200 en toont merk Gratis planten', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Gratis planten', false);
    $response->assertSee('<h1 class="hero-brand">Gratis planten</h1>', false);
    $response->assertSee('Waarom?', false);
    $response->assertSee('Doe mee', false);
    $response->assertSee('Hoe werkt het', false);
    $response->assertSee('Veilig delen', false);
    $response->assertSee('voedselbosbouw.org', false);
    $response->assertSee('meerbomen.nu', false);
    $response->assertSee('binnenkort', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"WebSite"', false);
    $response->assertDontSee('mensen doen al mee', false);
});

test('privacy pagina laadt', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacyverklaring', false);
});

test('noindex meta en header wanneer site.noindex', function () {
    config(['site.noindex' => true]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('name="robots" content="noindex, nofollow"', false);
    expect($response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

test('robots.txt disallow bij noindex', function () {
    config(['site.noindex' => true]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /', false);
});

test('robots.txt allow zonder noindex', function () {
    config(['site.noindex' => false]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Allow: /', false)
        ->assertSee('Sitemap:', false);
});
