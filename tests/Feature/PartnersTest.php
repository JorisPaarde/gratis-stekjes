<?php

declare(strict_types=1);

use App\Models\PartnerMarker;
use App\Support\Geo;

test('initiatievenpagina laadt op /initiatieven', function () {
    $this->get('/initiatieven')
        ->assertOk()
        ->assertSee('Plantenruilbeurzen, stekjesmarkten en initiatieven', false)
        ->assertSee('Stekjesmarkt, plantenruilbeurs en ruilinitiatieven', false)
        ->assertDontSee('>Partners<', false)
        ->assertSee('Voedselbosbouw.org', false)
        ->assertSee('MeerBomen Nu', false)
        ->assertDontSee('Stekjesroute', false);

    $this->get(route('partners'))
        ->assertOk()
        ->assertSee('Plantenruilbeurzen, stekjesmarkten en initiatieven', false);
});

test('oude /partners URL geeft 301 naar /initiatieven', function () {
    $this->get('/partners')
        ->assertRedirect('/initiatieven')
        ->assertStatus(301);

    $this->get('/partners/voorbeeld')
        ->assertRedirect('/initiatieven/voorbeeld')
        ->assertStatus(301);
});

test('homepage linkt naar Initiatieven en toont geen Partners', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('>Initiatieven</a>', false)
        ->assertSee(route('partners'), false)
        ->assertDontSee('>Partners<', false)
        ->assertDontSee('Alle partners', false);
});

test('sitemap bevat /initiatieven en niet /partners', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('/initiatieven');
    expect($xml)->not->toContain('/partners');
});

test('partners API toont zichtbare markers met afgeronde coördinaten', function () {
    PartnerMarker::query()->create([
        'naam' => 'Demo voedselbos',
        'type' => 'voedselbos',
        'plaats' => 'Utrecht',
        'beschrijving' => 'Open dagen',
        'lat' => 52.123456,
        'lon' => 5.123456,
        'url' => 'https://example.org/bos',
        'bron' => 'intern-geheim',
        'visible' => true,
    ]);
    PartnerMarker::query()->create([
        'naam' => 'Verborgen',
        'type' => 'ruilkast',
        'plaats' => 'Arnhem',
        'beschrijving' => 'Niet tonen',
        'lat' => 51.98,
        'lon' => 5.91,
        'url' => '',
        'bron' => '',
        'visible' => false,
    ]);

    $response = $this->getJson(route('api.partners'))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.naam', 'Demo voedselbos')
        ->assertJsonPath('items.0.type', 'voedselbos')
        ->assertJsonPath('items.0.kind', 'partner_marker')
        ->assertJsonMissing(['bron' => 'intern-geheim']);

    $item = $response->json('items.0');
    expect($item['lat'])->toBe(Geo::roundCoord(52.123456));
    expect($item['lon'])->toBe(Geo::roundCoord(5.123456));
    expect($item)->not->toHaveKey('bron');
});

test('partners API filtert op type en weigert onbekend type', function () {
    PartnerMarker::query()->create([
        'naam' => 'Bieb Oost',
        'type' => 'plantenbieb',
        'plaats' => 'Zwolle',
        'beschrijving' => '',
        'lat' => 52.5,
        'lon' => 6.1,
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);
    PartnerMarker::query()->create([
        'naam' => 'Ruil West',
        'type' => 'ruilkast',
        'plaats' => 'Haarlem',
        'beschrijving' => '',
        'lat' => 52.4,
        'lon' => 4.6,
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);

    $this->getJson(route('api.partners', ['type' => 'plantenbieb']))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.naam', 'Bieb Oost');

    $this->getJson(route('api.partners', ['type' => 'onzin']))
        ->assertStatus(400)
        ->assertJsonPath('error', 'Onbekend type.');
});
