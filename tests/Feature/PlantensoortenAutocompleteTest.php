<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Services\Plantensoorten;
use Illuminate\Support\Facades\RateLimiter;

function plantsoortUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

test('zoek-endpoint vindt op Nederlandse naam, synoniem en Latijn', function () {
    $byNaam = $this->getJson(route('api.plantensoorten', ['q' => 'Monstera']))
        ->assertOk()
        ->assertJsonStructure(['items' => [['id', 'naam', 'latijn', 'synoniemen', 'url']]])
        ->json('items');
    expect(collect($byNaam)->pluck('naam')->all())->toContain('Monstera');

    $syn = $this->getJson(route('api.plantensoorten', ['q' => 'Gatenplant']))
        ->assertOk()
        ->json('items');
    expect(collect($syn)->pluck('naam')->all())->toContain('Monstera');

    $lat = $this->getJson(route('api.plantensoorten', ['q' => 'Monstera del']))
        ->assertOk()
        ->json('items');
    expect(collect($lat)->pluck('naam')->all())->toContain('Monstera');
});

test('zoek-endpoint is accent- en hoofdletterongevoelig en max 10', function () {
    $upper = $this->getJson(route('api.plantensoorten', ['q' => 'MUNT']))
        ->assertOk()
        ->json('items');
    expect(collect($upper)->pluck('naam')->all())->toContain('Munt');

    $accent = $this->getJson(route('api.plantensoorten', ['q' => 'pelargonium']))
        ->assertOk()
        ->json('items');
    expect($accent)->not->toBeEmpty();

    $many = $this->getJson(route('api.plantensoorten', ['q' => 'a']))
        ->assertOk()
        ->json('items');
    expect(count($many))->toBeLessThanOrEqual(10);
});

test('zoek-endpoint zet url alleen bij bestaande stektips-pagina', function () {
    $items = $this->getJson(route('api.plantensoorten', ['q' => 'Monstera']))
        ->assertOk()
        ->json('items');

    $monstera = collect($items)->firstWhere('naam', 'Monstera');
    expect($monstera)->not->toBeNull()
        ->and($monstera['url'])->toBe(route('stektips.show', ['slug' => 'monstera']));

    $tomaat = $this->getJson(route('api.plantensoorten', ['q' => 'Tomaat']))
        ->assertOk()
        ->json('items');
    $row = collect($tomaat)->firstWhere('naam', 'Tomaat');
    expect($row)->not->toBeNull()
        ->and($row['url'])->toBeNull();
});

test('zoek-endpoint begint-met komt vóór bevat', function () {
    $service = app(Plantensoorten::class);
    $items = $service->search('sal', 10);
    expect($items)->not->toBeEmpty();

    $first = $items[0];
    $norm = $service->normalize('sal');
    $fields = array_merge(
        [$service->normalize($first['naam']), $service->normalize($first['latijn'])],
        array_map(fn ($s) => $service->normalize($s), $first['synoniemen'])
    );
    $isStart = false;
    foreach ($fields as $f) {
        if (str_starts_with($f, $norm)) {
            $isStart = true;
            break;
        }
    }
    expect($isStart)->toBeTrue();
});

test('rate limit plantensoorten-zoek: 60/min per IP', function () {
    $ip = '203.0.113.50';
    RateLimiter::clear('plantensoorten-zoek:'.$ip);

    $this->withServerVariables(['REMOTE_ADDR' => $ip]);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson(route('api.plantensoorten', ['q' => 'm']))
            ->assertOk();
    }

    $this->getJson(route('api.plantensoorten', ['q' => 'm']))
        ->assertStatus(429);
});

test('aanbod met gekozen plantensoort_id koppelt en valideert bestaand id', function () {
    $user = plantsoortUser();
    $monstera = app(Plantensoorten::class)->resolveExact('Monstera');
    expect($monstera)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Monstera',
            'plantensoort_id' => $monstera['id'],
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    $aanbod = Aanbod::query()->where('user_id', $user->id)->first();
    expect($aanbod)->not->toBeNull()
        ->and($aanbod->tekst)->toBe('Monstera')
        ->and($aanbod->plantensoort_id)->toBe($monstera['id']);
});

test('aanbod met ongeldig plantensoort_id wordt afgewezen', function () {
    $user = plantsoortUser();

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Monstera',
            'plantensoort_id' => 999999,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('plantensoort_id')
        ->assertRedirect();

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('vrije invoer zonder match krijgt geen plantensoort_id', function () {
    $user = plantsoortUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Muntstekjes uit mijn tuin',
            'plantensoort_id' => '',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    $aanbod = Aanbod::query()->where('user_id', $user->id)->first();
    expect($aanbod->tekst)->toBe('Muntstekjes uit mijn tuin')
        ->and($aanbod->plantensoort_id)->toBeNull();
});

test('exacte naam of synoniem zonder klik koppelt server-side', function () {
    $user = plantsoortUser();
    $munt = app(Plantensoorten::class)->resolveExact('Munt');
    expect($munt)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Munt',
            'plantensoort_id' => '',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'));

    expect(Aanbod::query()->where('user_id', $user->id)->value('plantensoort_id'))
        ->toBe($munt['id']);

    $user2 = plantsoortUser(['email' => 'syn@example.com']);
    $gaten = app(Plantensoorten::class)->resolveExact('Gatenplant');
    expect($gaten)->not->toBeNull()
        ->and($gaten['naam'])->toBe('Monstera');

    $this->actingAs($user2)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Gatenplant',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'));

    expect(Aanbod::query()->where('user_id', $user2->id)->value('plantensoort_id'))
        ->toBe($gaten['id']);
});

test('invasieve check blokkeert vrije invoer, synoniem en Latijn', function () {
    $user = plantsoortUser();

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Reuzenberenklauw',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Heracleum mantegazzianum',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Fallopia japonica stekjes',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('aanbodpagina toont plantensoort-veld met combobox-markup', function () {
    $user = plantsoortUser();

    $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertOk()
        ->assertSee('data-plantsoort-autocomplete', false)
        ->assertSee('name="tekst"', false)
        ->assertSee('name="plantensoort_id"', false)
        ->assertSee('role="listbox"', false)
        ->assertSee('/api/plantensoorten', false);
});

test('autocomplete-JS blijft binnen CSP (self + connect-src self)', function () {
    $js = file_get_contents(base_path('resources/js/plantsoort-autocomplete.js'));
    expect($js)->not->toBeFalse();
    expect($js)->not->toMatch('/https?:\\/\\/(?!localhost)/');
    expect($js)->not->toContain('unpkg.com');
    expect($js)->toContain('fetch(url');

    $user = plantsoortUser();
    $response = $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("connect-src 'self'")
        ->and($csp)->toContain("script-src 'self' https://unpkg.com")
        ->and($response->getContent())->toContain('data-plantsoort-url="/api/plantensoorten"');
});
