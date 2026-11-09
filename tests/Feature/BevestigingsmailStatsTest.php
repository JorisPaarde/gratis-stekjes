<?php

declare(strict_types=1);

use App\Models\MailLog;
use App\Models\User;
use App\Support\BevestigingsmailStats;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function beheerAdminVoorBevestiging(): User
{
    config(['stekjes.admin_dashboard_emails' => ['beheer-admin@example.com']]);

    return User::factory()->create([
        'name' => 'Joris',
        'email' => 'beheer-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

test('BevestigingsmailStats: eerste VerifyEmailDutch is aanmelding, rest + auto = herinneringen', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-09 14:00:00', 'Europe/Amsterdam'));

    $lid = User::factory()->unverified()->create(['name' => 'Onbevestigd Otto']);
    $ander = User::factory()->unverified()->create(['name' => 'Ander']);

    // 1 aanmelding + 1 handmatige Herstuur (VerifyEmailDutch) + 1 auto-herinnering
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(3),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailReminderDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(2),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDay(),
    ]);
    // mislukt (apart)
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'fail',
        'error' => '550 mailbox unavailable',
        'created_at' => now()->subHours(2),
    ]);
    // andere mailtypes tellen niet mee
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'NewMessageMail',
        'status' => 'ok',
        'created_at' => now()->subMinutes(30),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'ResetPasswordDutch',
        'status' => 'ok',
        'created_at' => now()->subMinutes(10),
    ]);
    // andere user: alleen aanmelding
    MailLog::query()->create([
        'user_id' => $ander->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subHours(5),
    ]);

    $stats = BevestigingsmailStats::voorUsers([$lid->id, $ander->id]);

    expect($stats)->toHaveCount(2);

    $otto = $stats->get($lid->id);
    expect($otto->verifyDutch)->toBe(2)
        ->and($otto->autoHerinnering)->toBe(1)
        ->and($otto->aanmelding())->toBe(1)
        ->and($otto->herinneringen())->toBe(2) // 1 extra VerifyEmailDutch + 1 auto
        ->and($otto->totaalOk())->toBe(3)
        ->and($otto->mislukt)->toBe(1)
        ->and($otto->tellingLabel())->toBe('Bevestigingsmail: 1× bij aanmelding + 2 herinneringen')
        ->and($otto->samenvatting())->toContain('laatste: 08-11-2026 14:00')
        ->and($otto->samenvatting())->toContain('1 mislukt')
        ->and($otto->herinnerdLabel())->toContain('2×')
        ->and($otto->laatsteVerzendHint())->toContain('Laatst verstuurd:');

    $anderStats = $stats->get($ander->id);
    expect($anderStats->aanmelding())->toBe(1)
        ->and($anderStats->herinneringen())->toBe(0)
        ->and($anderStats->mislukt)->toBe(0)
        ->and($anderStats->tellingLabel())->toBe('Bevestigingsmail: alleen bij aanmelding');
});

test('BevestigingsmailStats doet één groupBy-query zonder N+1', function () {
    $a = User::factory()->unverified()->create();
    $b = User::factory()->unverified()->create();
    foreach ([$a, $b] as $u) {
        MailLog::query()->create(['user_id' => $u->id, 'type' => 'VerifyEmailDutch', 'status' => 'ok', 'created_at' => now()]);
        MailLog::query()->create(['user_id' => $u->id, 'type' => 'VerifyEmailReminderDutch', 'status' => 'ok', 'created_at' => now()]);
    }

    Schema::hasTable('mail_log');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $stats = BevestigingsmailStats::voorUsers([$a->id, $b->id]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($stats->get($a->id)->herinneringen())->toBe(1)
        ->and($stats->get($b->id)->herinneringen())->toBe(1);

    $groupBy = array_values(array_filter(
        $queries,
        static fn (array $q) => str_contains(strtolower($q['query']), 'mail_log')
            && str_contains(strtolower($q['query']), 'group by'),
    ));
    expect($groupBy)->toHaveCount(1);

    $mailLogSelects = array_values(array_filter(
        $queries,
        static fn (array $q) => str_contains(strtolower($q['query']), 'from "mail_log"')
            || str_contains(strtolower($q['query']), 'from `mail_log`')
            || str_contains(strtolower($q['query']), 'from mail_log'),
    ));
    expect(count($mailLogSelects))->toBeLessThanOrEqual(1);
});

test('lijst toont aanmelding + herinneringen en laatste regel', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-09 15:30:00', 'Europe/Amsterdam'));
    $admin = beheerAdminVoorBevestiging();
    $lid = User::factory()->unverified()->create(['name' => 'Wachtende Wilma']);
    $bevestigd = User::factory()->create(['name' => 'Bevestigde Bert']);

    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(2),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailReminderDutch',
        'status' => 'ok',
        'created_at' => now()->subDay(),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'NewMessageMail',
        'status' => 'ok',
        'created_at' => now(),
    ]);

    $html = $this->actingAs($admin)
        ->get(route('beheer.gebruikers', ['filter' => 'onbevestigd']))
        ->assertOk()
        ->assertSee('Wachtende Wilma', false)
        ->assertDontSee('Bevestigde Bert', false)
        ->assertSee('Bevestigingsmail: 1× bij aanmelding + 1 herinnering', false)
        ->assertSee('laatste: 08-11-2026 15:30', false)
        ->getContent();

    expect($html)->not->toContain('NewMessageMail');
});

test('detailpagina heeft eigen rij Herinnerd en laatste bij herstuur-knop', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-09 16:00:00', 'Europe/Amsterdam'));
    $admin = beheerAdminVoorBevestiging();
    $lid = User::factory()->unverified()->create(['name' => 'Detail Dana', 'email' => 'dana@example.test']);

    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'ok',
        'created_at' => now()->subDays(1),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailReminderDutch',
        'status' => 'ok',
        'created_at' => now()->subHours(3),
    ]);
    MailLog::query()->create([
        'user_id' => $lid->id,
        'type' => 'VerifyEmailDutch',
        'status' => 'fail',
        'error' => 'Connection could not be established',
        'created_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('beheer.gebruikers.show', $lid))
        ->assertOk()
        ->assertSee('>Herinnerd</dt>', false)
        ->assertSee('1× · laatste', false)
        ->assertSee('1 mislukt', false)
        ->assertSee('Bevestigingsmail opnieuw sturen', false)
        ->assertSee('Laatst verstuurd:', false)
        ->assertSee('09-11-2026 13:00', false)
        ->assertDontSee('Bevestigingsmail: 1× bij aanmelding', false);
});

test('filter onbevestigd blijft werken naast de nieuwe weergave', function () {
    $admin = beheerAdminVoorBevestiging();
    User::factory()->unverified()->create(['name' => 'NogOpen']);
    User::factory()->create(['name' => 'AlBevestigd']);

    $this->actingAs($admin)
        ->get(route('beheer.gebruikers', ['filter' => 'onbevestigd']))
        ->assertOk()
        ->assertSee('NogOpen', false)
        ->assertDontSee('AlBevestigd', false)
        ->assertSee('>Bevestigingsmails<', false);
});
