<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Services\Deelplekken;
use App\Support\Geo;
use Database\Seeders\DeelplekkenSeeder;

beforeEach(function () {
    $this->seed(DeelplekkenSeeder::class);
});

test('deelplekken JSON is geldig en bevat genoeg plekken', function () {
    $service = app(Deelplekken::class);
    expect($service->validateJson())->toBe([]);

    $data = $service->loadJson();
    expect($data['plekken'])->toHaveCount(31);
    expect($data['meta']['geraadpleegd'])->toBe('2026-10-29');
    expect($data['meta']['bronnen'])->not->toBeEmpty();
    expect($data['meta']['niet_opgenomen'])->not->toBeEmpty();
});

test('deelplekkenpagina laadt met intro, bronlink en meldpad', function () {
    $this->get('/deelplekken')
        ->assertOk()
        ->assertSee('Plantenasiels, plantenbiebs en ruilkastjes in Nederland en België', false)
        ->assertSee('plantenasiel', false)
        ->assertSee('Plantenasiel Haarlem', false)
        ->assertSee('Bron', false)
        ->assertSee('Plek missen of fout? Meld het', false)
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

    expect($response->json('items'))->toHaveCount(31);

    $haarlem = collect($response->json('items'))->firstWhere('naam', 'Plantenasiel Haarlem');
    expect($haarlem)->not->toBeNull();
    expect($haarlem['bron_url'])->toContain('plantenasielhaarlem.nl');
    expect($haarlem['lat'])->toBe(Geo::roundCoord(52.377503));
    expect($haarlem['lng'])->toBe(Geo::roundCoord(4.664929));
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

test('seeder vult deelplekken uit JSON', function () {
    expect(Deelplek::query()->count())->toBe(31);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->exists())->toBeTrue();
});

test('herhaald seeden blijft bij 31 plekken zonder dubbelen', function () {
    $this->seed(DeelplekkenSeeder::class);
    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->count())->toBe(31);
    expect(Deelplek::query()->distinct()->count('slug'))->toBe(31);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->count())->toBe(1);
});
