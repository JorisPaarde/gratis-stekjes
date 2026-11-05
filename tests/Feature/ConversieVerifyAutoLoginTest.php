<?php

declare(strict_types=1);

use App\Models\User;
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
