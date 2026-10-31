<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Services\Steden;

test('overzicht gratis planten laadt met landen en provincies', function () {
    $this->get(route('gratis-planten.index'))
        ->assertOk()
        ->assertSee('Gratis planten ophalen in jouw stad', false)
        ->assertSee('Nederland', false)
        ->assertSee('België', false)
        ->assertSee('Noord-Holland', false)
        ->assertSee('Limburg (BE)', false)
        ->assertSee(route('gratis-planten.show', ['stad' => 'amsterdam']), false)
        ->assertSee(route('gratis-planten.show', ['stad' => 'antwerpen']), false);
});

test('elke stad-slug geeft 200 met H1 en canonical', function () {
    $stedenService = app(Steden::class);
    $steden = $stedenService->all();
    expect($steden)->toHaveCount(46);

    foreach ($steden as $stad) {
        $url = route('gratis-planten.show', ['stad' => $stad['slug']]);
        $response = $this->get($url);
        $response->assertOk();
        $response->assertSee($stedenService->pageTitle($stad), false);
        $response->assertSee('<link rel="canonical" href="'.$url.'"', false);
        $response->assertSee($stad['intro'], false);
    }
});

test('onbekende stad-slug geeft 404', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'onbestaande-stad']))
        ->assertNotFound();
});

test('sitemap bevat overzicht en steden met lastmod', function () {
    $xml = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)->toContain(route('gratis-planten.index'));
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'haarlem']));
    expect($xml)->toContain(route('gratis-planten.show', ['stad' => 'rotterdam']));

    foreach (['antwerpen', 'brussel', 'namen', 'lommel'] as $slug) {
        $loc = route('gratis-planten.show', ['stad' => $slug]);
        expect($xml)->toContain('<loc>'.e($loc).'</loc><lastmod>');
        expect($xml)->toMatch(
            '/<loc>'.preg_quote(e($loc), '/').'<\/loc><lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/'
        );
    }
});

test('stadspagina STEKJES_STAD bevat geen &quot; en heeft geldige JSON-waarden', function () {
    $html = $this->get(route('gratis-planten.show', ['stad' => 'antwerpen']))
        ->assertOk()
        ->getContent();

    expect(preg_match('/window\.STEKJES_STAD\s*=\s*(\{.*?\});/s', $html, $m))->toBe(1);
    $script = $m[1];
    expect($script)->not->toContain('&quot;');

    expect(preg_match('/\blat:\s*(-?[\d.]+)/', $script, $lat))->toBe(1);
    expect(json_decode($lat[1], false, 512, JSON_THROW_ON_ERROR))->toBeFloat();

    expect(preg_match('/\blng:\s*(-?[\d.]+)/', $script, $lng))->toBe(1);
    expect(json_decode($lng[1], false, 512, JSON_THROW_ON_ERROR))->toBeFloat();

    expect(preg_match('/\bstraal_km:\s*(-?[\d.]+)/', $script, $straal))->toBe(1);
    expect(json_decode($straal[1], false, 512, JSON_THROW_ON_ERROR))->toBeInt();

    expect(preg_match('/\bnaam:\s*("(?:\\\\.|[^"\\\\])*")/', $script, $naam))->toBe(1);
    expect(json_decode($naam[1], false, 512, JSON_THROW_ON_ERROR))->toBe('Antwerpen');

    expect(preg_match('/\baanbod:\s*(\[.*?\])/s', $script, $aanbod))->toBe(1);
    expect(json_decode($aanbod[1], true, 512, JSON_THROW_ON_ERROR))->toBeArray();

    expect(preg_match('/\bdeelplekken:\s*(\[.*\])/s', $script, $deelplekken))->toBe(1);
    expect(json_decode($deelplekken[1], true, 512, JSON_THROW_ON_ERROR))->toBeArray();
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
        ->assertDontSee('Er is nog geen aanbod', false);

    $this->get(route('gratis-planten.show', ['stad' => 'groningen']))
        ->assertOk()
        ->assertSee('Er is nog geen aanbod', false)
        ->assertDontSee('Bessenstekjes uit Haarlem-centrum', false);
});

test('footer en homepage linken naar stadsoverzicht', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('gratis-planten.index'), false)
        ->assertSee('Gratis planten in jouw stad', false);
});

test('stadspagina toont deelplekken uit JSON en linkt naar /deelplekken', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertSee('Deelplekken in of bij Haarlem', false)
        ->assertSee('Plantenasiel Haarlem', false)
        ->assertSee(route('deelplekken', ['plaats' => 'Haarlem']), false)
        ->assertSee(route('deelplekken'), false);
});

test('BE-stadspagina’s tonen zichtbare zadenbibs en geen verborgen plekken', function () {
    $cases = [
        'antwerpen' => [
            'title' => 'Gratis planten, stekjes en zaden in Antwerpen',
            'see' => [
                'Zadenbibs en deelplekken in of bij Antwerpen',
                'Zadenbib A-bib Permeke',
                'Zadenbib EcoHuis Antwerpen',
                'Zadenbib Erfgoedbibliotheek Hendrik Conscience',
                'zadenbib',
            ],
            'count' => 8,
        ],
        'brussel' => [
            'title' => 'Gratis planten, stekjes en zaden in Brussel',
            'see' => [
                'Zadenbib Muntpunt',
                'Zadenbib Bibliotheek Etterbeek',
                'Zadenbib Bibliotheek Ukkel',
            ],
            'count' => 5,
        ],
        'namen' => [
            'title' => 'Gratis planten, stekjes en zaden in Namen',
            'see' => [
                'Grainothèque Bibliothèque La Célestine',
                'Grainothèque Bibliothèque de Flawinne',
                'Grainothèque Bibliothèque de Malonne',
            ],
            'count' => 3,
        ],
        'lommel' => [
            'title' => 'Gratis planten, stekjes en zaden in Lommel',
            'see' => [
                'Zadenbib Bibliotheek Lommel',
                'Zadenbib Bibliotheek Pelt',
                'Zadenbib Bibliotheek Hamont',
                'Zadenbib Bibliotheek De Priool Bocholt',
                'Wat is een zadenbib?',
            ],
            'count' => 7,
        ],
    ];

    foreach ($cases as $slug => $case) {
        $html = $this->get(route('gratis-planten.show', ['stad' => $slug]))
            ->assertOk()
            ->assertSee($case['title'], false)
            ->assertDontSee('Verborgen Testplek', false)
            ->getContent();

        foreach ($case['see'] as $needle) {
            expect($html)->toContain($needle);
        }

        expect($html)->toMatch('/class="gp-deelplek-lijst">(.*?)<\/ul>/s');
        preg_match('/class="gp-deelplek-lijst">(.*?)<\/ul>/s', $html, $listMatch);
        expect(substr_count($listMatch[1], '<strong>'))->toBe($case['count']);
    }
});

test('NL-stadspagina’s blijven inhoudelijk stabiel (H1 en deelplek-kop)', function () {
    $this->get(route('gratis-planten.show', ['stad' => 'amsterdam']))
        ->assertOk()
        ->assertSee('Gratis planten en stekjes in Amsterdam', false)
        ->assertSee('Deelplekken in of bij Amsterdam', false)
        ->assertDontSee('Zadenbibs en deelplekken in of bij Amsterdam', false);

    $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertSee('Gratis planten en stekjes in Utrecht', false)
        ->assertSee('Plantenasiel in Utrecht', false);
});
