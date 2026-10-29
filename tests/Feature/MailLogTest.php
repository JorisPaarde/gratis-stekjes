<?php

declare(strict_types=1);

use App\Models\MailLog;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Support\SafeMail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

function mailLogAdminPass(): string
{
    return implode('-', ['TestOnly', 'Beheer', 'Passphrase', '9']);
}

test('SafeMail schrijft fail-regel zonder e-mailadres bij SMTP-fout', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'smtp-fail-'.Str::lower(Str::random(6)).'@example.com',
    ]);

    $this->mock(Dispatcher::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(
            new RuntimeException('SMTP 535 for user@secret.test password=geheim')
        );
    });

    expect(SafeMail::notify($user, new VerifyEmailDutch))->toBeTrue();

    $row = MailLog::query()->where('user_id', $user->id)->latest('id')->first();
    expect($row)->not->toBeNull()
        ->and($row->type)->toBe('VerifyEmailDutch')
        ->and($row->status)->toBe('fail')
        ->and($row->error)->not->toBeNull()
        ->and($row->error)->toContain('[email]')
        ->and($row->error)->not->toContain('user@secret.test')
        ->and($row->error)->not->toContain($user->email)
        ->and($row->error)->not->toContain('geheim');

    $attrs = $row->getAttributes();
    expect($attrs)->not->toHaveKey('email')
        ->and(json_encode($attrs))->not->toContain('@example.com');
});

test('SafeMail schrijft ok-regel bij geslaagde verzending', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'smtp-ok-'.Str::lower(Str::random(6)).'@example.com',
    ]);

    expect(SafeMail::notify($user, new VerifyEmailDutch))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmailDutch::class);

    $row = MailLog::query()->where('user_id', $user->id)->latest('id')->first();
    expect($row)->not->toBeNull()
        ->and($row->type)->toBe('VerifyEmailDutch')
        ->and($row->status)->toBe('ok')
        ->and($row->error)->toBeNull();
});

test('beheer toont bevestiging en laatste mailstatus en kan resend', function () {
    Notification::fake();
    config(['stekjes.admin_password' => mailLogAdminPass()]);

    $unverified = User::factory()->unverified()->create([
        'name' => 'Onbevestigd',
        'email' => 'onbevestigd-'.Str::lower(Str::random(6)).'@example.com',
    ]);
    MailLog::query()->create([
        'user_id' => $unverified->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'fail',
        'error' => 'RuntimeException: SMTP down @ x.php:1',
        'created_at' => now(),
    ]);

    $verified = User::factory()->create([
        'name' => 'Bevestigd',
        'email' => 'bevestigd-'.Str::lower(Str::random(6)).'@example.com',
    ]);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Gebruikers', false)
        ->assertSee('Onbevestigd', false)
        ->assertSee('Bevestigd', false)
        ->assertSee('nee', false)
        ->assertSee('ja', false)
        ->assertSee('VerifyEmailDutch', false)
        ->assertSee('fout', false)
        ->assertSee('Bevestigingsmail opnieuw sturen', false);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.users.resend-verification'), ['user_id' => $unverified->id])
        ->assertRedirect(route('beheer.index'))
        ->assertSessionHas('status');

    Notification::assertSentTo($unverified, VerifyEmailDutch::class);
    expect(session('status'))->toContain('opnieuw gestuurd');
});

test('beheer resend is niet bereikbaar zonder admin', function () {
    config(['stekjes.admin_password' => mailLogAdminPass()]);

    $user = User::factory()->unverified()->create();

    $this->post(route('beheer.users.resend-verification'), ['user_id' => $user->id])
        ->assertRedirect(route('beheer.login'));

    expect(MailLog::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('bevestigingslink blijft geldig na 23 uur', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'lang-'.Str::lower(Str::random(6)).'@example.com',
    ]);

    $createdAt = now();
    $this->travelTo($createdAt);

    $url = URL::temporarySignedRoute(
        'account.bevestigen',
        now()->addMinutes((int) config('auth.verification.expire')),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]
    );

    $this->travelTo($createdAt->copy()->addHours(23));

    $this->get($url)
        ->assertRedirect(route('account.index'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('bevestigingsmail noemt geldigheid in uren, niet 60 minuten', function () {
    config(['site.name' => 'Gratis planten', 'auth.verification.expire' => 1440]);

    $user = User::factory()->unverified()->create();
    $mail = (new VerifyEmailDutch)->toMail($user);
    $html = (string) $mail->render();

    expect($html)->toContain('24 uur geldig')
        ->and($html)->not->toContain('60 minuten')
        ->and($html)->not->toContain('1 uur geldig');
});

test('cron purge verwijdert mail_log ouder dan 90 dagen', function () {
    config(['stekjes.cron_token' => 'test-cron-token']);

    $user = User::factory()->create();
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'error' => null,
        'created_at' => now()->subDays(120),
    ]);
    MailLog::query()->create([
        'user_id' => $user->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'error' => null,
        'created_at' => now()->subDays(10),
    ]);

    $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->getJson('/cron')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('jobs.purge_mail_log.deleted', 1);

    expect(MailLog::query()->count())->toBe(1);
});
