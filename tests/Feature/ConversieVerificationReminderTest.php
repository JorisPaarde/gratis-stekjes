<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\VerifyEmailReminderDutch;
use App\Services\VerificationReminderService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

test('herinnering vlag uit stuurt niets', function () {
    Notification::fake();
    Config::set('stekjes.verification_reminder.enabled', false);
    Config::set('stekjes.verification_reminder.enabled_at', now()->subDays(7)->toIso8601String());

    User::factory()->unverified()->create([
        'email' => 'oud@example.com',
        'created_at' => now()->subDays(2),
    ]);

    $result = app(VerificationReminderService::class)->run();
    expect($result['enabled'])->toBeFalse()
        ->and($result['sent'])->toBe(0);
    Notification::assertNothingSent();
});

test('herinnering stuurt precies één keer aan onbevestigde na enabled_at', function () {
    Notification::fake();
    Config::set('stekjes.verification_reminder.enabled', true);
    Config::set('stekjes.verification_reminder.enabled_at', now()->subDays(5)->toIso8601String());
    Config::set('stekjes.verification_reminder.after_hours', 24);
    Config::set('stekjes.verification_reminder.test_recipients', []);

    $kandidaat = User::factory()->unverified()->create([
        'email' => 'herinner@example.com',
        'created_at' => now()->subHours(30),
        'verification_reminder_sent_at' => null,
    ]);
    $teJong = User::factory()->unverified()->create([
        'email' => 'jong@example.com',
        'created_at' => now()->subHours(2),
    ]);
    $bevestigd = User::factory()->create([
        'email' => 'alok@example.com',
        'created_at' => now()->subDays(2),
    ]);
    $voorVlag = User::factory()->unverified()->create([
        'email' => 'oudervoor@example.com',
        'created_at' => now()->subDays(10),
    ]);

    $result = app(VerificationReminderService::class)->run();
    expect($result['enabled'])->toBeTrue()
        ->and($result['sent'])->toBe(1);

    Notification::assertSentTo($kandidaat, VerifyEmailReminderDutch::class);
    Notification::assertNotSentTo($teJong, VerifyEmailReminderDutch::class);
    Notification::assertNotSentTo($bevestigd, VerifyEmailReminderDutch::class);
    Notification::assertNotSentTo($voorVlag, VerifyEmailReminderDutch::class);

    expect($kandidaat->fresh()->verification_reminder_sent_at)->not->toBeNull();

    // Tweede run: niet opnieuw.
    app(VerificationReminderService::class)->run();
    Notification::assertSentToTimes($kandidaat, VerifyEmailReminderDutch::class, 1);
});

test('herinnering allowlist beperkt ontvangers', function () {
    Notification::fake();
    Config::set('stekjes.verification_reminder.enabled', true);
    Config::set('stekjes.verification_reminder.enabled_at', now()->subDays(5)->toIso8601String());
    Config::set('stekjes.verification_reminder.test_recipients', ['alleen@example.com']);

    $wel = User::factory()->unverified()->create([
        'email' => 'alleen@example.com',
        'created_at' => now()->subDays(2),
    ]);
    $niet = User::factory()->unverified()->create([
        'email' => 'andere@example.com',
        'created_at' => now()->subDays(2),
    ]);

    app(VerificationReminderService::class)->run();
    Notification::assertSentTo($wel, VerifyEmailReminderDutch::class);
    Notification::assertNotSentTo($niet, VerifyEmailReminderDutch::class);
});

test('herinneringsmailtekst is letterlijk goedgekeurd', function () {
    $user = User::factory()->unverified()->create();
    $user->rotateEmailVerificationToken();
    $mail = (new VerifyEmailReminderDutch)->toMail($user);

    expect($mail->subject)->toBe('Bevestig je aanmelding bij '.config('site.name'));
    $rendered = implode("\n", array_map(
        static fn ($line) => is_string($line) ? $line : (string) ($line['line'] ?? ''),
        $mail->introLines
    ));
    expect($rendered)->toContain('Je bent een dag geleden begonnen met je aanmelding')
        ->and($rendered)->toContain('Klik op de knop hieronder om je aanmelding af te maken')
        ->and($mail->actionText)->toBe('Bevestig mijn e-mailadres');
});
