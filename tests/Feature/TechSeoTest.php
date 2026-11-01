<?php

declare(strict_types=1);

use App\Support\CanonicalUrl;

test('canonical en og:url komen uit APP_URL, niet uit request-host', function () {
    config(['app.url' => 'https://gratisplanten.nl', 'site.noindex' => false]);

    $html = $this->get('http://www.gratisplanten.nl/')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('rel="canonical" href="https://gratisplanten.nl"')
        ->and($html)->toContain('property="og:url" content="https://gratisplanten.nl"')
        ->and($html)->not->toContain('rel="canonical" href="http://www.gratisplanten.nl"')
        ->and($html)->not->toContain('rel="canonical" href="https://www.gratisplanten.nl"');
});

test('filter-URL’s canonicaliseren naar de pagina zonder query', function () {
    config(['app.url' => 'https://gratisplanten.nl']);

    $aanbod = $this->get('/aanbod?plant=munt')->assertOk()->getContent();
    expect($aanbod)->toContain('rel="canonical" href="'.route('aanbod').'"')
        ->and($aanbod)->not->toContain('canonical" href="'.route('aanbod', ['plant' => 'munt']).'"');

    $deelplekken = $this->get('/deelplekken?plaats=Amsterdam&type=plantenasiel')->assertOk()->getContent();
    expect($deelplekken)->toContain('rel="canonical" href="'.route('deelplekken').'"')
        ->and($deelplekken)->not->toContain('plaats=Amsterdam');
});

