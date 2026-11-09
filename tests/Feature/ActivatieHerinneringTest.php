<?php

declare(strict_types=1);

use App\Mail\ActivatieHerinneringMail;
use App\Models\Aanbod;
use App\Models\MailLog;
use App\Models\User;
use App\Services\ActivatieHerinneringService;
use App\Support\ActivatieHerinneringStats;
use App\Support\SafeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function activatieConfigAan(?string $enabledAt = null): void
{
    Config::set('stekjes.activation_reminder.enabled', true);
    Config::set(
        'stekjes.activation_reminder.enabled_at',
        $enabledAt ?? now()->subDays(10)->toIso8601String()
    );
    Config::set('stekjes.activation_reminder.after_hours', 48);
    Config::set('stekjes.activation_reminder.test_recipients', []);
}

test('activatieherinnering vlag uit stuurt niets', function () {
    Mail::fake();
    Config::set('stekjes.activation_reminder.enabled', false);
    Config::set('stekjes.activation_reminder.enabled_at', now()->subDays(7)->toIso8601String());

    User::factory()->create([
        'email' => 'oud-activatie@example.com',
        'email_verified_at' => now()->subDays(3),
        'activatie_herinnering_sent_at' => null,
    ]);

    $result = app(ActivatieHerinneringService::class)->run();
    expect($result['enabled'])->toBeFalse()
        ->and($result['sent'])->toBe(0)
        ->and($result['dry_run'])->toBeFalse();
    Mail::assertNothingSent();
});

test('activatieherinnering stuurt precies één keer aan bevestigde zonder aanbod na enabled_at', function () {
    Mail::fake();
    activatieConfigAan();

    $kandidaat = User::factory()->create([
        'name' => 'Sam',
        'email' => 'activatie-ok@example.com',
        'email_verified_at' => now()->subHours(50),
        'activatie_herinnering_sent_at' => null,
        'activatieherinnering' => true,
    ]);
    $teJong = User::factory()->create([
        'email' => 'activatie-jong@example.com',
        'email_verified_at' => now()->subHours(10),
    ]);
    $onbevestigd = User::factory()->unverified()->create([
        'email' => 'activatie-onbev@example.com',
        'created_at' => now()->subDays(3),
    ]);
    $voorVlag = User::factory()->create([
        'email' => 'activatie-oud@example.com',
        'email_verified_at' => now()->subDays(20),
    ]);
    $metAanbod = User::factory()->create([
        'email' => 'activatie-aanbod@example.com',
        'email_verified_at' => now()->subHours(50),
    ]);
    Aanbod::factory()->create(['user_id' => $metAanbod->id]);

    $result = app(ActivatieHerinneringService::class)->run();
    expect($result['enabled'])->toBeTrue()
        ->and($result['sent'])->toBe(1)
        ->and($result['eligible'])->toBe(1);

    Mail::assertSent(ActivatieHerinneringMail::class, function (ActivatieHerinneringMail $mail) use ($kandidaat) {
        return $mail->hasTo($kandidaat->email)
            && $mail->voornaam === 'Sam'
            && str_contains($mail->envelope()->subject, 'Zet je eerste stekje online');
    });
    Mail::assertNotSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($teJong->email));
    Mail::assertNotSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($onbevestigd->email));
    Mail::assertNotSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($voorVlag->email));
    Mail::assertNotSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($metAanbod->email));

    expect($kandidaat->fresh()->activatie_herinnering_sent_at)->not->toBeNull();

    app(ActivatieHerinneringService::class)->run();
    Mail::assertSent(ActivatieHerinneringMail::class, 1);
});

test('activatieherinnering stopt als er ondertussen aanbod is (claim faalt)', function () {
    Mail::fake();
    activatieConfigAan();

    $user = User::factory()->create([
        'email' => 'activatie-race-aanbod@example.com',
        'email_verified_at' => now()->subHours(50),
        'activatie_herinnering_sent_at' => null,
    ]);

    $service = app(ActivatieHerinneringService::class);
    expect($service->eligibleQuery(
        \Carbon\CarbonImmutable::parse((string) config('stekjes.activation_reminder.enabled_at')),
        []
    )->whereKey($user->id)->exists())->toBeTrue();

    Aanbod::factory()->create(['user_id' => $user->id]);

    expect($service->claimReminderSlot((int) $user->id))->toBeFalse();
    $result = $service->run();
    expect($result['sent'])->toBe(0);
    Mail::assertNothingSent();
    expect($user->fresh()->activatie_herinnering_sent_at)->toBeNull();
});

test('activatieherinnering allowlist beperkt ontvangers', function () {
    Mail::fake();
    activatieConfigAan();
    Config::set('stekjes.activation_reminder.test_recipients', ['alleen-act@example.com']);

    $wel = User::factory()->create([
        'email' => 'alleen-act@example.com',
        'email_verified_at' => now()->subDays(3),
    ]);
    $niet = User::factory()->create([
        'email' => 'andere-act@example.com',
        'email_verified_at' => now()->subDays(3),
    ]);

    app(ActivatieHerinneringService::class)->run();
    Mail::assertSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($wel->email));
    Mail::assertNotSent(ActivatieHerinneringMail::class, fn (ActivatieHerinneringMail $m) => $m->hasTo($niet->email));
});

