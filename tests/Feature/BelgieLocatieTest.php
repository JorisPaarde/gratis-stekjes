<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;

function seedGeoBelgie(): void
{
    foreach ([
        'be:9000' => ['lat' => 51.054, 'lon' => 3.723, 'label' => '9000'],
        'be:Gent' => ['lat' => 51.054, 'lon' => 3.723, 'label' => 'Gent'],
        'be:9000 Gent' => ['lat' => 51.054, 'lon' => 3.723, 'label' => 'Gent'],
        'nl,be:9000' => ['lat' => 51.054, 'lon' => 3.723, 'label' => '9000'],
        'be:4000' => ['lat' => 50.633, 'lon' => 5.567, 'label' => '4000'],
        'be:Liège' => ['lat' => 50.633, 'lon' => 5.567, 'label' => 'Liège'],
        'be:4000 Liège' => ['lat' => 50.633, 'lon' => 5.567, 'label' => 'Liège'],
        'nl,be:4000' => ['lat' => 50.633, 'lon' => 5.567, 'label' => '4000'],
        'be:5000' => ['lat' => 50.467, 'lon' => 4.867, 'label' => '5000'],
        'be:Namur' => ['lat' => 50.467, 'lon' => 4.867, 'label' => 'Namur'],
        'be:5000 Namur' => ['lat' => 50.467, 'lon' => 4.867, 'label' => 'Namur'],
    ] as $key => $geo) {
        GeocodeCache::query()->updateOrCreate(
            ['query_key' => Geo::normalizeQuery($key)],
            [
                'lat' => $geo['lat'],
                'lon' => $geo['lon'],
                'label' => $geo['label'],
                'country' => 'BE',
                'cached_at' => now(),
            ]
        );
    }
}

test('profiel accepteert Belgisch adres in Vlaanderen (Gent 9000)', function () {
    Http::fake();
    seedGeoBelgie();

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

test('profiel accepteert Waals adres (Liège 4000) met Franse plaatsnaam', function () {
    Http::fake();
    seedGeoBelgie();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Marie',
            'country' => 'BE',
            'locatie' => '4000',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->country)->toBe('BE')
        ->and($user->locatie_label)->toBe('4000')
        ->and($user->lat)->toBe(50.633)
        ->and($user->lon)->toBe(5.567);

    // Plaatsnaam FR zoals geocoder: via plaats-query
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Marie',
            'country' => 'BE',
            'locatie' => 'Liège',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'));

    expect($user->fresh()->locatie_label)->toBe('Liège');
});

test('profiel accepteert Waals adres Namur 5000', function () {
    Http::fake();
    seedGeoBelgie();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Jean',
            'country' => 'BE',
            'locatie' => '5000',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->country)->toBe('BE')
        ->and($user->fresh()->locatie_label)->toBe('5000');
});

test('deelplek-aanmelding met Gent 9000 slaagt en zet country BE', function () {
    Http::fake();
    seedGeoBelgie();
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

test('deelplek-aanmelding met Waals adres Liège 4000 slaagt', function () {
    Http::fake();
    seedGeoBelgie();
    config([
        'stekjes.form_min_seconds' => 0,
        'stekjes.admin_email' => 'admin@example.org',
        'mail.from.address' => 'admin@example.org',
    ]);

    $this->post(route('voor-organisaties.aanmelden.store'), [
        'naam' => 'Échange de boutures Liège',
        'type' => 'stekjesruilpunt',
        'country' => 'BE',
        'adres' => '',
        'postcode' => '4000',
        'plaats' => 'Liège',
        'url' => '',
        'beschrijving' => 'Stekjes in Luik.',
        'contact_email' => 'liege@example.org',
        'privacy' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ])->assertRedirect(route('voor-organisaties.aanmelden'));

    $this->assertDatabaseHas('deelplekken', [
        'country' => 'BE',
        'plaats' => 'Liège',
    ]);
});

test('aanbod van Belgische gebruiker verschijnt in zoek-API met country BE', function () {
    Http::fake();
    seedGeoBelgie();

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
