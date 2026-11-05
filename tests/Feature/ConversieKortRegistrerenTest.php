<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function kortRegPassword(): string
{
    return implode('-', ['TestOnly', 'KortReg', 'Pass', '9']);
}

function seedKortRegGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('be:9000')],
        ['lat' => 51.054, 'lon' => 3.723, 'label' => '9000 Gent', 'country' => 'BE', 'cached_at' => now()]
    );
}

function kortRegPayload(array $extra = []): array
{
    return array_merge([
        'website' => '',
        'form_started' => time() - 10,
        'email' => 'kort@example.com',
        'password' => kortRegPassword(),
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ], $extra);
}

test('registratieformulier vraagt alleen e-mail wachtwoord postcode plus vinkjes', function () {
    $html = $this->get(route('account.registreren'))->assertOk()->getContent();
    expect($html)->toContain('name="email"')
        ->and($html)->toContain('name="password"')
        ->and($html)->toContain('name="postcode"')
        ->and($html)->toContain('data-wachtwoord-toggle')
        ->and($html)->toContain('Hoe heb je ons gevonden')
        ->and($html)->toContain('name="acquisition_found_via"')
        ->and($html)->not->toContain('name="password_confirmation"')
        ->and($html)->not->toContain('name="voornaam"')
        ->and($html)->not->toContain('name="struikrover"')
        ->and($html)->not->toContain('name="stekchallenge"');
});

test('registreren met drie velden maakt account met lege naam en postcode', function () {
    Notification::fake();
    Http::fake();
    seedKortRegGeo();

    $this->post(route('account.registreren'), kortRegPayload())
        ->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'kort@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('')
        ->and($user->publiekeNaam())->toBe('Lid')
        ->and($user->hasLocation())->toBeTrue()
        ->and($user->postcode_gebied)->toBe('2011')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->email_verification_token)->not->toBeEmpty();

    Notification::assertSentTo($user, VerifyEmailDutch::class);
    $this->assertGuest();
});

test('registreren met Belgische postcode werkt', function () {
    Notification::fake();
    Http::fake();
    seedKortRegGeo();

    $this->post(route('account.registreren'), kortRegPayload([
        'email' => 'be@example.com',
        'country' => 'BE',
        'postcode' => '9000',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'be@example.com')->first();
    expect($user->country)->toBe('BE')
        ->and($user->locatie_label)->toBe('9000 Gent')
        ->and($user->publiekeNaam())->toBe('Lid');
});

test('lege naam breekt berichtenweergave niet', function () {
    $user = User::factory()->create(['name' => '']);
    expect($user->publiekeNaam())->toBe('Lid');
});

test('aanbieden zonder gekozen naam stuurt naar profiel', function () {
    $user = User::factory()->create([
        'name' => '',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'postcode_gebied' => '2011',
        'woonplaats' => 'Haarlem',
    ]);

    $this->actingAs($user)
        ->get(route('aanbieden'))
        ->assertRedirect(route('account.profiel'));
});