test('account inloggen en registreren hebben noindex', function () {
    config(['site.noindex' => false]);

    $this->get(route('account.inloggen'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, nofollow"', false);

    $this->get(route('account.registreren'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

test('index.php redirect naar pad zonder index.php', function () {
    config(['app.url' => 'https://gratisplanten.nl']);

    $this->get('/index.php')
        ->assertRedirect('https://gratisplanten.nl')
        ->assertStatus(301);

    $this->get('/index.php/over')
        ->assertRedirect('https://gratisplanten.nl/over')
        ->assertStatus(301);
});

test('404 heeft geen canonical', function () {
    $html = $this->get('/pagina-die-niet-bestaat-xyz-seo')
        ->assertNotFound()
        ->getContent();

    expect($html)->not->toContain('rel="canonical"');
});

test('og:image en twitter:card: per pagina een 1200×630-afbeelding met absolute URL', function () {
    config(['app.url' => 'https://gratisplanten.nl']);

    $cases = [
        '/' => '/assets/og/home.jpg',
        '/aanbod' => '/assets/og/aanbod.jpg',
        '/stektips' => '/assets/og/stektips.jpg',
        '/stektips/monstera' => '/assets/og/soort/monstera.jpg',
        '/deelplekken' => '/assets/og/deelplekken.jpg',
        '/hoe-werkt-het' => '/assets/og/hoe-werkt-het.jpg',
        '/gratis-planten' => '/assets/og/gratis-planten.jpg',
        '/gratis-planten/haarlem' => '/assets/og/stad/haarlem.jpg',
        '/over' => '/assets/og/standaard.jpg',
    ];
    foreach ($cases as $url => $pad) {
        expect(is_file(public_path(ltrim($pad, '/'))))->toBeTrue("ontbreekt: {$pad}");
        $abs = CanonicalUrl::asset($pad);
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('property="og:image" content="'.$abs.'"')
            ->and($html)->toContain('name="twitter:image" content="'.$abs.'"')
            ->and($html)->toContain('property="og:image:width" content="1200"')
            ->and($html)->toContain('name="twitter:card" content="summary_large_image"')
            ->and($abs)->toStartWith('https://gratisplanten.nl/');
    }
});

test('elke stektip en elke stad heeft een eigen social image', function () {
    foreach (json_decode((string) file_get_contents(base_path('data/stektips.json')), true)['soorten'] as $s) {
        expect(is_file(public_path('assets/og/soort/'.$s['slug'].'.jpg')))->toBeTrue('ontbreekt soort '.$s['slug']);
    }
    foreach (json_decode((string) file_get_contents(base_path('data/steden.json')), true)['steden'] as $c) {
        expect(is_file(public_path('assets/og/stad/'.$c['slug'].'.jpg')))->toBeTrue('ontbreekt stad '.$c['slug']);
    }
});

test('sitemap heeft lastmod op basis van bestandsdatum, niet nu', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)->toContain('<lastmod>')
        ->and($xml)->toMatch('/<lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/');

    $today = gmdate('Y-m-d');
    // Mag toevallig vandaag zijn als een contentbestand vandaag is gewijzigd,
    // maar mag niet uitsluitend 'nu' zijn zonder lastmod-bron: elke url heeft lastmod.
    expect(substr_count($xml, '<url>'))->toBe(substr_count($xml, '<lastmod>'));

    // Home lastmod volgt VERSION / blade-mtime, niet request-tijd.
    expect($xml)->toContain('<loc>'.e(url('/')).'</loc><lastmod>');
    // Geen ISO-datetime met seconden (zou op 'nu' wijzen).
    expect($xml)->not->toMatch('/<lastmod>\d{4}-\d{2}-\d{2}T/');
    expect($today)->not->toBe(''); // sanity
});

test('home schema heeft Organization en areaServed NL+BE', function () {
    config(['app.url' => 'https://gratisplanten.nl', 'site.name' => 'Gratis planten']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('"@type":"Organization"')
        ->and($html)->toContain('"name":"Gratis planten"')
        ->and($html)->toContain('"@type":"WebSite"')
        ->and($html)->toContain('"areaServed"')
        ->and($html)->toContain('"name":"NL"')
        ->and($html)->toContain('"name":"BE"')
        ->and($html)->toContain('"logo":"https://gratisplanten.nl/assets/icons/icon-512.png"');
});

test('publieke pagina’s zetten geen Set-Cookie; account-inloggen wel', function () {
    config(['site.noindex' => false]);

    foreach (['/', '/aanbod', '/gratis-planten/amsterdam'] as $path) {
        $response = $this->get($path)->assertOk();
        $cookies = collect($response->headers->getCookies())->map->getName()->all();
        expect($cookies)->toBeEmpty("Verwacht geen cookies op {$path}, kreeg: ".implode(',', $cookies));
    }

    $login = $this->get(route('account.inloggen'))->assertOk();
    $loginCookies = collect($login->headers->getCookies())->map->getName()->all();
    expect($loginCookies)->toContain(config('session.cookie'));
});

test('meta descriptions privacy contact help-mee aanmelden vallen binnen 70–160', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    foreach ([
        '/privacy',
        '/contact',
        '/help-mee',
        '/voor-organisaties/aanmelden',
        '/stektips/aardbei',
        '/stektips/artikel/hortensia-snoeien',
        '/stektips/artikel/stekpoeder',
        '/organisaties/velt',
        '/organisaties/meerbomen-nu',
        '/organisaties/struikroven',
        '/organisaties/netwerk-voedselbosbouw',
        '/organisaties/plantenasiel-zeist',
    ] as $path) {
        $html = $this->get($path)->assertOk()->getContent();
        expect(preg_match('/<meta name="description" content="([^"]*)"/', $html, $m))->toBe(1);
        $len = mb_strlen(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
        expect($len)->toBeGreaterThanOrEqual(70, "{$path} te kort ({$len})")
            ->and($len)->toBeLessThanOrEqual(160, "{$path} te lang ({$len})");
    }
});

test('login-formulier blijft werken met sessie en CSRF', function () {
    $html = $this->get(route('account.inloggen'))->assertOk()->getContent();
    expect($html)->toContain('name="_token"');

    $this->from(route('account.inloggen'))
        ->post(route('account.inloggen'), [
            'email' => 'onbekend@example.test',
            'password' => str_repeat('x', 12),
        ])
        ->assertRedirect(route('account.inloggen'));
});

test('uitloggen zet flash-status en toont die op home met sessiecookie', function () {
    $user = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $logout = $this->actingAs($user)
        ->post(route('account.uitloggen'))
        ->assertRedirect(route('home'))
        ->assertSessionHas('status', 'Je bent uitgelogd.');

    $sessionCookie = collect($logout->headers->getCookies())
        ->first(fn ($c) => $c->getName() === config('session.cookie'));

    expect($sessionCookie)->not->toBeNull();

    // Zelfde cookie als de browser zou meesturen → sessie start op publieke home.
    $this->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Je bent uitgelogd.', false);
});
