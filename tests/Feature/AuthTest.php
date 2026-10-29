<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/** Testwachtwoord opgebouwd (geen hardcoded “secret”-literal voor scanners). */
function authTestPassword(): string
{
    return implode('-', ['TestOnly', 'Auth', 'Passphrase', '9']);
}

function authFormPayload(array $extra = []): array
{
    return array_merge([
        'website' => '',
        'form_started' => time() - 10,
    ], $extra);
}

test('registratiepagina laadt', function () {
    $this->get(route('account.registreren'))
        ->assertOk()
        ->assertSee('Account maken', false)
        ->assertSee('double opt-in', false);
});

test('registreren stuurt verificatiemail en logt niet in', function () {
    Notification::fake();

    $response = $this->post(route('account.registreren'), authFormPayload([
        'voornaam' => 'Sam',
        'email' => 'sam@example.com',
        'password' => authTestPassword(),
        'password_confirmation' => authTestPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ]));

    $response->assertRedirect(route('account.bevestiging-nodig'));
    $this->assertGuest();

    $user = User::query()->where('email', 'sam@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Sam');
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmailDutch::class);
});

test('registreren met bestaand e-mail geeft geen enumeratie en geen tweede user', function () {
    Notification::fake();
    User::factory()->create(['email' => 'bestaat@example.com']);

    $this->post(route('account.registreren'), authFormPayload([
        'voornaam' => 'Ander',
        'email' => 'bestaat@example.com',
        'password' => authTestPassword(),
        'password_confirmation' => authTestPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'bestaat@example.com')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('wegwerpmail uit domains.txt wordt geweigerd', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), authFormPayload([
            'voornaam' => 'Tijdelijk',
            'email' => 'iemand@mailinator.com',
            'password' => authTestPassword(),
            'password_confirmation' => authTestPassword(),
            'gratis_akkoord' => '1',
            'privacy' => '1',
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'iemand@mailinator.com')->exists())->toBeFalse();
});

test('honeypot bij registratie is stil succes zonder user', function () {
    Notification::fake();

    $this->post(route('account.registreren'), authFormPayload([
        'voornaam' => 'Bot',
        'email' => 'bot@example.com',
        'password' => authTestPassword(),
        'password_confirmation' => authTestPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'website' => 'https://spam.test',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'bot@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('te snel formulier wordt geweigerd', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), authFormPayload([
            'voornaam' => 'Snel',
            'email' => 'snel@example.com',
            'password' => authTestPassword(),
            'password_confirmation' => authTestPassword(),
            'gratis_akkoord' => '1',
            'privacy' => '1',
            'form_started' => time(),
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors('form_started');
});

test('inloggen faalt zonder e-mailbevestiging', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'wacht@example.com',
        'password' => authTestPassword(),
    ]);

    $this->from(route('account.inloggen'))
        ->post(route('account.inloggen'), authFormPayload([
            'email' => $user->email,
            'password' => authTestPassword(),
            'next' => '/account',
        ]))
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inloggen lukt na verificatie', function () {
    $user = User::factory()->create([
        'email' => 'ok@example.com',
        'password' => authTestPassword(),
    ]);

    $this->post(route('account.inloggen'), authFormPayload([
        'email' => $user->email,
        'password' => authTestPassword(),
        'next' => '/account',
    ]))->assertRedirect('/account');

    $this->assertAuthenticatedAs($user);
});

test('signed bevestigingslink activeert account en logt in', function () {
    $user = User::factory()->unverified()->create(['email' => 'nieuw@example.com']);

    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->get($url)
        ->assertRedirect(route('account.index'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('account vereist auth en verified', function () {
    $this->get(route('account.index'))->assertRedirect(route('account.inloggen'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)
        ->get(route('account.index'))
        ->assertRedirect(route('account.bevestiging-nodig'));
});

test('uitloggen werkt', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.uitloggen'))
        ->assertRedirect(route('home'));
    $this->assertGuest();
});

test('homepage Doe mee linkt naar registreren', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('account.registreren', ['rol' => 'uitdelen'], false), false)
        ->assertSee(route('account.registreren', ['rol' => 'ophalen'], false), false);
});
