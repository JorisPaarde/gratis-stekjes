<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Services\Deelplekken;
use App\Services\StadAanbod;
use App\Support\Geo;
use Database\Seeders\DeelplekkenSeeder;

beforeEach(function () {
    $this->seed(DeelplekkenSeeder::class);
});

test('deelplekken JSON is geldig en bevat genoeg plekken', function () {
    $service = app(Deelplekken::class);
    expect($service->validateJson())->toBe([]);

    $data = $service->loadJson();
    expect($data['plekken'])->toHaveCount(81);
    expect($data['meta']['geraadpleegd'])->toBe('2026-10-31');
    expect($data['meta']['bronnen'])->not->toBeEmpty();
    expect($data['meta']['niet_opgenomen'])->not->toBeEmpty();
});

test('NL-deelplekkenrijen blijven ongewijzigd (aantal en inhoud)', function () {
    $service = app(Deelplekken::class);
    $plekken = $service->loadJson()['plekken'];

    $expected = json_decode(
        (string) file_get_contents(base_path('tests/fixtures/deelplekken-nl-v0.27.json')),
        true
    );
    expect($expected)->toBeArray()->toHaveCount(44);

    $nl = array_values(array_filter(
        $plekken,
        static fn (array $row): bool => ($row['country'] ?? 'NL') !== 'BE'
    ));
    expect($nl)->toHaveCount(44);
    expect($nl)->toBe($expected);

    $be = array_values(array_filter(
        $plekken,
        static fn (array $row): bool => ($row['country'] ?? null) === 'BE'
    ));
    expect($be)->toHaveCount(37);
    foreach ($be as $row) {
        expect($row['country'] ?? null)->toBe('BE');
        expect(str_starts_with((string) $row['id'], 'be-'))->toBeTrue();
    }
});

test('deelplekkenpagina laadt met intro, bronlink en meldpad', function () {
    $this->get('/deelplekken')
        ->assertOk()
        ->assertSee('Vind een deelplek in de buurt', false)
        ->assertSee('plantenasiel', false)
        ->assertSee('Plantenasiel Zeist — De Boswerf', false)
        ->assertDontSee('Plantenasiel Haarlem', false)
        ->assertSee('Website', false)
        ->assertSee('Gecontroleerd op', false)
        ->assertSee('Mis je een plek, of klopt er iets niet?', false)
        ->assertSee(route('voor-organisaties'), false)
        ->assertSee('id="deelplekken-kaart"', false)
        ->assertSee('id="veelgestelde-vragen"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('Wat is een plantenasiel?', false);
});

test('deelplekken: type-chips met aantallen; bronlink «Website» bij eigen site, anders «Meer informatie»', function () {
    $html = $this->get('/deelplekken')->assertOk()->getContent();

    expect($html)->toMatch('/class="dp-chip[^"]*" href="[^"]*type=zaadbibliotheek">Zadenbibliotheken <span>\d+<\/span>/');
    // plantenasielzeist.nl hoort bij Plantenasiel Zeist → eigen website.
    expect($html)->toMatch('/plantenasielzeist\.nl[^"]*" rel="noopener noreferrer" target="_blank">Website<\/a>/');

    $gefilterd = $this->get('/deelplekken?type=plantenasiel')->assertOk()->getContent();
    expect($gefilterd)->not->toContain('id="veelgestelde-vragen"')
        ->and($gefilterd)->toContain('Toon alles');
});

test('deelplekken filtert op type en provincie', function () {
    $this->get(route('deelplekken', ['type' => 'zaadbibliotheek', 'provincie' => 'Fryslân']))
        ->assertOk()
        ->assertSee('Zadenbibliotheek Dbieb Leeuwarden', false)
        ->assertDontSee('Plantenasiel Zeist', false);

    $this->get(route('deelplekken', ['plaats' => 'Zeist']))
        ->assertOk()
        ->assertSee('Plantenasiel Zeist — De Boswerf', false)
        ->assertDontSee('Zadenbibliotheek Dbieb Leeuwarden', false);
});

test('deelplekken API toont bron_url en afgeronde coördinaten', function () {
    $response = $this->getJson(route('api.deelplekken'))
        ->assertOk()
        ->assertJsonPath('items.0.kind', 'deelplek');

    expect($response->json('items'))->toHaveCount(67);

    $zeist = collect($response->json('items'))->firstWhere('naam', 'Plantenasiel Zeist — De Boswerf');
    expect($zeist)->not->toBeNull();
    expect($zeist['bron_url'])->toContain('plantenasielzeist.nl');
    expect($zeist['lat'])->toBe(Geo::roundCoord(52.084342));
    expect($zeist['lng'])->toBe(Geo::roundCoord(5.261313));

    $be = collect($response->json('items'))->firstWhere('slug', 'be-brussel-muntpunt-zadenbib');
    expect($be)->not->toBeNull();
    expect($be['country'])->toBe('BE');
});

test('deelplekken API filtert en weigert onbekend type', function () {
    $this->getJson(route('api.deelplekken', ['type' => 'plantenbieb']))
        ->assertOk()
        ->assertJsonFragment(['type' => 'plantenbieb']);

    $items = $this->getJson(route('api.deelplekken', ['type' => 'plantenbieb']))->json('items');
    expect(collect($items)->pluck('type')->unique()->all())->toBe(['plantenbieb']);

    $this->getJson(route('api.deelplekken', ['type' => 'onzin']))
        ->assertStatus(400)
        ->assertJsonPath('error', 'Onbekend type.');
});

test('sitemap, nav en homepage linken naar deelplekken', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/deelplekken');

    $this->get('/')
        ->assertOk()
        ->assertSee('Hier kun je nu al terecht', false)
        ->assertSee(route('deelplekken', ['type' => 'zaadbibliotheek']), false)
        ->assertSee(route('deelplekken'), false);

    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('api_deelplekken', false)
        ->assertSee('Zadenbibliotheek', false);
});

