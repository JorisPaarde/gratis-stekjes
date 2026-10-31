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

test('accountpagina toont beginscherm-installatieblok', function () {
    $user = User::factory()->create([
        'name' => 'Sam',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk()
        ->assertSee('Zet Gratis planten op je beginscherm', false)
        ->assertSee('Zet op beginscherm', false)
        ->assertSee('Toevoegen aan startscherm', false)
        ->assertSee('data-pwa-install', false)
        ->assertSee('Installeer Gratis planten', false)
        ->assertSee('geen pushmeldingen', false)
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
