<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Services\Steden;

test('overzicht gratis planten laadt met provincies', function () {
    $this->get(route('gratis-planten.index'))
        ->assertOk()
        ->assertSee('Gratis planten ophalen in jouw stad', false)
        ->assertSee('Noord-Holland', false)
        ->assertSee(route('gratis-planten.show', ['stad' => 'amsterdam']), false);
});

test('elke stad-slug geeft 200 met H1 en canonical', function () {
    $steden = app(Steden::class)->all();
    expect($steden)->toHaveCount(42);

    foreach ($steden as $stad) {
        $url = route('gratis-planten.show', ['stad' => $stad['slug']]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee('Gratis planten en stekjes in '.$stad['naam'], false);
        $response->assertSee('<link rel="canonical" href="'.$url.'"', false);
        $response->assertSee($stad['intro'], false);
    }
});

test('onbekende stad-slug geeft 404', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'onbestaande-stad']))
        ->assertNotFound();
});

test('sitemap bevat overzicht en steden', function () {
    $xml = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)->toContain(route('gratis-planten.index'));
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'haarlem']));
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'rotterdam']));
});

test('stadspagina FAQPage-schema is geldige JSON', function () {
    $html = $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<script type="application\/ld\+json">(.*?)<\/script>/s');
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    expect($matches[1])->not->toBeEmpty();

    $decoded = null;
    foreach ($matches[1] as $raw) {
        $json = json_decode(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if (is_array($json) && isset($json['@graph'])) {
            $decoded = $json;
            break;
        }
        if (is_array($json) && ($json['@type'] ?? '') === 'FAQPage') {
            $decoded = $json;
            break;
        }
    }
    expect($decoded)->toBeArray();

    $faq = null;
    if (($decoded['@type'] ?? '') === 'FAQPage') {
        $faq = $decoded;
    } else {
        foreach ($decoded['@graph'] as $node) {
            if (($node['@type'] ?? '') === 'FAQPage') {
                $faq = $node;
                break;
            }
        }
    }
    expect($faq)->toBeArray();
    expect($faq['mainEntity'] ?? null)->toBeArray()->not->toBeEmpty();
    foreach ($faq['mainEntity'] as $q) {
        expect($q['@type'] ?? null)->toBe('Question');
        expect($q['name'] ?? '')->not->toBe('');
        expect($q['acceptedAnswer']['@type'] ?? null)->toBe('Answer');
        expect($q['acceptedAnswer']['text'] ?? '')->not->toBe('');
    }
});

test('stadspagina toont aanbod binnen straal en lege staat elders', function () {
    $near = User::factory()->create([
        'name' => 'Sam',
        'locatie_label' => 'Haarlem',
        'lat' => 52.387,
        'lon' => 4.646,
        'telefoon' => '',
    ]);
    Aanbod::query()->create([
        'user_id' => $near->id,
        'type' => 'uitdelen',
        'tekst' => 'Bessenstekjes uit Haarlem-centrum',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertSee('Bessenstekjes uit Haarlem-centrum', false)
        ->assertDontSee('Nog geen openbaar aanbod', false);

    $this->get(route('gratis-planten.show', ['stad' => 'groningen']))
        ->assertOk()
        ->assertSee('Nog geen openbaar aanbod', false)
        ->assertDontSee('Bessenstekjes uit Haarlem-centrum', false);
});

test('footer en homepage linken naar stadsoverzicht', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('gratis-planten.index'), false)
        ->assertSee('Gratis planten in jouw stad', false);
});

test('stadspagina toont deelplekken uit JSON en linkt naar /deelplekken', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertSee('Deelplekken in of bij Utrecht', false)
        ->assertSee('Zadenbibliotheek Utrecht', false)
        ->assertDontSee('Het Plantenhuis', false)
        ->assertSee(route('deelplekken', ['plaats' => 'Utrecht']), false)
        ->assertSee(route('deelplekken'), false);

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertDontSee('Plantenasiel Haarlem', false);
});
