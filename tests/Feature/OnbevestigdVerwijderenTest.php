<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\MailLog;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UnverifiedDeletionNoticeDutch;
use App\Services\ImageProcessor;
use App\Services\UnverifiedAccountDeletionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

function onbevestigdMetHerinneringen(int $herinneringen, array $attrs = []): User
{
    $user = User::factory()->unverified()->create(array_merge([
        'created_at' => now()->subDays(21),
        'verification_reminder_sent_at' => $herinneringen > 0 ? now()->subDays(19) : null,
    ], $attrs));

    // Eerste VerifyEmailDutch = aanmelding, telt niet als herinnering.
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(21),
    ]);

    for ($i = 0; $i < $herinneringen; $i++) {
        $isAuto = $i === 0;
        MailLog::query()->create([
            'user_id' => $user->id,
            'type' => $isAuto ? 'VerifyEmailReminderDutch' : 'VerifyEmailDutch',
            'status' => 'ok',
            'created_at' => now()->subDays(20 - $i),
        ]);
    }

    return $user;
}

beforeEach(function () {
    Config::set('stekjes.unverified_deletion.enabled', true);
    Config::set('stekjes.unverified_deletion.grace_days', 7);
    Config::set('stekjes.unverified_deletion.min_age_days', 20);
    Config::set('stekjes.unverified_deletion.min_reminders', 3);
    Config::set('stekjes.unverified_deletion.test_recipients', []);
});

test('dry-run telt buckets zonder te versturen of verwijderen en toont geen e-mail', function () {
    Notification::fake();

    $metDrie = onbevestigdMetHerinneringen(3, ['email' => 'kandidaat-geheim@example.test']);
    onbevestigdMetHerinneringen(1, ['email' => 'een-herinnering@example.test']);
    User::factory()->unverified()->create([
        'email' => 'te-jong@example.test',
        'created_at' => now()->subDays(3),
    ]);
    User::factory()->create([
        'email' => 'al-bevestigd@example.test',
        'created_at' => now()->subDays(30),
    ]);

    $ander = User::factory()->create(['email' => 'gesprekspartner@example.test']);
    $aanbod = Aanbod::query()->create([
        'user_id' => $metDrie->id,
        'type' => 'ophalen',
        'tekst' => 'Nepaanbod peterselie',
        'visible' => true,
        'expires_at' => now()->addDays(7),
    ]);
    $conv = Conversation::query()->create([
        'user_a_id' => min($metDrie->id, $ander->id),
        'user_b_id' => max($metDrie->id, $ander->id),
        'aanbod_id' => $aanbod->id,
    ]);
    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $metDrie->id,
        'body' => 'Hoi, testbericht.',
        'created_at' => now(),
    ]);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--dry-run' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('eligible_verwijdermail=1')
        ->and($output)->toContain('herinneringen_3plus=1')
        ->and($output)->toContain('herinneringen_1=1')
        ->and($output)->toContain('met_aanbod=1')
        ->and($output)->toContain('met_gesprekken=1')
        ->and($output)->toContain('dry_run=ja')
        ->and($output)->toContain('sent=0')
        ->and($output)->toContain('purged=0')
        ->and($output)->not->toContain('kandidaat-geheim@example.test');

    Notification::assertNothingSent();
    expect(User::query()->whereKey($metDrie->id)->exists())->toBeTrue();
});

test('zonder --uitvoeren blijft het een dry-run, ook als de vlag aan staat', function () {
    Notification::fake();
    onbevestigdMetHerinneringen(3);

    expect(Artisan::call('gp:onbevestigd-verwijderen'))->toBe(0);
    expect(Artisan::output())->toContain('dry_run=ja')->toContain('sent=0');

    Notification::assertNothingSent();
});

test('vlag uit: dry-run telt wel, --uitvoeren stuurt en verwijdert niets', function () {
    Notification::fake();
    Config::set('stekjes.unverified_deletion.enabled', false);
    $user = onbevestigdMetHerinneringen(3);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--dry-run' => true]))->toBe(0);
    expect(Artisan::output())->toContain('enabled=nee')->toContain('eligible_verwijdermail=1');

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent=0')->toContain('purged=0');

    Notification::assertNothingSent();
    expect($user->fresh())->not->toBeNull()
        ->and($user->fresh()->unverified_deletion_notice_sent_at)->toBeNull();
});

test('te jong of te weinig herinneringen krijgen geen verwijdermail', function () {
    Notification::fake();

    $jong = onbevestigdMetHerinneringen(3, [
        'created_at' => now()->subDays(5),
        'email' => 'jong@example.test',
    ]);
    $weinig = onbevestigdMetHerinneringen(2, ['email' => 'twee@example.test']);

    $result = app(UnverifiedAccountDeletionService::class)->run(false);
    expect($result['sent'])->toBe(0);
    Notification::assertNothingSent();
    expect($jong->fresh()->unverified_deletion_notice_sent_at)->toBeNull()
        ->and($weinig->fresh()->unverified_deletion_notice_sent_at)->toBeNull();
});

