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
    expect($data['plekken'])->toHaveCount(63);
    expect($data['meta']['geraadpleegd'])->toBe('2026-10-29');
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
    expect($expected)->toBeArray()->toHaveCount(31);

    $nl = array_slice($plekken, 0, 31);
    expect($nl)->toHaveCount(31);
    expect($nl)->toBe($expected);

    $be = array_slice($plekken, 31);
    expect($be)->toHaveCount(32);
    foreach ($be as $row) {
        expect($row['country'] ?? null)->toBe('BE');
        expect(str_starts_with((string) $row['id'], 'be-'))->toBeTrue();
    }
});

test('deelplekkenpagina laadt met intro, bronlink en meldpad', function () {
    $this->get('/deelplekken')
        ->assertOk()
        ->assertSee('Plantenasiels, plantenbiebs en ruilkastjes in Nederland en België', false)
        ->assertSee('plantenasiel', false)
        ->assertSee('Plantenasiel Haarlem', false)
        ->assertSee('Bron', false)
        ->assertSee('Mis je een plek, of klopt er iets niet? Laat het me weten', false)
        ->assertSee(route('over'), false)
        ->assertSee('id="deelplekken-kaart"', false);
});

test('deelplekken filtert op type en provincie', function () {
    $this->get(route('deelplekken', ['type' => 'zaadbibliotheek', 'provincie' => 'Fryslân']))
        ->assertOk()
        ->assertSee('Zadenbibliotheek Leeuwarden Fonteinland', false)
        ->assertDontSee('Plantenasiel Haarlem', false);

    $this->get(route('deelplekken', ['plaats' => 'Middelburg']))
        ->assertOk()
        ->assertSee('Plantaardig Middelburg', false)
        ->assertDontSee('Plantenasiel Haarlem', false);
});

test('deelplekken API toont bron_url en afgeronde coördinaten', function () {
    $response = $this->getJson(route('api.deelplekken'))
        ->assertOk()
        ->assertJsonPath('items.0.kind', 'deelplek');

    expect($response->json('items'))->toHaveCount(63);

    $haarlem = collect($response->json('items'))->firstWhere('naam', 'Plantenasiel Haarlem');
    expect($haarlem)->not->toBeNull();
    expect($haarlem['bron_url'])->toContain('plantenasielhaarlem.nl');
    expect($haarlem['lat'])->toBe(Geo::roundCoord(52.377503));
    expect($haarlem['lng'])->toBe(Geo::roundCoord(4.664929));

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
        ->assertSee('deelplekken bij jou in de buurt', false)
        ->assertSee('Bekijk deelplekken', false);

    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('api_deelplekken', false)
        ->assertSee('Zaadbibliotheek', false);
});

test('seeder vult deelplekken uit JSON met NL-default en BE-country', function () {
    expect(Deelplek::query()->count())->toBe(63);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->exists())->toBeTrue();

    $haarlem = Deelplek::query()->where('slug', 'haarlem-plantenasiel')->first();
    expect($haarlem)->not->toBeNull();
    expect($haarlem->country)->toBe('NL');
    expect($haarlem->visible)->toBeTrue();

    $be = Deelplek::query()->where('slug', 'be-londerzeel-zadenbib')->first();
    expect($be)->not->toBeNull();
    expect($be->country)->toBe('BE');
    expect($be->type)->toBe('zaadbibliotheek');
    expect($be->visible)->toBeTrue();

    expect(Deelplek::query()->where('country', 'BE')->count())->toBe(32);
    expect(Deelplek::query()->where('country', 'NL')->count())->toBe(31);
});

test('herhaald seeden blijft bij 63 plekken zonder dubbelen', function () {
    $this->seed(DeelplekkenSeeder::class);
    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->count())->toBe(63);
    expect(Deelplek::query()->distinct()->count('slug'))->toBe(63);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->count())->toBe(1);
    expect(Deelplek::query()->where('slug', 'be-londerzeel-zadenbib')->count())->toBe(1);
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
