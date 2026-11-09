<?php

declare(strict_types=1);

test('in-de-media pagina laadt met intro, kaart, redactie en doe-mee', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    $response = $this->get(route('in-de-media'))->assertOk();

    $response
        ->assertSee('Gratis planten in de media', false)
        ->assertSee('Soms verschijnt Gratis planten in het nieuws', false)
        ->assertSee('Positief nieuws', false)
        ->assertSee('Een plant over? Maak er een buur blij mee.', false)
        ->assertSee('9 november 2026', false)
        ->assertSee('selectie- en samenvattingsdienst', false)
        ->assertSee('Op 9 november 2026 plaatste Positief nieuws een artikel over Gratis planten met de kop «Een plant over? Maak er een buur blij mee».', false)
        ->assertDontSee('via Gratisplanten.nl planten, stekjes of zaden bij buren', false)
        ->assertSee('https://positief-nieuws.nl/artikelen/2026-11-09/gratis-planten-verbindt-buren/', false)
        ->assertSee('class="media-kaart', false)
        ->assertSee('/assets/images/in-de-media/positief-nieuws-2026-11-09.jpg', false)
        ->assertSee('Beeld: Positief nieuws', false)
        ->assertSee('Lees het artikel', false)
        ->assertSee('Voor redacties', false)
        ->assertSee('Schrijf jij over Gratis planten? Dan delen wij je artikel op Facebook en schrijven we hier een kort stukje met een link terug naar jouw artikel.', false)
        ->assertSee('info@gratisplanten.nl', false)
        ->assertSee('Kernfeiten', false)
        ->assertSee('Zonder reclame', false)
        ->assertSee('Nederland en België', false)
        ->assertSee('We bereiken veel plantenliefhebbers in heel Nederland en België.', false)
        ->assertSee('er wordt niets verkocht', false)
        ->assertSee('Doe je mee?', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"NewsArticle"', false)
        ->assertDontSee('Ben je journalist?', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);

    $html = $response->getContent();
    expect(preg_match('/<meta name="description" content="([^"]*)"/', $html, $m))->toBe(1);
    $len = mb_strlen(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
    expect($len)->toBeGreaterThanOrEqual(70)->and($len)->toBeLessThanOrEqual(160);

    if (is_file(public_path('assets/icons/icon-512.png'))) {
        expect($html)->toContain('Logo (PNG)');
    }
});

test('in-de-media noemt deelplekken zonder exact aantal', function () {
    $html = $this->get(route('in-de-media'))->assertOk()->getContent();

    expect($html)->toContain('er staan steeds meer vaste deelplekken op de kaart')
        ->and($html)->not->toMatch('/er staan \d[\d\.]* vaste deelplekken/');
});

test('in-de-media staat in menu, footer (onder Over) en sitemap', function () {
    $home = $this->get('/')->assertOk()->getContent();

    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $home, $nav))->toBe(1);
    expect($nav[1])->toContain('href="'.route('in-de-media').'"')
        ->and($nav[1])->toContain('In de media');

    expect(preg_match('/<nav class="footer-links"[^>]*>(.*?)<\/nav>/s', $home, $footer))->toBe(1);
    expect($footer[1])->toContain('href="'.route('in-de-media').'"')
        ->and($footer[1])->toContain('In de media');
    expect(preg_match(
        '/href="'.preg_quote(route('over'), '/').'"[^>]*>Over<\/a>\s*<\/li>\s*<li>\s*<a href="'.preg_quote(route('in-de-media'), '/').'"[^>]*>In de media<\/a>/s',
        $footer[1]
    ))->toBe(1);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('in-de-media'));
});
