<?php

declare(strict_types=1);

test('/organisaties/steenbreek toont feiten, logo, geveltuin-beeld en artikel-link', function () {
    $html = $this->get(route('organisaties.show', ['slug' => 'steenbreek']))
        ->assertOk()
        ->assertSee('Stichting Steenbreek', false)
        ->assertSee('Minder steen, méér groen', false)
        ->assertSee('ondersteunt gemeenten, partners en inwoners met communicatie, kennisdeling en participatie bij het vergroenen van tuinen en straten', false)
        ->assertSee('gezonde, sociale én klimaatbestendige leefomgeving', false)
        ->assertSee('trend om te verstenen stoppen', false)
        ->assertSee('landelijke kennis- en netwerkorganisatie', false)
        ->assertSee('In 2015 werd Operatie Steenbreek opgericht', false)
        ->assertSee('Tegel eruit, plant erin', false)
        ->assertSee('Ruim 200 gemeenten doen mee', false)
        ->assertSee('Ze schreven over Gratis planten', false)
        ->assertSee('Wat Steenbreek doet', false)
        ->assertSee('ondersteunt en ontzorgt al 200 gemeenten', false)
        ->assertSee('voorbeeldtuinen, geveltuinen', false)
        ->assertSee('Meedoen', false)
        ->assertSee('Tegels eruit, plant erin', false)
        ->assertSee('Leg een geveltuin aan', false)
        ->assertSee('Organiseer een straatactie', false)
        ->assertSee('tegelruiltaxi', false)
        ->assertSee('NK Tegelwippen', false)
        ->assertSee('https://steenbreek.nl/', false)
        ->assertSee('https://steenbreek.nl/wie-we-zijn/', false)
        ->assertSee('https://steenbreek.nl/wat-we-doen/', false)
        ->assertSee('https://steenbreek.nl/missie/', false)
        ->assertSee('https://steenbreek.nl/word-steenbreker/inwoners/', false)
        ->assertSee('https://steenbreek.nl/word-steenbreker/inwoners/acties/', false)
        ->assertSee('https://steenbreek.nl/1-leg-een-geveltuin-aan/', false)
        ->assertSee('https://steenbreek.nl/deze-gemeenten-winnen-het-nk-tegelwippen-2026/', false)
        ->assertSee('Tijdens het NK Tegelwippen 2026', false)
        ->assertSee('bijna 5 miljoen tegels plaatsgemaakt voor groen', false)
        ->assertDontSee('maart tot en met oktober', false)
        ->assertSee('https://steenbreek.nl/gratis-planten-buren-verbinden-en-samen-de-wereld-groener-maken/', false)
        ->assertSee('logo-steenbreek.webp', false)
        ->assertSee('alt="Steenbreek"', false)
        ->assertSee('steenbreek-geveltuin-480.webp', false)
        ->assertSee('steenbreek-geveltuin-960.webp', false)
        ->assertSee('steenbreek-geveltuin-480.jpg', false)
        ->assertSee('Bloeiende geveltuin met klimrozen en stokrozen tegen een bakstenen gevel', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->assertDontSee('Paardekooper', false)
        ->assertDontSee('+31', false)
        ->assertDontSee('4794050', false)
        ->getContent();

    // Alleen de pagina-sectie (geen layout-JS): geen we/wij/ons/onze in de zichtbare tekst.
    if (preg_match('/<section\b[^>]*class="[^"]*plantenasiel-page[^"]*"[^>]*>(.*)<\/section>/is', $html, $m) === 1) {
        $text = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(0);
    } else {
        expect($html)->toContain('plantenasiel-page');
    }

    expect(is_file(public_path('assets/images/initiatieven/steenbreek-geveltuin-480.webp')))->toBeTrue();
    expect(is_file(public_path('assets/images/initiatieven/steenbreek-geveltuin-960.webp')))->toBeTrue();
    expect(is_file(public_path('assets/images/initiatieven/steenbreek-geveltuin-480.jpg')))->toBeTrue();
    expect(is_file(public_path('assets/images/initiatieven/steenbreek-geveltuin-960.jpg')))->toBeTrue();
});

test('initiatieven-gids toont Steenbreek; sitemap heeft org-URL', function () {
    $this->get(route('partners'))
        ->assertOk()
        ->assertSee('Stichting Steenbreek', false)
        ->assertSee('Landelijke kennis- en netwerkorganisatie', false)
        ->assertSee('geveltuinen', false)
        ->assertSee(route('organisaties.show', ['slug' => 'steenbreek']), false);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/organisaties/steenbreek');
});