test('--uitvoeren stuurt één verwijdermail en is idempotent', function () {
    Notification::fake();
    $user = onbevestigdMetHerinneringen(3, ['email' => 'klaar@example.test']);

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent=1')->toContain('purged=0');

    Notification::assertSentTo($user, UnverifiedDeletionNoticeDutch::class);
    expect($user->fresh()->unverified_deletion_notice_sent_at)->not->toBeNull();

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('sent=0');
    Notification::assertSentToTimes($user, UnverifiedDeletionNoticeDutch::class, 1);
});

test('auto-herinnering via kolom telt mee als mail_log die niet meer heeft', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create([
        'created_at' => now()->subDays(25),
        'verification_reminder_sent_at' => now()->subDays(24),
    ]);
    // Aanmelding + twee handmatige herinneringen, geen auto in mail_log.
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(25),
    ]);
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(10),
    ]);
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(5),
    ]);

    $counts = app(UnverifiedAccountDeletionService::class)->counts();
    expect($counts['herinneringen_3plus'])->toBe(1)
        ->and($counts['eligible_verwijdermail'])->toBe(1)
        ->and($counts['met_auto_herinnering'])->toBe(1);
});

test('na de termijn wordt het account met gekoppelde data verwijderd, bevestigde accounts niet', function () {
    Notification::fake();

    $rijp = onbevestigdMetHerinneringen(3, [
        'email' => 'rijp@example.test',
    ]);
    $rijp->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();
    $ander = User::factory()->create(['email' => 'blijft@example.test']);
    $aanbod = Aanbod::query()->create([
        'user_id' => $rijp->id,
        'type' => 'ophalen',
        'tekst' => 'Nepaanbod munt',
        'visible' => true,
        'expires_at' => now()->addDays(7),
    ]);
    $conv = Conversation::query()->create([
        'user_a_id' => min($rijp->id, $ander->id),
        'user_b_id' => max($rijp->id, $ander->id),
        'aanbod_id' => $aanbod->id,
    ]);
    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $rijp->id,
        'body' => 'Hoi, testbericht.',
        'created_at' => now(),
    ]);
    $rijpId = (int) $rijp->id;
    $aanbodId = (int) $aanbod->id;
    $convId = (int) $conv->id;

    $wacht = onbevestigdMetHerinneringen(3, [
        'email' => 'wacht@example.test',
    ]);
    $wacht->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(2)])->save();
    $tochBevestigd = onbevestigdMetHerinneringen(3, [
        'email' => 'toch-ok@example.test',
        'email_verified_at' => now(),
    ]);
    $tochBevestigd->forceFill(['unverified_deletion_notice_sent_at' => now()->subDays(8)])->save();

    $this->app->instance(ImageProcessor::class, new ImageProcessor(ImageProcessor::DRIVER_GD));

    expect(Artisan::call('gp:onbevestigd-verwijderen', ['--uitvoeren' => true]))->toBe(0);
    expect(Artisan::output())->toContain('purged=1');

    expect(User::query()->whereKey($rijpId)->exists())->toBeFalse()
        ->and(Aanbod::query()->whereKey($aanbodId)->exists())->toBeFalse()
        ->and(Conversation::query()->whereKey($convId)->exists())->toBeFalse()
        ->and(Message::query()->where('conversation_id', $convId)->exists())->toBeFalse()
        ->and($wacht->fresh())->not->toBeNull()
        ->and($tochBevestigd->fresh())->not->toBeNull()
        ->and($ander->fresh())->not->toBeNull();
});

test('mailtekst noemt de verwijderdatum en heeft een bevestigingsknop', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'voorbeeld-mail@example.test',
        'email_verification_token' => str_repeat('a1b2c3d4', 8),
    ]);
    $verwijderOp = now()->timezone('Europe/Amsterdam')->addDays(7);
    $mail = (new UnverifiedDeletionNoticeDutch($verwijderOp))->toMail($user);
    $html = (string) $mail->render();
    $datum = $verwijderOp->locale('nl')->translatedFormat('j F Y');

    expect($mail->subject)->toBe('Je account bij Gratis planten wordt op '.$datum.' verwijderd')
        ->and($html)->toContain('Bevestig mijn e-mailadres')
        ->and($html)->toContain($datum)
        ->and($html)->toContain('Als ik vóór '.$datum.' niets van je hoor, verwijder ik je account.')
        ->and($html)->not->toContain(' wij ');
});

test('bevestigingslink in de verwijdermail is langer geldig dan 24 uur', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_token' => str_repeat('abcd1234', 8),
    ]);
    $verwijderOp = now()->addDays(7);
    $mail = (new UnverifiedDeletionNoticeDutch($verwijderOp))->toMail($user);
    $html = (string) $mail->render();
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
        ->and($output)->not->toMatch('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i');

    $this->postJson('/deploy/onbevestigd-verwijderen')->assertForbidden();
});

test('admin-accounts worden nooit geselecteerd', function () {
    $admin = onbevestigdMetHerinneringen(3, [
        'email' => 'admin@example.test',
    ]);
    $admin->forceFill(['is_admin' => true])->save();

    $counts = app(UnverifiedAccountDeletionService::class)->counts();
    expect($counts['onbevestigd_actief'])->toBe(0)
        ->and($counts['eligible_verwijdermail'])->toBe(0);
    expect($admin->fresh())->not->toBeNull();
});
