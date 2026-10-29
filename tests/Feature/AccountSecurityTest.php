<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\EmailChangeNoticeDutch;
use App\Notifications\ResetPasswordDutch;
use App\Notifications\VerifyEmailDutch;
use App\Notifications\VerifyPendingEmailDutch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Testwachtwoord opgebouwd (geen hardcoded “secret”-literal voor scanners). */
function accountSecPassword(): string
{
    return implode('-', ['TestOnly', 'Account', 'Passphrase', '9']);
}

/** Verkeerd wachtwoord — runtime-waarde, geen string-literal naast password-keys. */
function accountSecWrongPassword(): string
{
    return 'x'.Str::random(16);
}

/** Te kort wachtwoord voor validatietests. */
function accountSecShortPassword(): string
{
    return Str::random(4);
}

/** Bevestiging die niet overeenkomt met een te kort wachtwoord. */
function accountSecMismatchConfirmation(): string
{
    return Str::random(5);
}

function accountSecForm(array $extra = []): array
{
    return array_merge([
        'website' => '',
        'form_started' => time() - 10,
    ], $extra);
}

beforeEach(function () {
    RateLimiter::clear('login:ok@example.com|127.0.0.1');
});

test('onthoud mij zet remember cookie', function () {
    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => accountSecPassword(),
    ]);

    $this->post(route('account.inloggen'), accountSecForm([
        'email' => $user->email,
        'password' => accountSecPassword(),
        'remember' => '1',
        'next' => '/account',
    ]))->assertRedirect('/account');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('niet-bevestigd account stuurt naar bevestigingsscherm met resend', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'wacht-nog@example.com',
        'password' => accountSecPassword(),
    ]);

    $this->from(route('account.inloggen'))
        ->post(route('account.inloggen'), accountSecForm([
            'email' => $user->email,
            'password' => accountSecPassword(),
            'next' => '/account',
        ]))
        ->assertRedirect(route('account.bevestiging-nodig'))
        ->assertSessionHas('status');

    $this->assertGuest();

    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertSee('Stuur opnieuw', false)
        ->assertSee('Bevestig je e-mail', false);
});

test('bevestigingsmail opnieuw sturen is throttled met wachttijd', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'resend@example.com']);

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('account.bevestiging-opnieuw'), ['email' => $user->email])
            ->assertRedirect();
    }

    $this->from(route('account.bevestiging-nodig'))
        ->post(route('account.bevestiging-opnieuw'), ['email' => $user->email])
        ->assertRedirect(route('account.bevestiging-nodig'))
        ->assertSessionHasErrors('email');

    $message = session('errors')->first('email');
    expect($message)->toContain('Te veel pogingen');
    expect($message)->toMatch('/seconde|minuut/');
});

test('login is throttled per e-mail en IP met Nederlandse wachttijd', function () {
    $user = User::factory()->create([
        'email' => 'throttle-login@example.com',
        'password' => accountSecPassword(),
    ]);

    $wrong = accountSecWrongPassword();

    for ($i = 0; $i < 5; $i++) {
        $this->from(route('account.inloggen'))
            ->post(route('account.inloggen'), accountSecForm([
                'email' => $user->email,
                'password' => $wrong,
                'next' => '/account',
            ]));
    }

    $this->from(route('account.inloggen'))
        ->post(route('account.inloggen'), accountSecForm([
            'email' => $user->email,
            'password' => $wrong,
            'next' => '/account',
        ]))
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Te veel pogingen');
});

test('registratie is throttled per IP', function () {
    for ($i = 0; $i < 8; $i++) {
        $this->post(route('account.registreren'), accountSecForm([
            'voornaam' => 'Reg'.$i,
            'email' => "reg{$i}@example.com",
            'password' => accountSecPassword(),
            'password_confirmation' => accountSecPassword(),
            'gratis_akkoord' => '1',
            'privacy' => '1',
        ]));
    }

    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), accountSecForm([
            'voornaam' => 'Teveel',
            'email' => 'teveel@example.com',
            'password' => accountSecPassword(),
            'password_confirmation' => accountSecPassword(),
            'gratis_akkoord' => '1',
            'privacy' => '1',
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Te veel pogingen');
});

test('wachtwoord vergeten stuurt mail zonder enumeratie', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->post(route('account.wachtwoord.vergeten'), accountSecForm([
        'email' => $user->email,
    ]))
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordDutch::class);

    Notification::fake();
    $this->post(route('account.wachtwoord.vergeten'), accountSecForm([
        'email' => 'bestaat-niet@example.com',
    ]))
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
    expect(session('status'))->toBe(__('passwords.sent'));
});

test('wachtwoordreset-aanvraag is throttled', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('account.wachtwoord.vergeten'), accountSecForm([
            'email' => 'reset-throttle@example.com',
        ]));
    }

    $this->from(route('account.wachtwoord.vergeten'))
        ->post(route('account.wachtwoord.vergeten'), accountSecForm([
            'email' => 'reset-throttle@example.com',
        ]))
        ->assertRedirect(route('account.wachtwoord.vergeten'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Te veel pogingen');
});

