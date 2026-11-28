<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UnverifiedDeletionNoticeDutch;
use App\Notifications\VerifyEmailReminderDay14Dutch;
use App\Notifications\VerifyEmailReminderDay7Dutch;
use App\Services\ImageProcessor;
use App\Services\UnverifiedAccountDeletionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

function onbevestigdSinds(int $dagen, array $attrs = []): User
{
    return User::factory()->unverified()->create(array_merge([
        'created_at' => now()->subDays($dagen),
        'verification_reminder_sent_at' => $dagen >= 1 ? now()->subDays($dagen)->addDay() : null,
    ], $attrs));
}

function koppelAanbodEnBericht(User $user): void
{
    $ander = User::factory()->create(['email' => 'gesprekspartner-'.uniqid('', true).'@example.test']);
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'ophalen',
        'tekst' => 'Nepaanbod peterselie',
        'visible' => true,
        'expires_at' => now()->addDays(7),
    ]);
    $conv = Conversation::query()->create([
        'user_a_id' => min($user->id, $ander->id),
        'user_b_id' => max($user->id, $ander->id),
        'aanbod_id' => $aanbod->id,
    ]);
    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $user->id,
        'body' => 'Hoi, testbericht.',
        'created_at' => now(),
    ]);
}

beforeEach(function () {
    Config::set('stekjes.unverified_deletion.enabled', true);
    Config::set('stekjes.unverified_deletion.grace_days', 7);
    Config::set('stekjes.unverified_deletion.min_age_days', 20);
    Config::set('stekjes.unverified_deletion.day7_days', 7);
    Config::set('stekjes.unverified_deletion.day14_days', 14);
    Config::set('stekjes.unverified_deletion.test_recipients', []);
});

test('dry-run telt de stappen zonder te versturen en toont geen e-mail', function () {
    Notification::fake();

    $dag7 = onbevestigdSinds(8, ['email' => 'dag7-geheim@example.test']);
    onbevestigdSinds(16, ['email' => 'dag14-geheim@example.test']);
    onbevestigdSinds(21, ['email' => 'dag20-geheim@example.test']);
    $metData = onbevestigdSinds(21, ['email' => 'met-aanbod@example.test']);
    koppelAanbodEnBericht($metData);
    onbevestigdSinds(3, ['email' => 'te-jong@example.test']);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--dry-run' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('eligible_herinnering_dag7=1')
        ->and($output)->toContain('eligible_herinnering_dag14=1')
        ->and($output)->toContain('eligible_verwijdermail=1')
        ->and($output)->toContain('overgeslagen_met_aanbod_of_berichten=1')
        ->and($output)->toContain('dry_run=ja')
        ->and($output)->toContain('sent=0')
        ->and($output)->toContain('purged=0')
        ->and($output)->not->toContain('dag7-geheim@example.test')
        ->and($output)->not->toContain('dag20-geheim@example.test');

    Notification::assertNothingSent();
    expect($dag7->fresh()->verification_reminder_day7_sent_at)->toBeNull();
});

test('zonder --uitvoeren blijft het een dry-run, ook als de vlag aan staat', function () {
    Notification::fake();
    onbevestigdSinds(8);

    expect(Artisan::call('gp:onbevestigd-verwijderen'))->toBe(0);
    expect(Artisan::output())->toContain('dry_run=ja')->toContain('sent_dag7=0');
    Notification::assertNothingSent();
});

test('vlag uit: dry-run telt wel, --uitvoeren stuurt en verwijdert niets', function () {
    Notification::fake();
    Config::set('stekjes.unverified_deletion.enabled', false);
    $dag7 = onbevestigdSinds(8);
    $dag20 = onbevestigdSinds(21);
    $rijp = onbevestigdSinds(30);
    $rijp->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--dry-run' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('enabled=nee')
        ->and($output)->toContain('eligible_herinnering_dag7=1')
        ->and($output)->toContain('eligible_verwijdermail=1')
        ->and($output)->toContain('eligible_verwijdering=1');

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent_dag7=0')->toContain('sent=0')->toContain('purged=0');

    Notification::assertNothingSent();
    expect($dag7->fresh()->verification_reminder_day7_sent_at)->toBeNull()
        ->and($dag20->fresh()->unverified_deletion_notice_sent_at)->toBeNull()
        ->and($rijp->fresh())->not->toBeNull();
});

