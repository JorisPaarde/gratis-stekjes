<?php

declare(strict_types=1);
use App\Services\Stektips;

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

test('pijler uitlopers en kindjes laadt; hub toont «wat kun je nu vermeerderen»', function () {
    $html = $this->get(route('stektips.artikel', ['slug' => 'uitlopers']))->assertOk()->getContent();
    expect($html)->toContain('Uitlopers, kindjes en bollen')
        ->and($html)->toContain('class="kb-kalender"')
        ->and($html)->toContain('data-video-id="vQOg-2Qi4jM"');

    $hub = $this->get(route('vermeerderen'))->assertOk()->getContent();
    expect($hub)->toContain('Wat kun je nu vermeerderen?')
        ->and($hub)->toContain('class="kb-nu"')
        ->and($hub)->toContain(route('stektips.artikel', ['slug' => 'uitlopers']));
});

test('filter op manier toont soorten waarvoor die manier werkt, plus artikelen', function () {
    $html = $this->get(route('stektips.index', ['categorie' => 'uitlopers']))->assertOk()->getContent();
    expect($html)->toContain(route('stektips.show', ['slug' => 'aardbei']))
        ->and($html)->toContain(route('stektips.show', ['slug' => 'graslelie']))
        ->and($html)->toContain(route('stektips.artikel', ['slug' => 'uitlopers']))
        ->and($html)->not->toContain(route('stektips.show', ['slug' => 'lavendel']));

    $scheuren = $this->get(route('stektips.index', ['categorie' => 'scheuren']))->assertOk()->getContent();
    expect($scheuren)->toContain(route('stektips.show', ['slug' => 'hosta']));

    // Snoeien is een onderwerp, geen manier: alleen artikelen.
    $snoeien = $this->get(route('stektips.index', ['categorie' => 'snoeien']))->assertOk()->getContent();
    expect($snoeien)->not->toContain(route('stektips.show', ['slug' => 'aardbei']));
});

test('methodes worden afgeleid uit de tekst van een soort', function () {
    $s = app(Stektips::class);
    expect($s->methodes($s->find('aardbei')))->toContain('uitlopers')
        ->and($s->methodes($s->find('bieslook')))->toContain('scheuren')->toContain('zaaien')
        ->and($s->methodes($s->find('munt')))->toContain('stekken');
});

test('hub toont voedselbos- en inheems-filters en secties', function () {
    $hub = $this->get(route('vermeerderen'))->assertOk()->getContent();
    expect($hub)->toContain('Voedselbos')
        ->and($hub)->toContain('Inheems')
        ->and($hub)->toContain(route('stektips.index', ['tag' => 'voedselbos']))
        ->and($hub)->toContain(route('stektips.index', ['tag' => 'inheems']))
        ->and($hub)->toContain(route('stektips.show', ['slug' => 'daslook']))
        ->and($hub)->toContain(route('stektips.show', ['slug' => 'vingerhoedskruid']));
});

test('tag-filter voedselbos en inheems filtert soorten', function () {
    $vb = $this->get(route('stektips.index', ['tag' => 'voedselbos']))->assertOk()->getContent();
    expect($vb)->toContain(route('stektips.show', ['slug' => 'daslook']))
        ->and($vb)->toContain(route('stektips.show', ['slug' => 'sleedoorn']))
        ->and($vb)->not->toContain(route('stektips.show', ['slug' => 'vingerhoedskruid']));

    $ih = $this->get(route('stektips.index', ['tag' => 'inheems']))->assertOk()->getContent();
    expect($ih)->toContain(route('stektips.show', ['slug' => 'vingerhoedskruid']))
        ->and($ih)->toContain(route('stektips.show', ['slug' => 'gewone-margriet']));

    $show = $this->get(route('stektips.show', ['slug' => 'vingerhoedskruid']))->assertOk()->getContent();
    expect($show)->toContain('Giftig')
        ->and($show)->toContain('badge--inheems');
});