test('seeder vult deelplekken uit JSON met NL-default en BE-country', function () {
    expect(Deelplek::query()->count())->toBe(81);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->exists())->toBeTrue();

    $haarlem = Deelplek::query()->where('slug', 'haarlem-plantenasiel')->first();
    expect($haarlem)->not->toBeNull();
    expect($haarlem->country)->toBe('NL');
    // Verborgen na gratis-check (vraagt een bijdrage), niet verwijderd.
    expect($haarlem->visible)->toBeFalse();

    $be = Deelplek::query()->where('slug', 'be-londerzeel-zadenbib')->first();
    expect($be)->not->toBeNull();
    expect($be->country)->toBe('BE');
    expect($be->type)->toBe('zaadbibliotheek');
    expect($be->visible)->toBeTrue();

    $hidden = Deelplek::query()->where('slug', 'delft-mekelpark-ruilkast')->first();
    expect($hidden)->not->toBeNull();
    expect($hidden->visible)->toBeFalse();

    expect(Deelplek::query()->where('country', 'BE')->count())->toBe(37);
    expect(Deelplek::query()->where('country', 'NL')->count())->toBe(44);
    expect(Deelplek::query()->visible()->count())->toBe(67);
});

test('herhaald seeden blijft bij 81 plekken zonder dubbelen', function () {
    $this->seed(DeelplekkenSeeder::class);
    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->count())->toBe(81);
    expect(Deelplek::query()->distinct()->count('slug'))->toBe(81);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->count())->toBe(1);
    expect(Deelplek::query()->where('slug', 'be-londerzeel-zadenbib')->count())->toBe(1);
    expect(Deelplek::query()->where('slug', 'maarssen-plant-voor-plant')->count())->toBe(0);
});

