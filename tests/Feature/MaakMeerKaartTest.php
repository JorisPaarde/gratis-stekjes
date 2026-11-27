<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;

function maakMeerAanbieder(): User
{
    return User::factory()->create([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

test('zoeken op daglelie zonder aanbod toont tip, knop en link naar de stektip', function () {
    $html = $this->get('/aanbod?plant=Daglelie')->assertOk()->getContent();

    expect($html)->toContain('id="plant-aanbod-lijst"')
        ->and($html)->toContain('maak-meer--uitgelicht')
        ->and($html)->toContain('Deze staat er nog niet op')
        ->and($html)->toContain('Zo maak je er meer, en dan kun je die zelf aanbieden.')
        ->and($html)->toContain('Scheuren in september of voorjaar')
        ->and($html)->toContain('Sep of mrt–apr')
        ->and($html)->toContain('Bekijk de tip voor Daglelie')
        ->and($html)->toContain('href="'.route('stektips.show', 'daglelie').'"')
        ->and($html)->toContain('Bied Daglelie aan')
        ->and($html)->toContain('href="'.route('aanbieden', ['plant' => 'Daglelie']).'"')
        ->and($html)->not->toContain('maak-meer--compact')
        ->and($html)->not->toContain('Er is nog geen aanbod met');
});

test('zoeken via Latijnse naam gebruikt de bestaande tip van daglelie', function () {
    $this->get('/aanbod?plant=Hemerocallis')
        ->assertOk()
        ->assertSee('Scheuren in september of voorjaar', false)
        ->assertSee('Bied Daglelie aan', false)
        ->assertSee(route('aanbieden', ['plant' => 'Daglelie']), false);
});

test('zoeken op plant mét aanbod toont de tip kleiner onder de resultaten', function () {
    $user = maakMeerAanbieder();
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Daglelie',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $html = $this->get('/aanbod?plant=Daglelie')->assertOk()->getContent();

    expect($html)->toContain('maak-meer--compact')
        ->and($html)->toContain('Zo maak je zelf meer Daglelie')
        ->and($html)->toContain('Scheuren in september of voorjaar')
        ->and($html)->toContain('Bied Daglelie aan')
        ->and($html)->not->toContain('Deze staat er nog niet op')
        ->and($html)->not->toContain('maak-meer--uitgelicht')
        ->and($html)->toContain('Daglelie'); // het aanbod zelf
});

test('zoeken op plant zonder stektip toont geen verzonnen tip, wel een aanbiedknop', function () {
    $html = $this->get('/aanbod?plant=Yucca')->assertOk()->getContent();

    expect($html)->toContain('Deze staat er nog niet op')
        ->and($html)->toContain('Wil je zelf Yucca aanbieden?')
        ->and($html)->toContain('Bied Yucca aan')
        ->and($html)->toContain('href="'.route('aanbieden', ['plant' => 'Yucca']).'"')
        ->and($html)->not->toContain('Zo maak je er meer')
        ->and($html)->not->toContain('Bekijk de tip voor')
        ->and($html)->not->toContain('href="'.url('/stektips/yucca').'"');
});

test('/aanbieden vult de plant uit de url al in', function () {
    $user = maakMeerAanbieder();

    $this->actingAs($user)
        ->get(route('aanbieden', ['plant' => 'Daglelie']))
        ->assertOk()
        ->assertSee('value="Daglelie"', false)
        ->assertSee('name="plantensoort_id"', false);

    $html = $this->actingAs($user)
        ->get(route('aanbieden', ['plant' => 'Daglelie']))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/name="plantensoort_id"[^>]*value="[1-9][0-9]*"/')
        ->and($html)->toMatch('/name="tekst"[^>]*value="Daglelie"|value="Daglelie"[^>]*name="tekst"/');
});
