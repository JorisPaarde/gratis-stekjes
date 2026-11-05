<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

test('bevestigen met geldige eenmalige token logt in vanuit verse sessie', function () {
    $user = User::factory()->unverified()->create(['email' => 'vers@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    // Verse sessie: geen actingAs, geen cookies van eerdere login.
    $this->get($url)->assertRedirect(route('account.index'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email_verification_token)->toBeNull();
});

test('hergebruik van bevestigingslink logt niet opnieuw in', function () {
    $user = User::factory()->unverified()->create(['email' => 'eenmaal@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)->assertRedirect(route('account.index'));
    $this->post(route('account.uitloggen'))->assertRedirect(route('home'));
    $this->assertGuest();

    $this->get($url)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHas('status');
    $this->assertGuest();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('verlopen handtekening geeft nette fout zonder login', function () {
    $user = User::factory()->unverified()->create(['email' => 'verlopen@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $url = URL::temporarySignedRoute('account.bevestigen', now()->subMinute(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('parallelle bevestigingsclaims: precies één winnaar', function () {
    $user = User::factory()->unverified()->create(['email' => 'parallel@example.com']);
    $token = $user->rotateEmailVerificationToken();
    $hash = sha1($user->email);

    // Beide workers «zagen» hetzelfde token (race vóór clear), daarna lock + compare-and-clear.
    $attempt = function () use ($user, $token, $hash): string {
        return DB::transaction(function () use ($user, $token, $hash): string {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($hash, sha1($locked->getEmailForVerification()))) {
                return 'invalid';
            }
            $stored = (string) ($locked->email_verification_token ?? '');
            if ($token === '' || $stored === '' || ! hash_equals($stored, $token)) {
                return $locked->hasVerifiedEmail() ? 'already' : 'used';
            }
            if (! $locked->claimEmailVerificationToken($token)) {
                return 'lost';
            }
            if (! $locked->hasVerifiedEmail()) {
                $locked->markEmailAsVerified();
            }

            return 'won';
        });
    };

    $outcomes = [$attempt(), $attempt()];
    expect($outcomes[0])->toBe('won')
        ->and($outcomes[1])->toBe('already')
        ->and(substr_count(implode(',', $outcomes), 'won'))->toBe(1)
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email_verification_token)->toBeNull();

    // HTTP: verbruikte link logt niet in (aparte sessie).
    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => $hash,
        'token' => $token,
    ]);
    $this->withSession([])->get($url)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHas('status');
    $this->assertGuest();
});
