<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function userWithLocation(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

test('publieke aanbodpagina laadt', function () {
    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('Postcode of plaats', false)
        ->assertSee('id="kaart"', false);
});

test('profiel geocodeert locatie via cache zonder netwerk', function () {
    GeocodeCache::query()->create([
        'query_key' => 'haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie' => 'Haarlem',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'));

    $user->refresh();
    expect($user->locatie_label)->toBe('Haarlem');
    expect($user->lat)->toBe(52.381);
    expect($user->lon)->toBe(4.637);
});

test('aanbod plaatsen vereist locatie en gratis-akkoord', function () {
    $user = User::factory()->create(['lat' => null, 'lon' => null, 'locatie_label' => '']);
    $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertRedirect(route('account.profiel'));

    $user = userWithLocation();
    $this->actingAs($user)
        ->post(route('account.aanbod'), [
            'type' => 'uitdelen',
            'tekst' => 'Zwarte bessenstekken',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'));

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('zoek-API sorteert op afstand en lekt geen e-mail', function () {
    Http::fake(); // geen netwerk
    GeocodeCache::query()->create([
        'query_key' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => '2011',
        'cached_at' => now(),
    ]);

    $near = userWithLocation(['email' => 'near@example.com', 'lat' => 52.382, 'lon' => 4.638]);
    $far = userWithLocation([
        'email' => 'far@example.com',
        'name' => 'Vera',
        'locatie_label' => '9711',
        'lat' => 53.219,
        'lon' => 6.567,
    ]);

    Aanbod::query()->create([
        'user_id' => $far->id,
        'type' => 'uitdelen',
        'tekst' => 'Ver weg: tomaten',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    Aanbod::query()->create([
        'user_id' => $near->id,
        'type' => 'uitdelen',
        'tekst' => 'Dichtbij: bessen',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->getJson(route('api.zoeken', ['q' => '2011', 'type' => 'uitdelen']));
    $response->assertOk();
    $items = $response->json('items');
    expect($items)->toHaveCount(2);
    expect($items[0]['tekst'])->toBe('Dichtbij: bessen');
    expect($items[0])->toHaveKeys(['id', 'type', 'tekst', 'voornaam', 'locatie_label', 'lat', 'lon', 'user_id', 'afstand_km', 'fotos']);
    expect($items[0]['fotos'])->toBeArray();
    expect($response->getContent())->not->toContain('near@example.com');
    expect($response->getContent())->not->toContain('"email"');
});

test('verlopen aanbod verschijnt niet in zoek-API', function () {
    GeocodeCache::query()->create([
        'query_key' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => '2011',
        'cached_at' => now(),
    ]);
    $user = userWithLocation();
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'ophalen',
        'tekst' => 'Verlopen item',
        'visible' => true,
        'expires_at' => now()->subDay(),
    ]);

    $this->getJson(route('api.zoeken', ['q' => '2011']))
        ->assertOk()
        ->assertJsonPath('items', []);
});

test('eigenaar kan aanbod verlengen', function () {
    $user = userWithLocation();
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(2),
    ]);

    $this->actingAs($user)
        ->post(route('account.aanbod.renew', $aanbod))
        ->assertRedirect(route('account.aanbod'));

    expect($aanbod->fresh()->expires_at->greaterThan(now()->addDays(50)))->toBeTrue();
});

test('partners API is leeg zonder markers', function () {
    $this->getJson(route('api.partners'))
        ->assertOk()
        ->assertJsonPath('items', []);
});

test('zoek-API zonder q met recent=1 geeft nieuwste aanbod, begrensd en zonder e-mail', function () {
    $user = userWithLocation(['email' => 'recent@example.com']);
    foreach (range(1, 30) as $i) {
        Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => 'uitdelen',
            'tekst' => 'Recent '.$i,
            'visible' => true,
            'expires_at' => now()->addDays(30),
        ]);
    }
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Verlopen',
        'visible' => true,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->getJson(route('api.zoeken', ['recent' => 1]))->assertOk();
    $items = $response->json('items');

    expect($items)->toHaveCount(24);
    expect($response->json('meta'))->toBeNull();
    expect($items[0]['tekst'])->toBe('Recent 30');
    expect(collect($items)->pluck('tekst'))->not->toContain('Verlopen');
    expect($response->getContent())->not->toContain('recent@example.com');
});

test('zoek-API zonder q en zonder recent blijft 400', function () {
    $this->getJson(route('api.zoeken'))->assertStatus(400);
});

test('aanbodpagina vult ?q= alvast in (zoekveld homepage)', function () {
    $this->get(route('aanbod', ['q' => 'Haarlem']))
        ->assertOk()
        ->assertSee('value="Haarlem"', false);
});

test('zoeken.js: na zoeken één lijst op afstand (aanbod en plekken door elkaar), begrensd', function () {
    $js = (string) file_get_contents(base_path('resources/js/zoeken.js'));

    expect($js)->toContain('function renderList(')
        ->and($js)->toContain('data-meer')
        ->and($js)->toContain('groups.concat(plekken).sort(')
        ->and($js)->toContain('"Dichtstbij " + center.label')
        ->and($js)->toContain('function groupAanbod(')
        ->and($js)->toContain('data-kaart')
        ->and($js)->toContain('function fitRondZoek(')
        ->and($js)->toContain('?recent=1')
        ->and($js)->toContain('get("q")')
        ->and($js)->toContain('if (heeftGezocht)');
});

test('aanbodpagina: compacte zoekbalk zonder uitdelen/ophalen-keuze, filters als chips', function () {
    $html = $this->get(route('aanbod'))->assertOk()->getContent();

    expect($html)->toContain('class="zoekbalk"')
        ->and($html)->not->toContain('id="zoek-type"')
        ->and($html)->not->toContain('Ophalen of zoeken')
        ->and($html)->toContain('class="filter-chip"')
        ->and($html)->not->toContain('<fieldset class="marker-filters"');
});
