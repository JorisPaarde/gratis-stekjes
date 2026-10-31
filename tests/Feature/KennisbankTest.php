<?php

declare(strict_types=1);

use App\Services\Kennisbank;
use App\Services\Stektips;

test('kennisbank heeft minstens 10 artikelen met bron', function () {
    $artikelen = app(Kennisbank::class)->all();
    expect(count($artikelen))->toBeGreaterThanOrEqual(10);

    foreach ($artikelen as $artikel) {
        $slug = (string) ($artikel['slug'] ?? '');
        expect($slug)->not->toBeEmpty();
        expect($artikel['titel'] ?? null)->toBeString()->not->toBeEmpty();
        expect($artikel['samenvatting'] ?? null)->toBeString()->not->toBeEmpty();
        expect($artikel['zoekwoorden'] ?? null)->toBeArray()->not->toBeEmpty();
        expect($artikel['categorieen'] ?? null)->toBeArray()->not->toBeEmpty();
        expect($artikel['inhoud'] ?? null)->toBeArray()->not->toBeEmpty();

        $json = json_encode($artikel, JSON_UNESCAPED_UNICODE);
        expect($json)->not->toContain('Volgt binnenkort');

        $bronnen = $artikel['bronnen'] ?? [];
        expect($bronnen)->toBeArray()->not->toBeEmpty("{$slug} mist bronnen");
        $urls = 0;
        foreach ($bronnen as $bron) {
            expect($bron)->toBeArray();
            expect($bron['url'] ?? '')->toMatch('#^https?://#');
            $urls++;
        }
        expect($urls)->toBeGreaterThanOrEqual(1);
    }
});

test('stektips overzicht filtert en zoekt server-side onder 500', function () {
    $index = $this->get(route('stektips.index'))->assertOk();
    expect($index->getStatusCode())->toBeLessThan(500);
    $index->assertSee('Stektips: planten stekken en snoeien', false)
        ->assertSee('Wilgenwater', false)
        ->assertSee('for="stektips-q"', false)
        ->assertSee('name="categorie"', false)
        ->assertDontSee('Volgt binnenkort', false);

    $zoek = $this->get(route('stektips.index', ['q' => 'wilgenwater']))->assertOk();
    expect($zoek->getStatusCode())->toBeLessThan(500);
    $zoek->assertSee('Wilgenwater maken', false)
        ->assertDontSee('Winterhoutstek', false);

    $cat = $this->get(route('stektips.index', ['categorie' => 'natuurlijk-bewortelen']))->assertOk();
    expect($cat->getStatusCode())->toBeLessThan(500);
    $cat->assertSee('Wilgenwater', false)
        ->assertSee('Stekpoeder', false)
        ->assertDontSee('>Aardbei</h2>', false);

    $maand = $this->get(route('stektips.index', ['maand' => '11']))->assertOk();
    expect($maand->getStatusCode())->toBeLessThan(500);
    $maand->assertSee('Winterhoutstek', false);

    $soort = $this->get(route('stektips.index', ['soort' => 'wilg']))->assertOk();
    expect($soort->getStatusCode())->toBeLessThan(500);
    $soort->assertSee('Wilg', false)
        ->assertSee('Wilgenwater', false);

    $locatie = $this->get(route('stektips.index', ['locatie' => 'binnen']))->assertOk();
    expect($locatie->getStatusCode())->toBeLessThan(500);
    $locatie->assertSee('Bladstek', false);
});

test('artikelpagina toont breadcrumbs bronnen related en schema', function () {
    $response = $this->get(route('stektips.artikel', ['slug' => 'wilgenwater']))->assertOk();
    expect($response->getStatusCode())->toBeLessThan(500);

    $response->assertSee('Wilgenwater maken', false)
        ->assertSee('Broodkruimels', false)
        ->assertSee('Bronnen', false)
        ->assertSee('https://libanswers.nybg.org/faq/223060', false)
        ->assertSee('Gerelateerde artikelen', false)
        ->assertSee('application/ld+json', false)
        ->assertDontSee('Volgt binnenkort', false);

    $html = $response->getContent();
    expect($html)->toContain('"@type":"Article"')
        ->and($html)->toContain('"@type":"HowTo"');

    $this->get(route('stektips.artikel', ['slug' => 'onbestaand-artikel']))
        ->assertNotFound();
});

test('stekpoeder verwijst naar duurzamer alternatief wilgenwater', function () {
    $this->get(route('stektips.artikel', ['slug' => 'stekpoeder']))
        ->assertOk()
        ->assertSee('Duurzamer alternatief', false)
        ->assertSee(route('stektips.artikel', ['slug' => 'wilgenwater']), false);
});

test('soortpagina toont gerelateerde artikelen onder 500', function () {
    $response = $this->get(route('stektips.show', ['slug' => 'wilg']))->assertOk();
    expect($response->getStatusCode())->toBeLessThan(500);
    $response->assertSee('Gerelateerde artikelen', false)
        ->assertSee(route('stektips.artikel', ['slug' => 'wilgenwater']), false)
        ->assertSee('Broodkruimels', false);
});

test('sitemap bevat kennisbank-artikelen', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('stektips.artikel', ['slug' => 'wilgenwater']));
    expect($xml)->toContain(route('stektips.artikel', ['slug' => 'stekpoeder']));
    expect($xml)->toContain(route('stektips.show', ['slug' => 'munt']));
});

test('artikel-slug botst niet met soort-slug en reserved pad', function () {
    $soortSlugs = collect(app(Stektips::class)->all())->pluck('slug');
    $artikelSlugs = collect(app(Kennisbank::class)->all())->pluck('slug');

    expect($soortSlugs)->not->toContain('artikel');
    foreach ($artikelSlugs as $slug) {
        expect($soortSlugs)->not->toContain($slug, "Artikel-slug {$slug} botst met soort");
    }

    $this->get('/stektips/artikel')->assertNotFound();
});