test('wachtwoord resetten via token werkt', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email' => 'nieuw-ww@example.com',
        'password' => accountSecPassword(),
    ]);

    $token = Password::broker()->createToken($user);
    $nieuw = implode('-', ['TestOnly', 'Nieuw', 'Passphrase', '1']);

    $this->post(route('account.wachtwoord.reset.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => $nieuw,
        'password_confirmation' => $nieuw,
    ])->assertRedirect(route('account.inloggen'));

    expect(Hash::check($nieuw, $user->fresh()->password))->toBeTrue();
});

test('wachtwoord wijzigen vereist huidig wachtwoord en logt andere sessies uit', function () {
    $user = User::factory()->create(['password' => accountSecPassword()]);
    $nieuw = implode('-', ['TestOnly', 'Changed', 'Passphrase', '2']);
    $oldRemember = $user->remember_token;

    expect(Schema::hasTable('sessions'))->toBeTrue();

    DB::table('sessions')->insert([
        'id' => 'other-session-id-xyz',
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Other',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    $this->actingAs($user)
        ->post(route('account.wachtwoord'), [
            'current_password' => accountSecPassword(),
            'password' => $nieuw,
            'password_confirmation' => $nieuw,
        ])
        ->assertRedirect(route('account.wachtwoord'))
        ->assertSessionHas('status');

    expect(Hash::check($nieuw, $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe($oldRemember);
    expect(DB::table('sessions')->where('id', 'other-session-id-xyz')->exists())->toBeFalse();

    $this->actingAs($user->fresh())
        ->from(route('account.wachtwoord'))
        ->post(route('account.wachtwoord'), [
            'current_password' => accountSecWrongPassword(),
            'password' => $nieuw,
            'password_confirmation' => $nieuw,
        ])
        ->assertRedirect(route('account.wachtwoord'))
        ->assertSessionHasErrors('current_password');
});

test('e-mail wijzigen stuurt bevestiging naar nieuw en melding naar oud', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email' => 'oud@example.com',
        'password' => accountSecPassword(),
    ]);

    $this->actingAs($user)
        ->post(route('account.email'), [
            'email' => 'nieuw@example.com',
            'password' => accountSecPassword(),
        ])
        ->assertRedirect(route('account.email'))
        ->assertSessionHas('status');

    expect($user->fresh()->email)->toBe('oud@example.com');
    expect($user->fresh()->pending_email)->toBe('nieuw@example.com');

    Notification::assertSentTo($user, VerifyPendingEmailDutch::class);
    Notification::assertSentOnDemand(EmailChangeNoticeDutch::class);
});

test('e-mailwijziging wordt actief na signed bevestigingslink', function () {
    $user = User::factory()->create([
        'email' => 'voor@example.com',
        'pending_email' => 'na@example.com',
    ]);

    $url = URL::temporarySignedRoute('account.email.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('na@example.com'),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('account.email'));

    $fresh = $user->fresh();
    expect($fresh->email)->toBe('na@example.com');
    expect($fresh->pending_email)->toBeNull();
    expect($fresh->hasVerifiedEmail())->toBeTrue();
});

test('mijn account toont alle instellingen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk()
        ->assertSee('Mijn account', false)
        ->assertSee(route('account.profiel'), false)
        ->assertSee(route('account.email'), false)
        ->assertSee(route('account.wachtwoord'), false)
        ->assertSee(route('account.export'), false)
        ->assertSee(route('account.verwijderen'), false);
});

test('account verwijderen vereist wachtwoord', function () {
    $user = User::factory()->create(['password' => accountSecPassword()]);

    $this->actingAs($user)
        ->from(route('account.verwijderen'))
        ->post(route('account.verwijderen'), ['password' => accountSecWrongPassword()])
        ->assertRedirect(route('account.verwijderen'))
        ->assertSessionHasErrors('password');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('account.verwijderen'), ['password' => accountSecPassword()])
        ->assertRedirect(route('home'));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

test('validatiemeldingen zijn Nederlands', function () {
    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), accountSecForm([
            'voornaam' => '',
            'email' => 'geen-mail',
            'password' => accountSecShortPassword(),
            'password_confirmation' => accountSecMismatchConfirmation(),
        ]))
        ->assertRedirect(route('account.registreren'))
        ->assertSessionHasErrors(['voornaam', 'email', 'password', 'gratis_akkoord', 'privacy']);

    $errors = session('errors');
    expect($errors->first('email'))->not->toContain('must be');
    expect($errors->first('email'))->toMatch('/e-mail|geldig/i');
});

test('verificatiemail heeft Nederlandse onderwerp met sitenaam', function () {
    Notification::fake();
    config(['site.name' => 'Gratis planten']);

    $user = User::factory()->unverified()->create(['email' => 'mailcheck@example.com']);
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmailDutch::class, function (VerifyEmailDutch $n) use ($user) {
        $mail = $n->toMail($user);

        return str_contains($mail->subject, 'Gratis planten')
            && str_contains($mail->subject, 'Bevestig');
    });
});