test('verborgen JSON-rij verschijnt niet op stadspagina', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'deelplekken-');
    expect($tmp)->not->toBeFalse();

    $payload = [
        'meta' => ['titel' => 'test'],
        'plekken' => [
            [
                'id' => 'haarlem-zichtbaar',
                'naam' => 'Zichtbare Testplek Haarlem',
                'type' => 'zaadbibliotheek',
                'plaats' => 'Haarlem',
                'provincie' => 'Noord-Holland',
                'adres' => 'Grote Markt 1, Haarlem',
                'lat' => 52.381,
                'lng' => 4.636,
                'bron_url' => 'https://example.com/zichtbaar',
                'geraadpleegd' => '2026-10-31',
                'country' => 'NL',
                'visible' => true,
            ],
            [
                'id' => 'haarlem-verborgen',
                'naam' => 'Verborgen Testplek Haarlem',
                'type' => 'zaadbibliotheek',
                'plaats' => 'Haarlem',
                'provincie' => 'Noord-Holland',
                'adres' => 'Gedempte Oude Gracht 1, Haarlem',
                'lat' => 52.382,
                'lng' => 4.637,
                'bron_url' => 'https://example.com/verborgen',
                'geraadpleegd' => '2026-10-31',
                'country' => 'NL',
                'visible' => false,
            ],
        ],
    ];
    file_put_contents($tmp, json_encode($payload, JSON_THROW_ON_ERROR));

    try {
        $service = new Deelplekken($tmp);
        app()->instance(Deelplekken::class, $service);
        $stadAanbod = app(StadAanbod::class);

        $items = $stadAanbod->deelplekkenWithin([
            'lat' => 52.381,
            'lng' => 4.636,
            'straal_km' => 5,
            'naam' => 'Haarlem',
        ]);

        $namen = collect($items)->pluck('naam')->all();
        expect($namen)->toContain('Zichtbare Testplek Haarlem');
        expect($namen)->not->toContain('Verborgen Testplek Haarlem');
    } finally {
        @unlink($tmp);
    }
});

test('gratis-check: niet-gratis en twijfelplekken verborgen, niet verwijderd', function () {
    $plekken = collect(app(Deelplekken::class)->loadJson()['plekken']);

    $nietGratis = [
        'haarlem-plantenasiel', 'utrecht-plantenhuis', 'leiden-plantenasiel',
        'eindhoven-de-mortel', 'houten-plantenasiel', 'middelburg-plantaardig',
    ];
    $twijfel = [
        'wageningen-plantenasiel', 'groningen-plantenasiel-050', 'medemblik-plantenbieb',
        'voorburg-koningkerk-plantenbieb', 'leidschenveen-plantenbieb', 'leeuwarden-zadenbieb-fonteinland',
        'delft-mekelpark-ruilkast',
    ];

    foreach ([...$nietGratis, ...$twijfel] as $slug) {
        $row = $plekken->firstWhere('id', $slug);
        expect($row)->not->toBeNull();
        expect($row['visible'])->toBeFalse();
        expect($row['verborgen_reden'])->toBe(in_array($slug, $nietGratis, true) ? 'niet-gratis' : 'twijfel');
        expect(Deelplek::query()->where('slug', $slug)->value('visible'))->toBeFalsy();
    }

    $html = $this->get(route('deelplekken'))->assertOk()->getContent();
    $apiNamen = collect($this->getJson(route('api.deelplekken'))->json('items'))->pluck('naam');
    foreach (['Plantenasiel Haarlem', 'Het Plantenhuis', 'Plantenasiel Leiden', 'Plantenbieb Medemblik', 'Zadenbibliotheek Leeuwarden Fonteinland'] as $naam) {
        expect($html)->not->toContain($naam);
        expect($apiNamen->contains(fn ($n) => str_contains((string) $n, $naam)))->toBeFalse();
    }

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertDontSee('Plantenasiel Haarlem', false);
    $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertSee('Zadenbibliotheek Utrecht', false)
        ->assertDontSee('Het Plantenhuis', false);
});

test('gecorrigeerde adressen van Joure en Utrecht', function () {
    $joure = Deelplek::query()->where('slug', 'joure-zadenbieb')->first();
    expect($joure->adres)->toBe('De Merk 31 (Bibliotheek Joure), 8501 AN Joure');
    expect($joure->bron_url)->toBe('https://www.bmf.nl/laat-je-inspireren/zadenbibliotheek.html');
    expect($joure->visible)->toBeTrue();

    $utrecht = Deelplek::query()->where('slug', 'utrecht-zadenbibliotheek')->first();
    expect($utrecht->adres)->toBe('2e Polderweg 5 (tuindersvereniging Ons Genot), 3565 MD Utrecht');
    expect($utrecht->bron_url)->toBe('https://ons-genot.nl/zadenbibliotheek-utrecht/');
    expect($utrecht->visible)->toBeTrue();

    $this->get(route('deelplekken', ['plaats' => 'Utrecht']))
        ->assertOk()
        ->assertSee('3565 MD Utrecht', false)
        ->assertDontSee('3554 GR Utrecht', false);
});
