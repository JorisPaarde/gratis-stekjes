<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Support\SafeMail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function regMailPassword(): string
{
    return implode('-', ['TestOnly', 'RegMail', 'Passphrase', '9']);
}

function regMailForm(array $extra = []): array
{
    return array_merge([
        'website' => '',
        'form_started' => time() - 10,
        'voornaam' => 'Sam',
        'password' => regMailPassword(),
        'password_confirmation' => regMailPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ], $extra);
}

test('SafeMail redigeert gevoelige data in exceptionsamenvatting', function () {
    $summary = SafeMail::safeSummary(new RuntimeException('fail user@example.com at 10.0.0.1 password=geheim'));
    expect($summary)->toContain('[email]')
        ->toContain('[ip]')
        ->not->toContain('user@example.com')
        ->not->toContain('geheim');
});

test('registratie maakt account aan zonder 500 als SMTP na response faalt', function () {
    $email = 'half-'.Str::lower(Str::random(8)).'@example.com';

    // Mail gaat via terminating/afterResponse: SMTP-fout mag geen 500 geven.
    $this->mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
    });

    $this->post(route('account.registreren'), regMailForm([
        'email' => $email,
    ]))
        ->assertRedirect(route('account.bevestiging-nodig'))
        ->assertSessionHas('status');

    expect(User::query()->where('email', $email)->exists())->toBeTrue();
    $this->assertGuest();
});

test('opnieuw registreren met zelfde wachtwoord stuurt opnieuw voor unverified', function () {
    Notification::fake();
    $email = 'retry-'.Str::lower(Str::random(8)).'@example.com';
    $user = User::factory()->unverified()->create([
        'email' => $email,
        'password' => regMailPassword(),
    ]);

    $this->post(route('account.registreren'), regMailForm([
        'email' => $email,
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', $email)->count())->toBe(1);
    Notification::assertSentTo($user, VerifyEmailDutch::class);
});

test('bevestiging opnieuw vangt mailfout zonder 500', function () {
    $email = 'resend-'.Str::lower(Str::random(8)).'@example.com';
    User::factory()->unverified()->create(['email' => $email]);

    $this->mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
    });

    $this->from(route('account.bevestiging-nodig'))
        ->post(route('account.bevestiging-opnieuw'), ['email' => $email])
        ->assertRedirect(route('account.bevestiging-nodig'));
});

test('registratie toont alle validatiefouten tegelijk', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), regMailForm([
            'voornaam' => 'Test',
            'email' => 'ok-'.Str::lower(Str::random(6)).'@example.com',
            'password' => Str::random(4),
            'password_confirmation' => Str::random(5),
            'gratis_akkoord' => null,
            'privacy' => null,
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors(['password', 'gratis_akkoord', 'privacy']);

    $bag = session('errors');
    expect($bag->get('password'))->toHaveCount(2);
    expect(implode(' ', $bag->get('password')))->toContain('niet overeen');
    expect(implode(' ', $bag->get('password')))->toContain('10 tekens');
    expect($bag->first('gratis_akkoord'))->toContain('gratis');
    expect($bag->first('privacy'))->toContain('privacy');

    $html = view('auth.register', [
        'title' => 'Registreren',
        'metaDescription' => 'x',
        'canonical' => route('account.registreren'),
        'rol' => '',
        'formStarted' => time(),
        'errors' => $bag,
    ])->render();
    expect($html)->toContain('De wachtwoorden komen niet overeen')
        ->toContain('Kies een wachtwoord van minstens 10 tekens')
        ->toContain('Je moet akkoord gaan: alleen gratis')
        ->toContain('privacyverklaring');
});

test('foutpagina 404 is Nederlands gestyled', function () {
    $this->get('/bestaat-niet-'.Str::random(8))
        ->assertNotFound()
        ->assertSee('Pagina niet gevonden', false)
        ->assertSee(config('site.name'), false);
});

test('foutpagina 500 view is Nederlands', function () {
    $html = view('errors.500')->render();
    expect($html)->toContain('Er ging iets mis')
        ->toContain(config('site.name'));
});
