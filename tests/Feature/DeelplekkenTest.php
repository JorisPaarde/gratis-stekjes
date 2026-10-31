<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Services\Deelplekken;
use App\Services\DeelplekkenGratisCheck;
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
    expect($data['meta']['geraadpleegd'])->toBe('2026-10-31');
    expect($data['meta']['bronnen'])->not->toBeEmpty();
    expect($data['meta']['niet_opgenomen'])->not->toBeEmpty();

    $hidden = collect($data['plekken'])->whereStrict('zichtbaar', false);
    expect($hidden)->toHaveCount(14);
    expect($hidden->pluck('verborgen_reden')->unique()->sort()->values()->all())
        ->toBe(['niet-gratis', 'twijfel']);
});

test('deelplekkenpagina laadt met intro, bronlink en meldpad', function () {
    $this->get('/deelplekken')
        ->assertOk()
        ->assertSee('Plantenasiels, plantenbiebs en ruilkastjes in Nederland', false)
        ->assertSee('plantenasiel', false)
        ->assertSee('Plantenasiel Zeist — De Boswerf', false)
        ->assertDontSee('Plantenasiel Haarlem', false)
        ->assertSee('Bron', false)
        ->assertSee('Plek missen of fout? Meld het', false)
        ->assertSee(route('over'), false)
        ->assertSee('id="deelplekken-kaart"', false);
});

test('deelplekken filtert op type en provincie', function () {
    $this->get(route('deelplekken', ['type' => 'zaadbibliotheek', 'provincie' => 'Fryslân']))
        ->assertOk()
        ->assertSee('Zadenbibliotheek Dbieb Leeuwarden', false)
        ->assertDontSee('Zadenbibliotheek Leeuwarden Fonteinland', false)
        ->assertDontSee('Plantenasiel Haarlem', false);

    $this->get(route('deelplekken', ['plaats' => 'Zeist']))
        ->assertOk()
        ->assertSee('Plantenasiel Zeist — De Boswerf', false)
        ->assertDontSee('Plantenasiel Haarlem', false);
});

test('deelplekken API toont bron_url en afgeronde coördinaten', function () {
    $response = $this->getJson(route('api.deelplekken'))
        ->assertOk()
        ->assertJsonPath('items.0.kind', 'deelplek');

    expect($response->json('items'))->toHaveCount(17);

    $zeist = collect($response->json('items'))->firstWhere('naam', 'Plantenasiel Zeist — De Boswerf');
    expect($zeist)->not->toBeNull();
    expect($zeist['bron_url'])->toContain('plantenasielzeist.nl');
    expect($zeist['lat'])->toBe(Geo::roundCoord(52.084342));
    expect($zeist['lng'])->toBe(Geo::roundCoord(5.261313));

    $names = collect($response->json('items'))->pluck('naam');
    expect($names)->not->toContain('Plantenasiel Haarlem');
    expect($names)->not->toContain('Plantaardig Middelburg (plantenasiel)');
});

test('deelplekken API filtert en weigert onbekend type', function () {
    $this->getJson(route('api.deelplekken', ['type' => 'plantenbieb']))
        ->assertOk()
        ->assertJsonFragment(['type' => 'plantenbieb']);

    $items = $this->getJson(route('api.deelplekken', ['type' => 'plantenbieb']))->json('items');
    expect(collect($items)->pluck('type')->unique()->all())->toBe(['plantenbieb']);
    expect(collect($items)->pluck('slug'))->not->toContain('medemblik-plantenbieb');

    $this->getJson(route('api.deelplekken', ['type' => 'onzin']))
        ->assertStatus(400)
        ->assertJsonPath('error', 'Onbekend type.');
});

test('sitemap, nav en homepage linken naar deelplekken', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/deelplekken');
    expect($xml)->not->toContain('haarlem-plantenasiel');
    expect($xml)->not->toContain('Plantenasiel Haarlem');

    $this->get('/')
        ->assertOk()
        ->assertSee('deelplekken bij jou in de buurt', false)
        ->assertSee('Bekijk deelplekken', false);

    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('api_deelplekken', false)
        ->assertSee('Zaadbibliotheek', false);
});

