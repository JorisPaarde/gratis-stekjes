<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Services\StadCatalogus;
use App\Support\PlaatsSlug;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    config(['gratisplanten.stad_aanbod_min' => 1]);
    config(['site.noindex' => false]);
});

function maakPubliekAanbod(string $plaats, array $userAttrs = [], array $aanbodAttrs = []): Aanbod
{
    $user = User::factory()->create(array_merge([
        'name' => 'Sam',
        'woonplaats' => $plaats,
        'locatie_label' => $plaats,
        'lat' => 52.15,
        'lon' => 5.39,
        'country' => 'NL',
        'email_verified_at' => now(),
        'active' => true,
    ], $userAttrs));

    return Aanbod::query()->create(array_merge([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Stekje uit '.$plaats,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ], $aanbodAttrs));
}

test('plaats-slug normaliseert spelling en aliassen naar vaste stad', function () {
    expect(PlaatsSlug::from('Haarlem'))->toBe('haarlem')
        ->and(PlaatsSlug::from('  HAARLEM '))->toBe('haarlem')
        ->and(PlaatsSlug::from("'s-Gravenhage"))->toBe('den-haag')
        ->and(PlaatsSlug::from('Den Haag'))->toBe('den-haag')
        ->and(PlaatsSlug::from("'s-Hertogenbosch"))->toBe('den-bosch');
});

test('drempel net erboven: automatische stad is 200, geïndexeerd en in sitemap/overzicht', function () {
    // Bunnik zit niet in steden.json en heeft geen deelplek in de JSON-seed.
    maakPubliekAanbod('Bunnik', ['lat' => 52.063, 'lon' => 5.198]);
    app(StadCatalogus::class)->forgetCache();

    $html = $this->get(route('gratis-planten.show', ['stad' => 'bunnik']))
        ->assertOk()
        ->assertDontSee('name="robots" content="noindex', false)
        ->assertSee('Gratis planten en stekjes in Bunnik', false)
        ->assertSee('1 gratis plant of stekje', false)
        ->assertSee('Zelf iets aanbieden', false)
        ->assertSee('Zoek verder rond Bunnik', false)
        ->assertSee('id="stad-kaart"', false)
        ->getContent();

    expect($html)->not->toContain('1 gratis planten');

    $this->get(route('gratis-planten.index'))
        ->assertOk()
        ->assertSee(route('gratis-planten.show', ['stad' => 'bunnik']), false)
        ->assertSee('>Bunnik</a>', false);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'bunnik']));
});

test('drempel net eronder: automatische stad redirect 301, vaste stad met 0 blijft 200 met noindex', function () {
    config(['gratisplanten.stad_aanbod_min' => 2]);
    maakPubliekAanbod('Bunnik', ['lat' => 52.063, 'lon' => 5.198]);
    app(StadCatalogus::class)->forgetCache();

    $this->get(route('gratis-planten.show', ['stad' => 'bunnik']))
        ->assertStatus(301)
        ->assertRedirect(route('gratis-planten.index'));

    // Vaste stad zonder lokaal aanbod: URL blijft, noindex, niet in sitemap.
    $this->get(route('gratis-planten.show', ['stad' => 'rotterdam']))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, follow"', false);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->not->toContain(route('gratis-planten.show', ['stad' => 'bunnik']))
        ->and($xml)->not->toContain(route('gratis-planten.show', ['stad' => 'rotterdam']));
});

test('vaste steden blijven 200 onder dezelfde URL', function () {
    foreach (['amsterdam', 'utrecht', 'antwerpen', 'den-helder'] as $slug) {
        $this->get(route('gratis-planten.show', ['stad' => $slug]))->assertOk();
    }
});

test('slug-botsing met vaste stad: geen tweede pagina, alias telt mee bij vaste stad', function () {
    maakPubliekAanbod("'s-Gravenhage", [
        'woonplaats' => "'s-Gravenhage",
        'locatie_label' => '2511 Den Haag',
        'lat' => 52.078, 'lon' => 4.311,
    ]);
    maakPubliekAanbod('Den Haag', [
        'woonplaats' => 'Den Haag',
        'locatie_label' => '2512 Den Haag',
        'lat' => 52.079, 'lon' => 4.312,
    ]);
    app(StadCatalogus::class)->forgetCache();

    $catalogus = app(StadCatalogus::class);
    $denHaag = $catalogus->find('den-haag');
    expect($denHaag)->not->toBeNull()
        ->and($denHaag['bron'])->toBe('vast')
        ->and($denHaag['aanbod'])->toBe(2);

    $viaAlias = $catalogus->find('s-gravenhage');
    expect($viaAlias)->not->toBeNull()
        ->and($viaAlias['slug'])->toBe('den-haag')
        ->and($viaAlias['aanbod'])->toBe(2);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'den-haag']))
        ->and($xml)->not->toContain('/gratis-planten/s-gravenhage');
});

test('geen dubbele stad door andere spelling van de plaatsnaam', function () {
    maakPubliekAanbod('Haarlem', ['lat' => 52.387, 'lon' => 4.646]);
    maakPubliekAanbod('haarlem', [
        'woonplaats' => 'haarlem',
        'locatie_label' => 'haarlem',
        'lat' => 52.388, 'lon' => 4.647,
    ]);
    app(StadCatalogus::class)->forgetCache();

    $rows = collect(app(StadCatalogus::class)->rapport())
        ->filter(fn ($r) => $r['slug'] === 'haarlem')
        ->values();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['aanbod'])->toBe(2)
        ->and($rows[0]['bron'])->toBe('vast');
});

test('sitemap bevat alleen geïndexeerde stadspagina’s', function () {
    // Haarlem heeft een zichtbare deelplek in JSON → geïndexeerd.
    // Rotterdam heeft (zonder seed-aanbod) 0 → niet in sitemap.
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('gratis-planten.index'))
        ->and($xml)->toContain(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->and($xml)->toContain(route('gratis-planten.show', ['stad' => 'antwerpen']))
        ->and($xml)->not->toContain(route('gratis-planten.show', ['stad' => 'rotterdam']));
});

test('onbekende stad blijft nette 404', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'onbestaande-stad']))
        ->assertNotFound();
});

test('enkelvoudige intro bij precies 1 aanbieding', function () {
    maakPubliekAanbod('Bunnik', ['lat' => 52.063, 'lon' => 5.198]);
    app(StadCatalogus::class)->forgetCache();

    $html = $this->get(route('gratis-planten.show', ['stad' => 'bunnik']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('1 gratis plant of stekje')
        ->and($html)->not->toContain('1 gratis planten')
        ->and($html)->toContain('Nabije steden')
        ->and($html)->toContain('Zelf iets aanbieden')
        ->and($html)->toContain('Zoek verder rond Bunnik');
});

test('gp:steden-aanbod toont aantallen zonder PII', function () {
    maakPubliekAanbod('Bunnik', [
        'lat' => 52.063,
        'lon' => 5.198,
        'email' => 'geheim@example.test',
    ]);
    app(StadCatalogus::class)->forgetCache();

    $this->artisan('gp:steden-aanbod', ['--refresh' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Bunnik')
        ->expectsOutputToContain('Drempel: 1');

    $output = Artisan::output();
    expect($output)->not->toContain('geheim@example.test')
        ->and($output)->not->toContain('@example');
});
