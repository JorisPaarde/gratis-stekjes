<?php

declare(strict_types=1);

use App\Mail\AanbodExpiryReminderMail;
use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\AanbodExpiryService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['stekjes.cron_token' => 'test-cron-token']);
});

test('cron weigert zonder geldige token', function () {
    $this->get('/cron')->assertForbidden();
    $this->withHeader('X-Cron-Token', 'wrong')->get('/cron')->assertForbidden();
});

test('cron stuurt herinnering en purge geocode-cache', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'owner@example.com',
        'active' => true,
    ]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Bessenstekjes voor de buurt',
        'visible' => true,
        'expires_at' => now()->addDays(5),
        'expiry_reminder_sent_at' => null,
    ]);

    GeocodeCache::query()->create([
        'query_key' => 'oud',
        'lat' => 52.1,
        'lon' => 5.1,
        'label' => 'Oud',
        'cached_at' => now()->subDays(120),
    ]);
    GeocodeCache::query()->create([
        'query_key' => 'nieuw',
        'lat' => 52.2,
        'lon' => 5.2,
        'label' => 'Nieuw',
        'cached_at' => now(),
    ]);

    $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->getJson('/cron')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('jobs.expiry_reminders.sent', 1)
        ->assertJsonPath('jobs.purge_geocode_cache.deleted', 1);

    Mail::assertSent(AanbodExpiryReminderMail::class);
    expect(Aanbod::query()->first()->renew_token)->not->toBeNull();
    expect(GeocodeCache::query()->where('query_key', 'oud')->exists())->toBeFalse();
    expect(GeocodeCache::query()->where('query_key', 'nieuw')->exists())->toBeTrue();
});

test('eenklik verlengen via token werkt eenmalig', function () {
    $service = app(AanbodExpiryService::class);
    $user = User::factory()->create();
    $token = $service->makeRenewToken();

    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'ophalen',
        'tekst' => 'Zoek tomaten',
        'visible' => true,
        'expires_at' => now()->addDays(3),
        'renew_token' => $token,
        'renew_token_expires' => now()->addDays(7),
        'expiry_reminder_sent_at' => now(),
    ]);

    $this->get(route('account.aanbod-verlengen', ['token' => $token]))
        ->assertOk()
        ->assertSee('Aanbod verlengd', false);

    $aanbod->refresh();
    expect($aanbod->renew_token)->toBeNull();
    expect($aanbod->expires_at->greaterThan(now()->addDays(50)))->toBeTrue();

    $this->get(route('account.aanbod-verlengen', ['token' => $token]))
        ->assertOk()
        ->assertSee('Verlengen mislukt', false);
});
