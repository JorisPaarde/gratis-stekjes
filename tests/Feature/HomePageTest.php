<?php

declare(strict_types=1);
use App\Models\Aanbod;
use App\Models\User;
use Database\Seeders\DeelplekkenSeeder;
use Illuminate\Support\Facades\Schema;

test('homepage is 200 en toont merk Gratis planten', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Gratis planten', false);
    $response->assertSee('class="hero-brand"', false);
    $response->assertSee('>Gratis planten en stekjes in je buurt</h1>', false);
    $response->assertSee('assets/images/hero/hero-stekjes-1280.webp', false);
    $response->assertSee('fetchpriority="high"', false);
    $response->assertSee('Doe mee', false);
    $response->assertSee('Hoe werkt het', false);
    $response->assertSee('help-mee', false);
    $response->assertSee('Ik wil op een andere manier helpen', false);
    $response->assertSee(route('invasieve-soorten'), false);
    $response->assertDontSee('badge-soon', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"WebSite"', false);
    $response->assertDontSee('mensen doen al mee', false);
});

test('hero heeft een zoekveld dat naar /aanbod?q= gaat', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('class="hero-zoek"')
        ->and($html)->toContain('action="'.route('aanbod').'"')
        ->and($html)->toContain('name="q"')
        ->and($html)->toContain('Zoek in de buurt');
});

test('homepage is ingekort: geen Waarom, Veilig delen, Initiatieven- of Verder kijken-sectie', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Waarom?')
        ->and($html)->not->toContain('id="veiligheid"')
        ->and($html)->not->toContain('id="initiatieven"')
        ->and($html)->not->toContain('Verder kijken')
        ->and($html)->not->toContain('voedselbosbouw.org')
        ->and($html)->not->toContain('staan klaar');
});

test('hoe werkt het heeft drie stappen', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/<ol class="steps">(.*?)<\/ol>/s', $html, $m);

    expect($m)->not->toBeEmpty();
    expect(substr_count($m[1], '<li>'))->toBe(3);
});

test('blok «Hier kun je nu al terecht» toont tellingen per soort deelplek', function () {
    $this->seed(DeelplekkenSeeder::class);
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Hier kun je nu al terecht')
        ->and($html)->toMatch('/<strong>\d+<\/strong> zaadbibliotheken/')
        ->and($html)->toContain('plekken in Nederland en België');
    // Zonder aanbod geen lege «Net aangeboden»-kop.
    expect($html)->not->toContain('Net aangeboden');
});

test('zonder deelplekken en aanbod verdwijnt het blok (geen lege staat)', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Hier kun je nu al terecht');
});

test('blok toont maximaal zes nieuwste aanbiedingen', function () {
    $user = User::factory()->create([
        'name' => 'Sam',
        'locatie_label' => 'Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'active' => true,
        'email_verified_at' => now(),
    ]);
    foreach (range(1, 8) as $i) {
        Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => 'uitdelen',
            'tekst' => 'Stekje nummer '.$i,
            'visible' => true,
            'expires_at' => now()->addDays(30),
        ])->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
    }

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Net aangeboden')
        ->and(substr_count($html, 'class="aanbod-kaart"'))->toBe(6)
        ->and($html)->toContain('Stekje nummer 8')
        ->and($html)->not->toContain('Stekje nummer 1<')
        ->and($html)->toContain(route('aanbod', ['q' => 'Haarlem']));
});

test('footer toont geen versienummer, maar de deploy-smoke vindt het nog', function () {
    config(['site.version' => '9.9.9']);
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<!-- Versie 9.9.9 -->');
    $zonderCommentaar = preg_replace('/<!--.*?-->/s', '', $html);
    expect($zonderCommentaar)->not->toContain('Versie 9.9.9');
});

test('nu te stekken toont geen tijdzone en geen placeholder', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('tijdzone')
        ->and($html)->not->toContain('Europe/Amsterdam')
        ->and($html)->not->toContain('volgen binnenkort');
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
        ->assertSee('Privacyverklaring', false)
        ->assertSee('Gegevensbeschermingsautoriteit', false)
        ->assertSee('Gebruik en recht', false);
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
        ->assertSee('Sitemap: https://gratisplanten.nl/sitemap.xml', false);
});
