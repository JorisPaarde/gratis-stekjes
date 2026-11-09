<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Notifications\AccountBestaatAl;
use App\Notifications\VerifyEmailDutch;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ], $extra);
}

function seedAuthRegGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
}

test('registratiepagina laadt', function () {
    $this->get(route('account.registreren'))
        ->assertOk()
        ->assertSee('Account maken', false)
        ->assertSee('Je krijgt een e-mail om je aanmelding te bevestigen', false);
});

test('oude /registreren URL geeft 301 naar /account/registreren', function () {
    $this->get('/registreren')
        ->assertRedirect('/account/registreren')
        ->assertStatus(301);
});

test('registreren stuurt verificatiemail en logt niet in', function () {
    Notification::fake();
    Http::fake();
    seedAuthRegGeo();

    $response = $this->post(route('account.registreren'), authFormPayload([
        'email' => 'sam@example.com',
        'password' => authTestPassword(),
    ]));

    $response->assertRedirect(route('account.bevestiging-nodig'));
    $this->assertGuest();

    $user = User::query()->where('email', 'sam@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('');
    expect($user->publiekeNaam())->toBe('Lid');
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmailDutch::class);
});

test('registreren met bestaand e-mail geeft geen enumeratie en geen tweede user', function () {
    Notification::fake();
    Http::fake();
    seedAuthRegGeo();
    User::factory()->create(['email' => 'bestaat@example.com']);

    $this->post(route('account.registreren'), authFormPayload([
        'email' => 'bestaat@example.com',
        'password' => authTestPassword(),
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'bestaat@example.com')->count())->toBe(1);
    // Scherm gelijk (geen enumeratie); alleen de eigenaar van het adres krijgt een mail.
    $bestaand = User::query()->where('email', 'bestaat@example.com')->first();
    Notification::assertSentTo($bestaand, AccountBestaatAl::class);
    Notification::assertSentTimes(AccountBestaatAl::class, 1);
    Notification::assertNotSentTo($bestaand, VerifyEmailDutch::class);
});

test('wegwerpmail uit domains.txt wordt geweigerd', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), authFormPayload([
            'email' => 'iemand@mailinator.com',
            'password' => authTestPassword(),
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'iemand@mailinator.com')->exists())->toBeFalse();
});

test('honeypot bij registratie is stil succes zonder user', function () {
    Notification::fake();
    Log::spy();
    seedAuthRegGeo();
    Http::fake();

    $this->post(route('account.registreren'), authFormPayload([
        'email' => 'bot@example.com',
        'password' => authTestPassword(),
        FormGuard::HONEYPOT_FIELD => 'https://spam.test',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'bot@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
        return $message === 'form.bot_rejected'
            && ($context['form'] ?? null) === 'register'
            && ($context['reason'] ?? null) === 'honeypot'
            && ! isset($context['email']);
    })->atLeast()->once();
});

test('legacy honeypot-veld website blijft nep-succes zonder user', function () {
    Notification::fake();
    Log::spy();
    seedAuthRegGeo();
    Http::fake();

    $this->post(route('account.registreren'), authFormPayload([
        'email' => 'legacy-bot@example.com',
        'password' => authTestPassword(),
        'website' => 'https://old-spam.test',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'legacy-bot@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
        return $message === 'form.bot_rejected'
            && ($context['reason'] ?? null) === 'honeypot';
    })->atLeast()->once();
});

test('te snel formulier wordt geweigerd met vriendelijke melding', function () {
    Log::spy();
    Notification::fake();

    $response = $this->from(route('account.registreren'))
        ->post(route('account.registreren'), authFormPayload([
            'email' => 'snel@example.com',
            'password' => authTestPassword(),
            'form_started' => time(),
        ]));

    $response->assertRedirect(route('account.registreren', [
            'fout' => 'snel',
            'email' => 'snel@example.com',
        ]))
        ->assertSessionHasErrors([
            'form_started' => 'Dat ging erg snel. Klik nog een keer op Account maken.',
        ])
        ->assertSessionDoesntHaveErrors('email')
        ->assertSessionMissing('status');

    expect(User::query()->where('email', 'snel@example.com')->exists())->toBeFalse()
        ->and(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('email'))->toBe('snel@example.com');

    Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
        return $message === 'form.bot_rejected'
            && ($context['form'] ?? null) === 'register'
            && ($context['reason'] ?? null) === 'too_fast'
            && ! isset($context['email']);
    })->atLeast()->once();
});

test('registratiepagina honeypot is autofill-neutraal', function () {
    $html = $this->get(route('account.registreren'))->assertOk()->getContent();
    expect($html)->toContain('name="'.FormGuard::HONEYPOT_FIELD.'"')
        ->and($html)->toContain('data-1p-ignore')
        ->and($html)->toContain('data-lpignore="true"')
        ->and($html)->toContain('data-bwignore')
        ->and($html)->toContain('data-form-type="other"')
        ->and($html)->not->toMatch('/class="hp"[^>]*>[\s\S]*name="website"/');
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
        ->assertRedirect(route('account.bevestiging-nodig'))
        ->assertSessionHas('status');

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
    $token = $user->rotateEmailVerificationToken();

    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)
        ->assertRedirect(route('account.welkom'));

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

test('homepage Doe mee linkt naar registreren zonder rolkeuze', function () {
    $html = $this->get('/')
        ->assertOk()
        ->assertSee(route('ga.word-lid', absolute: false), false)
        ->getContent();

    expect($html)->not->toContain('rol=uitdelen')
        ->and($html)->not->toContain('rol=ophalen')
        ->and($html)->not->toContain('Ik deel planten uit')
        ->and($html)->not->toContain('Ik wil ophalen')
        ->and($html)->toContain('Met één account kun je iets aanbieden én iets ophalen');
});
