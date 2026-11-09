<?php

declare(strict_types=1);

test('in-de-media pagina laadt met kaart, seo en schema', function () {
    config(['site.name' => 'Gratis planten', 'site.noindex' => false]);

    $response = $this->get(route('in-de-media'))->assertOk();

    $response
        ->assertSee('Gratis planten in de media', false)
        ->assertSee('Positief nieuws', false)
        ->assertSee('Een plant over? Maak er een buur blij mee.', false)
        ->assertSee('9 november 2026', false)
        ->assertSee('https://positief-nieuws.nl/artikelen/2026-11-09/gratis-planten-verbindt-buren/', false)
        ->assertSee('class="media-kaart', false)
        ->assertSee('/assets/images/in-de-media/positief-nieuws-2026-11-09.jpg', false)
        ->assertSee('Beeld: Positief nieuws', false)
        ->assertSee('Lees het artikel', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"NewsArticle"', false)
        ->assertDontSee('Ben je journalist?', false)
        ->assertDontSee('Artikelen en vermeldingen over', false)
        ->assertDontSee('name="robots" content="noindex, nofollow"', false);

    $html = $response->getContent();
    expect(preg_match('/<meta name="description" content="([^"]*)"/', $html, $m))->toBe(1);
    $len = mb_strlen(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
    expect($len)->toBeGreaterThanOrEqual(70)->and($len)->toBeLessThanOrEqual(160);
});

test('in-de-media staat in footer en sitemap', function () {
    $home = $this->get('/')->assertOk()->getContent();
    expect(preg_match('/<nav class="footer-links"[^>]*>(.*?)<\/nav>/s', $home, $footer))->toBe(1);
    expect($footer[1])->toContain('href="'.route('in-de-media').'"')
        ->and($footer[1])->toContain('In de media');

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('in-de-media'));
});
