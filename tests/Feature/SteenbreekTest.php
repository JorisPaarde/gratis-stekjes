<?php

declare(strict_types=1);

test('/organisaties/steenbreek toont feiten, logo en artikel-link', function () {
    $html = $this->get(route('organisaties.show', ['slug' => 'steenbreek']))
        ->assertOk()
        ->assertSee('Stichting Steenbreek', false)
        ->assertSee('Minder steen, méér groen', false)
        ->assertSee('ondersteunt gemeenten, partners en inwoners met communicatie, kennisdeling en participatie bij het vergroenen van tuinen en straten', false)
        ->assertSee('In 2015 werd Operatie Steenbreek opgericht', false)
        ->assertSee('Tegel eruit, plant erin', false)
        ->assertSee('Ruim 200 gemeenten doen mee', false)
        ->assertSee('Ze schreven over Gratis planten', false)
        ->assertSee('https://steenbreek.nl/', false)
        ->assertSee('https://steenbreek.nl/wie-we-zijn/', false)
        ->assertSee('https://steenbreek.nl/wat-we-doen/', false)
        ->assertSee('https://steenbreek.nl/gratis-planten-buren-verbinden-en-samen-de-wereld-groener-maken/', false)
        ->assertSee('logo-steenbreek.webp', false)
        ->assertSee('alt="Steenbreek"', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->getContent();

    // Alleen de pagina-sectie (geen layout-JS): geen we/wij/ons/onze in de zichtbare tekst.
    if (preg_match('/<section\b[^>]*class="[^"]*plantenasiel-page[^"]*"[^>]*>(.*)<\/section>/is', $html, $m) === 1) {
        $text = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(0);
    } else {
        expect($html)->toContain('plantenasiel-page');
    }
});

test('initiatieven-gids toont Steenbreek; sitemap heeft org-URL', function () {
    $this->get(route('partners'))
        ->assertOk()
        ->assertSee('Stichting Steenbreek', false)
        ->assertSee(route('organisaties.show', ['slug' => 'steenbreek']), false);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/organisaties/steenbreek');
});