test('staging met lege allowlist stuurt 0 activatie-mails (fail-closed)', function () {
    Mail::fake();
    $previousEnv = app()->environment();
    app()['env'] = 'staging';

    activatieConfigAan();
    Config::set('stekjes.activation_reminder.test_recipients', []);

    User::factory()->create([
        'email' => 'staging-activatie@example.com',
        'email_verified_at' => now()->subHours(50),
    ]);

    try {
        $result = app(ActivatieHerinneringService::class)->run();
        expect($result['enabled'])->toBeTrue()
            ->and($result['sent'])->toBe(0)
            ->and($result['eligible'])->toBe(0);
        Mail::assertNothingSent();
    } finally {
        app()['env'] = $previousEnv;
    }
});

test('activatieherinnering dry-run telt zonder te versturen', function () {
    Mail::fake();
    activatieConfigAan();

    User::factory()->create([
        'email' => 'dry-act@example.com',
        'email_verified_at' => now()->subHours(50),
    ]);

    $result = app(ActivatieHerinneringService::class)->run(50, true);
    expect($result['dry_run'])->toBeTrue()
        ->and($result['eligible'])->toBe(1)
        ->and($result['sent'])->toBe(0);
    Mail::assertNothingSent();

    $this->artisan('gp:activatie-herinnering', ['--dry-run' => true])
        ->expectsOutputToContain('eligible=1')
        ->assertSuccessful();
    Mail::assertNothingSent();
});

test('overlappende cronruns claimen atomair max één activatieherinnering', function () {
    Mail::fake();
    activatieConfigAan();

    $user = User::factory()->create([
        'email' => 'race-activatie@example.com',
        'email_verified_at' => now()->subHours(50),
        'activatie_herinnering_sent_at' => null,
    ]);

    $service = app(ActivatieHerinneringService::class);
    $claimA = $service->claimReminderSlot((int) $user->id);
    $claimB = $service->claimReminderSlot((int) $user->id);
    expect($claimA)->toBeTrue()->and($claimB)->toBeFalse();

    if ($claimA) {
        SafeMail::mailable((string) $user->email, $service->buildMail($user->fresh()), (int) $user->id);
    }

    Mail::assertSent(ActivatieHerinneringMail::class, 1);
    expect($user->fresh()->activatie_herinnering_sent_at)->not->toBeNull();

    $result = $service->run();
    expect($result['sent'])->toBe(0);
    Mail::assertSent(ActivatieHerinneringMail::class, 1);
});

test('afmelden via tipmail-link zet activatieherinnering uit', function () {
    Mail::fake();
    activatieConfigAan();

    $user = User::factory()->create([
        'email' => 'optout-act@example.com',
        'email_verified_at' => now()->subHours(50),
        'activatieherinnering' => true,
    ]);

    $url = URL::signedRoute('activatieherinnering.uit', ['user' => $user->id]);

    $this->get($url)->assertOk()->assertSee('Zet de tipmail uit', false);
    expect($user->fresh()->activatieherinnering)->toBeTrue();

    $this->post($url)->assertOk()->assertSee('geen tipmail meer', false);
    expect($user->fresh()->activatieherinnering)->toBeFalse();

    $result = app(ActivatieHerinneringService::class)->run();
    expect($result['sent'])->toBe(0);
    Mail::assertNothingSent();

    $this->get(route('activatieherinnering.uit', ['user' => $user->id]))->assertForbidden();
});

test('activatieherinneringmailtekst is warm en zonder deelplek-aantallen', function () {
    $user = User::factory()->create(['name' => 'Sam']);
    $mail = app(ActivatieHerinneringService::class)->buildMail($user);
    $rendered = $mail->render();

    expect($mail->envelope()->subject)->toBe('Zet je eerste stekje online op '.config('site.name'))
        ->and($rendered)->toContain('Hoi Sam,')
        ->and($rendered)->toContain('leuk dat je erbij bent')
        ->and($rendered)->toContain('Geen haast')
        ->and($rendered)->toContain(route('aanbieden'))
        ->and($rendered)->not->toMatch('/\d+\s*deelplek/i')
        ->and($rendered)->not->toContain('Resend');
});

test('ActivatieHerinneringStats telt mail_log voor beheer', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-09 15:00:00', 'Europe/Amsterdam'));

    $lid = User::factory()->create(['name' => 'Bevestigd Bea']);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'ActivatieHerinneringMail',
        'status' => 'ok',
        'created_at' => now()->subDay(),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(4),
    ]);

    $stats = ActivatieHerinneringStats::voorUser((int) $lid->id);
    expect($stats->ok)->toBe(1)
        ->and($stats->mislukt)->toBe(0)
        ->and($stats->tellingLabel())->toBe('Activatieherinnering: 1×')
        ->and($stats->herinnerdLabel())->toContain('1×');

    config(['stekjes.admin_dashboard_emails' => ['beheer-act@example.com']]);
    $admin = User::factory()->create([
        'name' => 'Joris',
        'email' => 'beheer-act@example.com',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('beheer.gebruikers.show', $lid))
        ->assertOk()
        ->assertSee('Activatieherinnering', false)
        ->assertSee('1×', false);
});

test('gegevensexport bevat activatieherinnering-instelling', function () {
    $user = User::factory()->create([
        'email' => 'export-act@example.com',
        'activatieherinnering' => true,
    ]);
    $response = $this->actingAs($user)->get(route('account.export'))->assertOk();
    $data = json_decode($response->streamedContent(), true);
    expect($data['user']['activatieherinnering'])->toBeTrue();
});
