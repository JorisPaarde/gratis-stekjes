<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Support\Geo;
use App\Support\PostalCode;
use Illuminate\Support\Facades\Http;

function seedGeoGent(): void
{
    foreach (['be:9000', 'be:Gent', 'be:9000 Gent', 'nl,be:9000'] as $key) {
        GeocodeCache::query()->updateOrCreate(
            ['query_key' => Geo::normalizeQuery($key)],
            [
                'lat' => 51.054,
                'lon' => 3.723,
                'label' => str_contains($key, 'Gent') ? 'Gent' : '9000',
                'country' => 'BE',
                'cached_at' => now(),
            ]
        );
    }
}

function seedGeoWallonie(): void
{
    // Cache-hit alsof Nominatim Luik/4000 teruggeeft — resolve moet Wallonië weigeren.
    foreach (['be:4000', 'nl,be:4000 Liège'] as $key) {
        GeocodeCache::query()->updateOrCreate(
            ['query_key' => Geo::normalizeQuery($key)],
            [
                'lat' => 50.633,
                'lon' => 5.567,
                'label' => '4000 Liège',
                'country' => 'BE',
                'cached_at' => now(),
            ]
        );
    }
}

test('profiel accepteert Belgisch adres in Vlaanderen (Gent 9000)', function () {
    Http::fake(); // geen netwerk; cache volstaat
    seedGeoGent();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'An',
            'country' => 'BE',
            'locatie' => '9000',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'));

    $user->refresh();
    expect($user->country)->toBe('BE')
        ->and($user->locatie_label)->toBe('9000')
        ->and($user->lat)->toBe(51.054)
        ->and($user->lon)->toBe(3.723);
});

test('profiel weigert Waalse postcode met duidelijke melding', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.profiel'))
        ->post(route('account.profiel'), [
            'voornaam' => 'An',
            'country' => 'BE',
            'locatie' => '4000',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasErrors(['locatie']);

    expect(session('errors')->first('locatie'))->toBe(PostalCode::OUTSIDE_FLANDERS_MESSAGE);
    expect($user->fresh()->lat)->toBeNull();
});

test('deelplek-aanmelding met Gent 9000 slaagt en zet country BE', function () {
    Http::fake();
    seedGeoGent();
    config([
        'stekjes.form_min_seconds' => 0,
        'stekjes.admin_email' => 'admin@example.org',
        'mail.from.address' => 'admin@example.org',
    ]);

    $this->post(route('voor-organisaties.aanmelden.store'), [
        'naam' => 'Stekjesruil Gent',
        'type' => 'stekjesruilpunt',
        'country' => 'BE',
        'adres' => '',
        'postcode' => '9000',
        'plaats' => 'Gent',
        'url' => '',
        'beschrijving' => 'Gratis stekjes in Gent.',
        'contact_email' => 'gent@example.org',
        'privacy' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ])->assertRedirect(route('voor-organisaties.aanmelden'));

    $this->assertDatabaseHas('deelplekken', [
        'naam' => 'Stekjesruil Gent',
        'country' => 'BE',
        'plaats' => 'Gent',
    ]);
});

test('zoek-API weigert Waalse locatie met foutmelding', function () {
    Http::fake();
    seedGeoWallonie();

    $this->getJson(route('api.zoeken', ['q' => '4000 Liège']))
        ->assertStatus(422)
        ->assertJson([
            'error' => PostalCode::OUTSIDE_FLANDERS_MESSAGE,
        ]);
});

test('aanbod van Belgische gebruiker verschijnt in zoek-API met country BE', function () {
    Http::fake();
    seedGeoGent();

    $user = User::factory()->create([
        'name' => 'An',
        'country' => 'BE',
        'locatie_label' => '9000',
        'lat' => 51.054,
        'lon' => 3.723,
    ]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Lavendelstekjes Gent',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $response = $this->getJson(route('api.zoeken', ['q' => '9000']));
    $response->assertOk();
    $items = $response->json('items');
    expect($items)->not->toBeEmpty()
        ->and($items[0]['country'])->toBe('BE')
        ->and($items[0]['locatie_label'])->toBe('9000');
});