test('dag 6 krijgt geen herinnering van dag 7, dag 7 wel en maar één keer', function () {
    Notification::fake();
    $jong = onbevestigdSinds(6, ['email' => 'dag6@example.test']);
    $klaar = onbevestigdSinds(8, ['email' => 'dag8@example.test']);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent_dag7=1')->toContain('sent_dag14=0')->toContain('sent=0');

    Notification::assertSentTo($klaar, VerifyEmailReminderDay7Dutch::class);
    Notification::assertNotSentTo($jong, VerifyEmailReminderDay7Dutch::class);
    expect($klaar->fresh()->verification_reminder_day7_sent_at)->not->toBeNull()
        ->and($jong->fresh()->verification_reminder_day7_sent_at)->toBeNull();

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    Notification::assertSentToTimes($klaar, VerifyEmailReminderDay7Dutch::class, 1);
});

test('dag 14 krijgt de herinnering van dag 14, niet die van dag 7 of de verwijdermail', function () {
    Notification::fake();
    $user = onbevestigdSinds(16, ['email' => 'dag16@example.test']);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent_dag14=1')->toContain('sent_dag7=0')->toContain('sent=0');

    Notification::assertSentTo($user, VerifyEmailReminderDay14Dutch::class);
    Notification::assertNotSentTo($user, VerifyEmailReminderDay7Dutch::class);
    Notification::assertNotSentTo($user, UnverifiedDeletionNoticeDutch::class);
    expect($user->fresh()->verification_reminder_day14_sent_at)->not->toBeNull();

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    Notification::assertSentToTimes($user, VerifyEmailReminderDay14Dutch::class, 1);
});

test('dag 20 krijgt de verwijdermail, niet de herinneringen van dag 7 of 14', function () {
    Notification::fake();
    $user = onbevestigdSinds(21, ['email' => 'dag21@example.test']);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent=1')->toContain('sent_dag7=0')->toContain('sent_dag14=0');

    Notification::assertSentTo($user, UnverifiedDeletionNoticeDutch::class);
    Notification::assertNotSentTo($user, VerifyEmailReminderDay7Dutch::class);
    Notification::assertNotSentTo($user, VerifyEmailReminderDay14Dutch::class);
    expect($user->fresh()->unverified_deletion_notice_sent_at)->not->toBeNull();
});

test('accounts met aanbod of berichten worden overgeslagen bij elke stap', function () {
    Notification::fake();
    $this->app->instance(ImageProcessor::class, new ImageProcessor(ImageProcessor::DRIVER_GD));

    $dag7 = onbevestigdSinds(8, ['email' => 'skip-dag7@example.test']);
    koppelAanbodEnBericht($dag7);
    $dag14 = onbevestigdSinds(16, ['email' => 'skip-dag14@example.test']);
    koppelAanbodEnBericht($dag14);
    $dag20 = onbevestigdSinds(21, ['email' => 'skip-dag20@example.test']);
    koppelAanbodEnBericht($dag20);
    $rijp = onbevestigdSinds(30, ['email' => 'skip-rijp@example.test']);
    $rijp->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();
    koppelAanbodEnBericht($rijp);

    $counts = app(UnverifiedAccountDeletionService::class)->counts();
    expect($counts['eligible_herinnering_dag7'])->toBe(0)
        ->and($counts['eligible_herinnering_dag14'])->toBe(0)
        ->and($counts['eligible_verwijdermail'])->toBe(0)
        ->and($counts['eligible_verwijdering'])->toBe(0)
        ->and($counts['overgeslagen_met_aanbod_of_berichten'])->toBe(4);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent_dag7=0')->toContain('sent=0')->toContain('purged=0');
    Notification::assertNothingSent();
    expect($dag7->fresh())->not->toBeNull()
        ->and($dag20->fresh())->not->toBeNull()
        ->and($rijp->fresh())->not->toBeNull();
});

