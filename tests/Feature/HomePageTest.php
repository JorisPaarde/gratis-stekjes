<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schema;

test('homepage is 200 en toont merk Gratis planten', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Gratis planten', false);
    $response->assertSee('class="hero-brand"', false);
    $response->assertSee('>Gratis planten</h1>', false);
    $response->assertSee('assets/illustrations/hero-planten.svg', false);
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

test('Doe mee-paneel staat in HTML zonder JS-afhankelijke reveal-klassen', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('id="doe-mee"');
    expect($html)->toContain('class="cta-panel"');
    // Geen opacity/reveal hooks die zonder JS onzichtbaar zouden blijven
    expect($html)->not->toMatch('/cta-panel[^>]*opacity\s*:\s*0/');
    expect($html)->not->toContain('data-reveal');
    expect($html)->not->toContain('is-invisible');
});

test('homepage rendert zonder database-tabellen (alleen JSON/config)', function () {
    // Bewijs: na droppen van álle tabellen blijft / 200 (session/cache=array in tests;
    // staging deploy gebruikt file-drivers). Homepage leest alleen config + JSON.
    Schema::disableForeignKeyConstraints();
    foreach (Schema::getTableListing() as $table) {
        $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
        Schema::drop($name);
    }
    Schema::enableForeignKeyConstraints();
    expect(Schema::getTableListing())->toBe([]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Gratis planten', false);
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
