<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;

function seedProfielGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:1326')],
        ['lat' => 52.375, 'lon' => 5.22, 'label' => '1326 Almere', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:1326 AB')],
        ['lat' => 52.375, 'lon' => 5.22, 'label' => '1326 Almere', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:Almere')],
        ['lat' => 52.375, 'lon' => 5.22, 'label' => 'Almere', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('be:3370')],
        ['lat' => 50.84, 'lon' => 4.83, 'label' => '3370 Boutersem', 'country' => 'BE', 'cached_at' => now()]
    );
}

test('profiel postcode NL vult woonplaats en label «gebied plaats»', function () {
    Http::fake();
    seedProfielGeo();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie_modus' => 'postcode',
            'locatie' => '1326 AB',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->postcode_gebied)->toBe('1326')
        ->and($user->woonplaats)->toBe('Almere')
        ->and($user->locatie_label)->toBe('1326 Almere');
});

test('profiel postcode normaliseert spaties en hoofdletters', function () {
    Http::fake();
    seedProfielGeo();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie_modus' => 'postcode',
            'locatie' => '1326 ab',
            'telefoon' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->locatie_label)->toBe('1326 Almere');
});

test('profiel BE postcode Boutersem', function () {
    Http::fake();
    seedProfielGeo();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'An',
            'country' => 'BE',
            'locatie_modus' => 'postcode',
            'locatie' => '3370',
            'telefoon' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->locatie_label)->toBe('3370 Boutersem')
        ->and($user->fresh()->country)->toBe('BE');
});

test('profiel fallback plaatsnaam: label alleen plaats', function () {
    Http::fake();
    seedProfielGeo();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie_modus' => 'plaats',
            'locatie' => 'Almere',
            'telefoon' => '',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locatie_label)->toBe('Almere')
        ->and($user->postcode_gebied)->toBeNull()
        ->and($user->woonplaats)->toBe('Almere');
});

test('profiel toont Nederlandse foutmelding bij ongeldige postcode', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.profiel'))
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie_modus' => 'postcode',
            'locatie' => '12',
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasErrors('locatie');

    expect(session('errors')->first('locatie'))->toContain('Nederlandse postcode');
});

test('profielpagina toont postcodeveld en fallback-link', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get(route('account.profiel'))
        ->assertOk()
        ->assertSee('Postcode', false)
        ->assertSee('Weet je je postcode niet?', false)
        ->assertSee('name="locatie_modus"', false);
});

test('beheer gebruikers toont land en postcode · plaats, filter zonder locatie', function () {
    config(['stekjes.admin_dashboard_emails' => ['beheer-admin@example.com']]);
    $admin = User::factory()->create([
        'name' => 'Joris',
        'email' => 'beheer-admin@example.com',
        'email_verified_at' => now(),
    ]);
    $met = User::factory()->create([
        'name' => 'MetLocatie',
        'country' => 'NL',
        'locatie_label' => '1326 Almere',
        'postcode_gebied' => '1326',
        'woonplaats' => 'Almere',
        'lat' => 52.375,
        'lon' => 5.22,
    ]);
    $zonder = User::factory()->create([
        'name' => 'ZonderLocatie',
        'locatie_label' => '',
        'lat' => null,
        'lon' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('beheer.gebruikers'))
        ->assertOk()
        ->assertSee('1326 · Almere', false)
        ->assertSee('>NL<', false)
        ->assertSee('Zonder locatie', false);

    $this->actingAs($admin)
        ->get(route('beheer.gebruikers', ['filter' => 'zonder_locatie']))
        ->assertOk()
        ->assertSee('>ZonderLocatie<', false)
        ->assertDontSee('>MetLocatie<', false);
});