test('na de termijn wordt een account zonder aanbod of berichten verwijderd, een bevestigd account niet', function () {
    Notification::fake();
    $this->app->instance(ImageProcessor::class, new ImageProcessor(ImageProcessor::DRIVER_GD));

    $rijp = onbevestigdSinds(30, ['email' => 'rijp@example.test']);
    $rijp->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();
    $rijpId = (int) $rijp->id;

    $wacht = onbevestigdSinds(30, ['email' => 'wacht@example.test']);
    $wacht->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(2)])->save();

    $tochBevestigd = User::factory()->create([
        'email' => 'toch-ok@example.test',
        'created_at' => now()->subDays(30),
    ]);
    $tochBevestigd->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('purged=1');

    expect(User::query()->whereKey($rijpId)->exists())->toBeFalse()
        ->and($wacht->fresh())->not->toBeNull()
        ->and($tochBevestigd->fresh())->not->toBeNull();
});

test('mailteksten van dag 7, dag 14 en de verwijdermail hebben een bevestigingsknop', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'voorbeeld-mail@example.test',
        'email_verification_token' => str_repeat('a1b2c3d4', 8),
    ]);

    $dag7 = (new VerifyEmailReminderDay7Dutch)->toMail($user);
    expect($dag7->subject)->toBe('Bevestig je aanmelding bij Gratis planten')
        ->and((string) $dag7->render())->toContain('Een week geleden ben je begonnen')
        ->and((string) $dag7->render())->toContain('Bevestig mijn e-mailadres')
        ->and((string) $dag7->render())->not->toContain(' wij ');

    $dag14 = (new VerifyEmailReminderDay14Dutch)->toMail($user);
    expect($dag14->subject)->toBe('Bevestig je aanmelding bij Gratis planten')
        ->and((string) $dag14->render())->toContain('Twee weken geleden ben je begonnen')
        ->and((string) $dag14->render())->toContain('Bevestig mijn e-mailadres');

    $verwijderOp = now()->timezone('Europe/Amsterdam')->addDays(7);
    $mail = (new UnverifiedDeletionNoticeDutch($verwijderOp))->toMail($user);
    $datum = $verwijderOp->locale('nl')->translatedFormat('j F Y');
    $html = (string) $mail->render();
    expect($mail->subject)->toBe('Je account bij Gratis planten wordt op '.$datum.' verwijderd')
        ->and($html)->toContain('Bevestig mijn e-mailadres')
        ->and($html)->toContain($datum)
        ->and($html)->toContain('Als ik vóór '.$datum.' niets van je hoor, verwijder ik je account.');
});

test('bevestigingslink in de herinnering van dag 7 is langer geldig dan 24 uur', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_token' => str_repeat('abcd1234', 8),
    ]);
    $html = (string) (new VerifyEmailReminderDay7Dutch)->toMail($user)->render();
    expect(preg_match('#expires=(\d+)#', $html, $m))->toBe(1);
    $secondsLeft = (int) $m[1] - time();
    expect($secondsLeft)->toBeGreaterThan(6 * 24 * 3600)
        ->and($secondsLeft)->toBeLessThanOrEqual(7 * 24 * 3600 + 120);
});

test('deploy-endpoint draait alleen dry-run en vereist token', function () {
    Config::set('stekjes.deploy_token', 'test-deploy-token');
    Config::set('stekjes.deploy_endpoint_enabled', true);
    Config::set('stekjes.unverified_deletion.enabled', false);

    $this->postJson('/deploy/onbevestigd-verwijderen', [], [
        'Authorization' => 'Bearer test-deploy-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['ok', 'at', 'output']);

    $output = $this->postJson('/deploy/onbevestigd-verwijderen', [], [
        'Authorization' => 'Bearer test-deploy-token',
    ])->json('output');
    expect($output)->toContain('dry_run=ja')
        ->and($output)->toContain('eligible_herinnering_dag7=')
        ->and($output)->not->toMatch('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i');

    $this->postJson('/deploy/onbevestigd-verwijderen')->assertForbidden();
});

test('admin-accounts worden nooit geselecteerd', function () {
    $admin = onbevestigdSinds(21, ['email' => 'admin@example.test']);
    $admin->forceFill(['is_admin' => true])->save();

    $counts = app(UnverifiedAccountDeletionService::class)->counts();
    expect($counts['onbevestigd_actief'])->toBe(0)
        ->and($counts['eligible_verwijdermail'])->toBe(0);
    expect($admin->fresh())->not->toBeNull();
});
