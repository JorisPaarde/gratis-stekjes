<?php

declare(strict_types=1);

use Database\Seeders\DeelplekkenSeeder;

/**
 * Browser-/kaart-init: L.map zonder view laadde geen tiles (regressie 0.27.0).
 * Pest Feature dekt de bestaande E2E-setup (geen Playwright in deze repo).
 */
test('zoeken.js en deelplekken.js zetten view direct bij L.map (fitBounds NL+BE)', function () {
    foreach (['resources/js/zoeken.js', 'resources/js/deelplekken.js'] as $rel) {
        $js = (string) file_get_contents(base_path($rel));
        expect($js)->not->toBe('');

        // Directe beginview bij aanmaken — niet alleen in whenReady.
        expect($js)->toMatch('/L\.map\([^)]+\)\.fitBounds\(/')
            ->and($js)->toContain('[49.45, 2.4]')
            ->and($js)->toContain('[53.55, 7.25]')
            ->and($js)->toContain('maxZoom: 8')
            ->and($js)->toContain('zoomSnap: 0.25')
            ->and($js)->toContain('padding: [4, 4]')
            ->and($js)->toContain('getBoundsZoom(bounds, true')
            ->and($js)->toContain('invalidateSize')
            ->and($js)->toContain('tile.openstreetmap.org');

        // Geen kale L.map(el) zonder chained view vóór tileLayer.
        expect($js)->not->toMatch('/L\.map\([^)]+\);\s*\n\s*L\.tileLayer/');
    }
});

test('kaartvak is hoger zodat NL+BE het kader vult (desktop + mobiel)', function () {
    foreach (['resources/css/aanbod.css', 'resources/css/deelplekken.css'] as $rel) {
        $css = (string) file_get_contents(base_path($rel));
        expect($css)->toContain('height: 360px')
            ->and($css)->toContain('min-height: 360px')
            ->and($css)->toContain('height: 480px')
            ->and($css)->toContain('min-height: 480px')
            ->and($css)->not->toMatch('/#(?:kaart|deelplekken-kaart)\s*\{[^}]*min-height:\s*320px/');
    }
});

test('.htaccess redirect www/http naar https://gratisplanten.nl (vóór front controller)', function () {
    $ht = (string) file_get_contents(base_path('public/.htaccess'));
    expect($ht)->toContain('RewriteCond %{HTTP_HOST} ^www\.gratisplanten\.nl$ [NC]')
        ->and($ht)->toContain('RewriteCond %{HTTP_HOST} ^gratisplanten\.nl$ [NC]')
        ->and($ht)->toContain('RewriteCond %{HTTPS} !=on')
        ->and($ht)->toContain('RewriteRule ^ https://gratisplanten.nl%{REQUEST_URI} [L,R=301]');

    $gratisstekjesPos = strpos($ht, 'gratisstekjes\.nl');
    $wwwPos = strpos($ht, 'www\.gratisplanten\.nl');
    $frontPos = strpos($ht, 'RewriteRule ^ index.php [L]');
    expect($gratisstekjesPos)->toBeInt()
        ->and($wwwPos)->toBeInt()
        ->and($frontPos)->toBeInt()
        ->and($wwwPos)->toBeGreaterThan($gratisstekjesPos)
        ->and($frontPos)->toBeGreaterThan($wwwPos);
});

test('aanbodpagina levert kaartcontainer, Leaflet en markerdata bij openen', function () {
    $this->seed(DeelplekkenSeeder::class);

    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('id="kaart"', false)
        ->assertSee('unpkg.com/leaflet@1.9.4/dist/leaflet.js', false)
        ->assertSee('role="img"', false)
        ->assertSee('api_partners', false)
        ->assertSee('api_deelplekken', false);

    $partners = $this->getJson(route('api.partners'))->assertOk()->json('items');
    $deelplekken = $this->getJson(route('api.deelplekken'))->assertOk()->json('items');

    expect($partners)->toBeArray()->and($deelplekken)->not->toBeEmpty();

    $withCoords = collect($deelplekken)->filter(
        fn ($item) => isset($item['lat']) && (isset($item['lng']) || isset($item['lon']))
    );
    expect($withCoords)->not->toBeEmpty();
});

test('deelplekkenpagina levert kaartcontainer, Leaflet en markers bij openen', function () {
    $this->seed(DeelplekkenSeeder::class);

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertSee('id="deelplekken-kaart"', false)
        ->assertSee('unpkg.com/leaflet@1.9.4/dist/leaflet.js', false)
        ->assertSee('aria-label="Kaart met deelplekken voor planten"', false);

    $items = $this->getJson(route('api.deelplekken'))->assertOk()->json('items');
    expect($items)->not->toBeEmpty();
    expect(collect($items)->firstWhere('naam', 'Plantenasiel Haarlem'))->not->toBeNull();
});

test('zoeken na postcode zoomt rond resultaat (geen landelijke fitBounds)', function () {
    $js = (string) file_get_contents(base_path('resources/js/zoeken.js'));

    // Na zoeken: markers zonder fitPartners, daarna setView op zoekmeta.
    expect($js)->toContain('refreshMarkers(false)')
        ->and($js)->toContain('map.setView([meta.zoek_lat, meta.zoek_lon], 11)')
        ->and($js)->toContain('getBoundsZoom(L.latLngBounds(nlBeBounds)');
});
