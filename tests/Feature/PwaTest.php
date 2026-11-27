<?php

declare(strict_types=1);

use App\Models\User;

test('manifest-route levert web app manifest met naam, kleuren en iconen', function () {
    config([
        'site.name' => 'Gratis planten',
        'site.theme_color' => '#1F4D2B',
        'site.background_color' => '#E6F2E1',
        'site.description' => 'Gratis planten en stekjes weggeven en ophalen. De enige tegenprestatie is een praatje.',
    ]);

    $response = $this->get(route('manifest'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/manifest+json');

    $data = $response->json();
    expect($data['name'])->toBe('Gratis planten')
        ->and($data['short_name'])->toBe('Gratis planten')
        ->and($data['start_url'])->toBe('/')
        ->and($data['display'])->toBe('standalone')
        ->and($data['lang'])->toBe('nl')
        ->and($data['theme_color'])->toBe('#1F4D2B')
        ->and($data['background_color'])->toBe('#E6F2E1');

    $purposes = collect($data['icons'])->pluck('purpose')->all();
    expect($purposes)->toContain('any')->toContain('maskable');

    $sizes = collect($data['icons'])->pluck('sizes')->all();
    expect($sizes)->toContain('192x192')->toContain('512x512');
});

test('homepage koppelt manifest en toont theme-color uit palet', function () {
    config(['site.name' => 'Gratis planten', 'site.theme_color' => '#1F4D2B']);

    $this->get('/')
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('#1F4D2B', false)
        ->assertSee('name="gp-app-version"', false)
        ->assertSee('apple-touch-icon', false);
});

test('CSP staat worker-src en manifest-src toe voor PWA', function () {
    $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->toContain("worker-src 'self'")
        ->and($csp)->toContain("manifest-src 'self'");
});

test('accountpagina verwijst beginscherm-blok naar /app', function () {
    $user = User::factory()->create([
        'name' => 'Sam',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk()
        ->assertSee('Zet Gratis planten op je beginscherm', false)
        ->assertSee('geen pushmeldingen', false)
        ->assertSee('href="'.route('app').'"', false)
        ->assertSee('Gratis planten als app', false)
        ->assertDontSee('Installeer Gratis planten', false)
        ->assertDontSee('push-notificatie', false);
});

test('service worker en offline-pagina bestaan in public', function () {
    expect(is_file(public_path('sw.js')))->toBeTrue();
    expect(is_file(public_path('offline.html')))->toBeTrue();
    expect(is_file(public_path('assets/icons/icon-192.png')))->toBeTrue();
    expect(is_file(public_path('assets/icons/icon-512.png')))->toBeTrue();
    expect(is_file(public_path('assets/icons/icon-192-maskable.png')))->toBeTrue();
    expect(is_file(public_path('assets/icons/icon-512-maskable.png')))->toBeTrue();

    $sw = (string) file_get_contents(public_path('sw.js'));
    expect($sw)->toContain('gp-shell-v')
        ->and($sw)->toContain('/account')
        ->and($sw)->toContain('/beheer')
        ->and($sw)->toContain('/cron')
        ->and($sw)->toContain('/deploy')
        ->and($sw)->toContain('/media')
        ->and($sw)->toContain('/ervaringen/')
        ->and($sw)->toContain('/foto')
        ->and($sw)->toContain('/build/assets/')
        ->and($sw)->toContain('/offline.html');

    // Prefix /media dekt /media/aanbod-foto én /media-uploads.
    expect(str_contains($sw, 'pathname.indexOf("/media") === 0'))->toBeTrue();

    $offline = (string) file_get_contents(public_path('offline.html'));
    expect($offline)->toContain('Je bent offline')
        ->and($offline)->toContain('lang="nl"');
});

test('pagina /app toont de vaste copy en alle situatieschermen', function () {
    $this->get(route('app'))
        ->assertOk()
        ->assertSee('<h1 class="page-title">Gratis planten als app</h1>', false)
        ->assertSee('Zet Gratis planten op je beginscherm. Dan open je het met één tik, net als een app. Gratis, zonder app store en zonder meldingen.', false)
        ->assertSee('Installeer in één tik', false)
        ->assertSee('Installeer de app', false)
        ->assertSee('Je telefoon vraagt of het goed is. Tik dan op «Installeren».', false)
        ->assertSee('In 3 stappen op je iPhone', false)
        ->assertSee('Tik onderin Safari op', false)
        ->assertSee('Zet op beginscherm', false)
        ->assertSee('Tik rechtsboven op', false)
        ->assertSee('Voeg toe', false)
        ->assertSee('Open deze pagina in Safari of Chrome', false)
        ->assertSee('In deze browser lukt het niet. Op een iPhone kan het in Safari, op Android in Chrome.', false)
        ->assertSee('Kopieer de link', false)
        ->assertSee('Plak de link in Safari of Chrome en kom terug op deze pagina.', false)
        ->assertSee('Op je telefoon', false)
        ->assertSee('De app is vooral handig op je telefoon. Open', false)
        ->assertSee('gratisplanten.nl/app', false)
        ->assertSee('Op je computer gebruik je gewoon de site.', false)
        ->assertSee('Je hebt de app al', false)
        ->assertSee('Gratis planten staat op je beginscherm. Tik op het icoon, dan open je het meteen.', false)
        ->assertSee('← Terug naar de site', false)
        ->assertSee('data-app-scherm="android"', false)
        ->assertSee('data-app-scherm="iphone"', false)
        ->assertSee('data-app-scherm="anders"', false)
        ->assertSee('data-app-scherm="desktop"', false)
        ->assertSee('data-app-scherm="geinstalleerd"', false)
        ->assertDontSee('beforeinstallprompt', false)
        ->assertDontSee('service worker', false)
        ->assertDontSee('PWA', false);
});

test('menu en footer hebben de link Gratis planten als app, desktop-nav verbergt hem in CSS', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Gratis planten als app')
        ->and($html)->toContain('class="menu-sep"')
        ->and($html)->toContain('class="app-link"')
        ->and($html)->toContain('href="'.route('app').'"')
        ->and($html)->toContain('class="footer-app"');

    // De link staat ná Doe mee (uitgelogd) en ná een scheidingslijn.
    $navStart = strpos($html, 'id="hoofdmenu"');
    $navEnd = strpos($html, '</nav>', (int) $navStart);
    expect($navStart)->toBeInt()->and($navEnd)->toBeInt();
    $nav = substr($html, (int) $navStart, (int) $navEnd - (int) $navStart);
    $doeMee = strpos($nav, 'Doe mee');
    $sep = strpos($nav, 'class="menu-sep"');
    $appLink = strpos($nav, 'class="app-link"');
    expect($doeMee)->toBeInt()
        ->and($sep)->toBeInt()
        ->and($appLink)->toBeInt()
        ->and($doeMee)->toBeLessThan($sep)
        ->and($sep)->toBeLessThan($appLink);

    $footerStart = strpos($html, 'class="footer-merk"');
    $footerMerkEnd = strpos($html, 'class="footer-links"', (int) $footerStart);
    $merk = substr($html, (int) $footerStart, (int) $footerMerkEnd - (int) $footerStart);
    $cta = strpos($merk, 'Doe mee');
    $app = strpos($merk, 'class="footer-app"');
    $mail = strpos($merk, 'footer-contact');
    expect($cta)->toBeInt()
        ->and($app)->toBeInt()
        ->and($mail)->toBeInt()
        ->and($cta)->toBeLessThan($app)
        ->and($app)->toBeLessThan($mail);

    $css = (string) file_get_contents(resource_path('css/site.css'));
    expect($css)->toContain('.site-nav .menu-sep,')
        ->and($css)->toContain('.site-nav .app-link { display: none !important; }');
});

test('menu-paneel heeft een dekkende achtergrond', function () {
    $css = (string) file_get_contents(resource_path('css/site.css'));

    expect($css)->toContain('background: #fff;')
        ->and($css)->not->toContain('background: rgba(255, 255, 255, 0.98)');
});

test('layout bevat de balk Ook als app, standaard verborgen, en een markering na het eerste aanbod', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-app-balk')
        ->and($html)->toContain('Ook als app')
        ->and($html)->toContain('Installeren')
        ->and($html)->toContain('aria-label="Sluiten"')
        ->and($html)->toContain('data-app-balk-sluit')
        ->and($html)->not->toContain('data-app-balk-na-aanbod');

    $this->withSession([
        'status' => 'Gelukt, je aanbod staat online! Nog iets over? Bied het hieronder ook aan.',
    ])->get('/')->assertOk()->assertSee('data-app-balk-na-aanbod', false);
});

test('sitemap bevat /app', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.e(url('/app')).'</loc>');
});
