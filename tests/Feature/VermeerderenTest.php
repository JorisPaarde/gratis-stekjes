<?php

declare(strict_types=1);

test('/vermeerderen is de hub met methodekaarten, kalender en links naar de pijlers', function () {
    $html = $this->get(route('vermeerderen'))->assertOk()->getContent();
    expect($html)->toContain('<h1 class="page-title">Planten vermeerderen</h1>')
        ->and($html)->toContain('rel="canonical" href="'.route('vermeerderen').'"')
        ->and($html)->toContain('class="kb-kaarten"')
        ->and($html)->toContain('class="kb-kalender"')
        ->and($html)->toContain(route('stektips.artikel', ['slug' => 'scheuren']))
        ->and($html)->toContain(route('stektips.artikel', ['slug' => 'zaaien']))
        ->and($html)->toContain(route('stektips.artikel', ['slug' => 'afleggen']))
        ->and($html)->toContain('href="'.route('stektips.index').'"');
});

test('oude URL van «vermeerderen overig» verwijst permanent naar /vermeerderen', function () {
    $this->get('/stektips/artikel/vermeerderen')->assertRedirect(route('vermeerderen'))->assertStatus(301);
});

test('nieuwe pijlers scheuren, zaaien en afleggen laden met kalender en stappen', function () {
    foreach (['scheuren', 'zaaien', 'afleggen'] as $slug) {
        $html = $this->get(route('stektips.artikel', ['slug' => $slug]))->assertOk()->getContent();
        expect($html)->toContain('class="kb-kalender"')
            ->and($html)->toContain('class="kb-stappen"')
            ->and($html)->toContain('data-video-id=')
            ->and($html)->toContain('>Vermeerderen</a>');
    }
});

test('menu en footer verwijzen naar Vermeerderen; sitemap bevat /vermeerderen maar niet de oude URL', function () {
    $home = $this->get('/')->assertOk()->getContent();
    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $home, $nav))->toBe(1);
    expect($nav[1])->toContain('href="'.route('vermeerderen').'"')->and($nav[1])->toContain('Vermeerderen');
    expect($home)->toContain('Zelf vermeerderen');

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('<loc>'.route('vermeerderen').'</loc>')
        ->and($xml)->not->toContain('/stektips/artikel/vermeerderen<')
        ->and($xml)->toContain('/stektips/artikel/scheuren<');
});

test('overzicht toont filter-onderwerpen Scheuren, Zaaien en Afleggen', function () {
    $html = $this->get(route('stektips.index'))->assertOk()->getContent();
    expect($html)->toContain('>Scheuren<')->and($html)->toContain('>Zaaien<')->and($html)->toContain('>Afleggen<');
});