test('verborgen deelplekken staan niet in lijst, kaart-JSON of stadspagina', function () {
    $hiddenNames = [
        'Plantenasiel Haarlem',
        'Het Plantenhuis (Plantenasiel Utrecht)',
        'Plant voor Plant (MEC Maarssen)',
        'Plantenasiel Leiden',
        'Plantenasiel Eindhoven (wijkgebouw De Mortel)',
        'Plantenasiel Houten',
        'Plantaardig Middelburg (plantenasiel)',
        'Plantenasiel Wageningen',
        'Plantenasiel 050 Groningen',
        'Plantenruilkast Mekelpark Delft',
        'Plantenbieb Medemblik',
        'Plantenbieb Koningkerk Voorburg',
        'Plantenbieb Leidschenveen',
        'Zadenbibliotheek Leeuwarden Fonteinland',
    ];

    $list = $this->get(route('deelplekken'))->assertOk()->getContent();
    $api = $this->getJson(route('api.deelplekken'))->assertOk()->json('items');
    $apiNames = collect($api)->pluck('naam')->all();

    foreach ($hiddenNames as $name) {
        expect($list)->not->toContain($name);
        expect($apiNames)->not->toContain($name);
    }

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertDontSee('Plantenasiel Haarlem', false);

    $this->get(route('gratis-planten.show', ['stad' => 'leiden']))
        ->assertOk()
        ->assertDontSee('Plantenasiel Leiden', false);

    $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertSee('Zadenbibliotheek Utrecht', false)
        ->assertDontSee('Het Plantenhuis', false);

    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->where('visible', false)->exists())->toBeTrue();
    expect(Deelplek::query()->visible()->count())->toBe(17);
});

test('gecorrigeerde adressen van joure en utrecht kloppen', function () {
    $joure = Deelplek::query()->where('slug', 'joure-zadenbieb')->first();
    expect($joure)->not->toBeNull();
    expect($joure->adres)->toBe('De Merk 31 (Bibliotheek Joure), 8501 AN Joure');
    expect($joure->bron_url)->toBe('https://www.bmf.nl/laat-je-inspireren/zadenbibliotheek.html');
    expect($joure->visible)->toBeTrue();

    $utrecht = Deelplek::query()->where('slug', 'utrecht-zadenbibliotheek')->first();
    expect($utrecht)->not->toBeNull();
    expect($utrecht->adres)->toBe('2e Polderweg 5 (tuindersvereniging Ons Genot), 3565 MD Utrecht');
    expect($utrecht->bron_url)->toBe('https://ons-genot.nl/zadenbibliotheek-utrecht/');
    expect($utrecht->visible)->toBeTrue();

    $this->get(route('deelplekken', ['plaats' => 'Joure']))
        ->assertOk()
        ->assertSee('8501 AN Joure', false)
        ->assertSee('bmf.nl/laat-je-inspireren/zadenbibliotheek.html', false);

    $this->get(route('deelplekken', ['plaats' => 'Utrecht']))
        ->assertOk()
        ->assertSee('3565 MD Utrecht', false)
        ->assertSee('ons-genot.nl/zadenbibliotheek-utrecht', false)
        ->assertDontSee('3554 GR Utrecht', false);
});

test('gratis-check applicator raakt alleen de 16 doelrijen en verwijdert niets', function () {
    expect(Deelplek::query()->count())->toBe(31);

    Deelplek::query()
        ->whereIn('slug', [
            ...DeelplekkenGratisCheck::HIDE_NIET_GRATIS,
            ...DeelplekkenGratisCheck::HIDE_TWIJFEL,
        ])
        ->update(['visible' => true]);
    Deelplek::query()->where('slug', 'joure-zadenbieb')->update([
        'adres' => 'oud adres',
        'bron_url' => 'https://example.com/oud',
    ]);

    $beforeIds = Deelplek::query()->orderBy('id')->pluck('id')->all();
    $result = app(DeelplekkenGratisCheck::class)->apply();
    // Tweede run blijft veilig (idempotent, geen deletes).
    $again = app(DeelplekkenGratisCheck::class)->apply();

    expect($result['hidden'])->toBe(14);
    expect($result['corrected'])->toBe(2);
    expect($result['missing'])->toBe([]);
    expect($again['missing'])->toBe([]);
    expect(Deelplek::query()->count())->toBe(31);
    expect(Deelplek::query()->orderBy('id')->pluck('id')->all())->toBe($beforeIds);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->value('visible'))->toBeFalsy();
    expect(Deelplek::query()->where('slug', 'joure-zadenbieb')->value('adres'))
        ->toBe('De Merk 31 (Bibliotheek Joure), 8501 AN Joure');
    expect(DeelplekkenGratisCheck::allTargetSlugs())->toHaveCount(16);
});

test('seeder vult deelplekken uit JSON', function () {
    expect(Deelplek::query()->count())->toBe(31);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->exists())->toBeTrue();
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->value('visible'))->toBeFalsy();
    expect(Deelplek::query()->visible()->count())->toBe(17);
});

test('herhaald seeden blijft bij 31 plekken zonder dubbelen', function () {
    $this->seed(DeelplekkenSeeder::class);
    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->count())->toBe(31);
    expect(Deelplek::query()->distinct()->count('slug'))->toBe(31);
    expect(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->count())->toBe(1);
    expect(Deelplek::query()->visible()->count())->toBe(17);
});
