<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\ConversionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

function welkomVerifyUrl(User $user, string $token): string
{
    return URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);
}

test('eerste bevestiging stuurt door naar welkomstscherm en zet welcome_shown_at', function () {
    $user = User::factory()->unverified()->create(['email' => 'welkom-nieuw@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $this->get(welkomVerifyUrl($user, $token))
        ->assertRedirect(route('account.welkom'));

    $this->assertAuthenticatedAs($user);

    $this->get(route('account.welkom'))
        ->assertOk()
        ->assertSee('Welkom bij Gratis planten', false)
        ->assertSee('Je e-mailadres is bevestigd. Heb je een plant, stekje of zakje zaden over? Zet je eerste aanbod online, dan kunnen buren het vinden.', false)
        ->assertSee('Zet je eerste stekje online', false)
        ->assertSee('Liever eerst kijken? Bekijk het aanbod', false)
        ->assertDontSee('Volgende stap', false);

    expect($user->fresh()->welcome_shown_at)->not->toBeNull();
    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_WELCOME_VIEW)->count())->toBe(1);
});

test('herhaalde bevestiging of al bevestigd toont welkom niet opnieuw', function () {
    $user = User::factory()->unverified()->create(['email' => 'welkom-herhaal@example.com']);
    $token = $user->rotateEmailVerificationToken();
    $url = welkomVerifyUrl($user, $token);

    $this->get($url)->assertRedirect(route('account.welkom'));
    $this->get(route('account.welkom'))->assertOk();
    expect($user->fresh()->welcome_shown_at)->not->toBeNull();

    $this->get(route('account.welkom'))->assertRedirect(route('account.index'));

    // Verbruikte link → inloggen, geen welkom.
    $this->post(route('account.uitloggen'));
    $this->get($url)
        ->assertRedirect(route('account.inloggen'))
        ->assertSessionHas('status');
});

test('uitloggen en opnieuw inloggen toont welkom niet opnieuw', function () {
    $plain = authTestPassword();
    $user = User::factory()->unverified()->create([
        'email' => 'welkom-login@example.com',
        'password' => $plain,
    ]);
    $token = $user->rotateEmailVerificationToken();

    $this->get(welkomVerifyUrl($user, $token))->assertRedirect(route('account.welkom'));
    $this->get(route('account.welkom'))->assertOk();
    $shownAt = $user->fresh()->welcome_shown_at;
    expect($shownAt)->not->toBeNull();

    $this->post(route('account.uitloggen'))->assertRedirect(route('home'));
    $this->assertGuest();

    $this->post(route('account.inloggen'), authFormPayload([
        'email' => 'welkom-login@example.com',
        'password' => $plain,
        'next' => '/account',
    ]))->assertRedirect('/account');

    $this->assertAuthenticatedAs($user);
    $this->get(route('account.index'))->assertOk();
    // Geen tweede redirect naar welkom na login; route weigert herhaalbezoek.
    $this->get(route('account.welkom'))->assertRedirect(route('account.index'));
    expect($user->fresh()->welcome_shown_at?->equalTo($shownAt))->toBeTrue();
});

test('welkom-cta gaat naar aanbieden en meet klik', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'welkom-cta@example.com',
        'name' => 'WelkomCta',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'country' => 'NL',
    ]);
    $token = $user->rotateEmailVerificationToken();

    $this->get(welkomVerifyUrl($user, $token))->assertRedirect(route('account.welkom'));
    $this->get(route('account.welkom'))->assertOk();

    $this->get(route('account.welkom.cta'))
        ->assertRedirect(route('aanbieden'));

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_WELCOME_CTA_CLICK)->count())->toBe(1);
    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_FIRST_OFFER)->count())->toBe(0);
});

test('welkom-link naar openbaar aanbod werkt', function () {
    $user = User::factory()->unverified()->create(['email' => 'welkom-aanbod@example.com']);
    $token = $user->rotateEmailVerificationToken();

    $this->get(welkomVerifyUrl($user, $token))->assertRedirect(route('account.welkom'));
    $html = $this->get(route('account.welkom'))->assertOk()->getContent();

    expect($html)->toContain(route('aanbod'))
        ->and($html)->toContain(route('account.welkom.cta'));
});

test('lid met al aanbod mag welkom bij eerste verify nog zien', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'welkom-al-aanbod@example.com',
        'name' => 'AlAanbod',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Munt',
        'visible' => true,
        'expires_at' => now()->addDays(14),
    ]);
    $token = $user->rotateEmailVerificationToken();

    $this->get(welkomVerifyUrl($user, $token))->assertRedirect(route('account.welkom'));
    $this->get(route('account.welkom'))
        ->assertOk()
        ->assertSee('Welkom bij Gratis planten', false);
});

test('migratie-backfill vult welcome_shown_at voor bestaande geverifieerde users', function () {
    $verified = User::factory()->create([
        'email' => 'bestaand-verified@example.com',
        'email_verified_at' => now()->subDays(10),
        'welcome_shown_at' => null,
    ]);
    $unverified = User::factory()->unverified()->create([
        'email' => 'bestaand-unverified@example.com',
    ]);

    DB::table('users')
        ->whereNotNull('email_verified_at')
        ->whereNull('welcome_shown_at')
        ->update([
            'welcome_shown_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
        ]);

    expect($verified->fresh()->welcome_shown_at)->not->toBeNull()
        ->and($unverified->fresh()->welcome_shown_at)->toBeNull();

    $this->actingAs($verified->fresh())
        ->get(route('account.welkom'))
        ->assertRedirect(route('account.index'));
});

test('avg-export bevat welcome_shown_at', function () {
    $user = User::factory()->create([
        'email' => 'welkom-avg@example.com',
        'email_verified_at' => now(),
        'welcome_shown_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('account.export'))
        ->assertOk();

    $data = json_decode($response->streamedContent(), true);
    expect($data['user'])->toHaveKey('welcome_shown_at')
        ->and($data['user']['welcome_shown_at'])->not->toBeNull();
});

test('welcome events staan niet in FUNNEL_TYPES', function () {
    expect(ConversionEvent::FUNNEL_TYPES)->not->toContain(ConversionEvent::TYPE_WELCOME_VIEW)
        ->and(ConversionEvent::FUNNEL_TYPES)->not->toContain(ConversionEvent::TYPE_WELCOME_CTA_CLICK)
        ->and(ConversionEvent::FUNNEL_TYPES)->toContain(ConversionEvent::TYPE_FIRST_OFFER)
        ->and(ConversionEvent::FUNNEL_TYPES)->toContain(ConversionEvent::TYPE_EMAIL_VERIFIED);
});
