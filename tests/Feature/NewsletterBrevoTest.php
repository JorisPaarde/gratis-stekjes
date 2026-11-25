<?php

declare(strict_types=1);

use App\Jobs\SyncNewsletterContactJob;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\AvgService;
use App\Services\BrevoNewsletterService;
use App\Services\NewsletterOptOutBackfill;
use App\Support\FormGuard;
use App\Support\Geo;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function nieuwsbriefPassword(): string
{
    return implode('-', ['TestOnly', 'Newsletter', 'Pass', '9']);
}

function nieuwsbriefRegPayload(array $extra = []): array
{
    return array_merge([
        FormGuard::HONEYPOT_FIELD => '',
        'website' => '',
        'form_started' => time() - 10,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'nieuwsbrief' => '1',
        'email' => 'nieuws@example.com',
        'password' => nieuwsbriefPassword(),
    ], $extra);
}

function seedNieuwsbriefGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
}

function configureBrevoForTests(): void
{
    config([
        'services.brevo.sync_enabled' => true,
        'services.brevo.api_key' => 'test-brevo-key',
        'services.brevo.newsletter_list_id' => 42,
        'services.brevo.webhook_token' => 'webhook-test-token-16',
    ]);
}

beforeEach(function () {
    configureBrevoForTests();
});

function profielPayload(string $name, string $nieuwsbrief): array
{
    return [
        'voornaam' => $name,
        'country' => 'NL',
        'locatie_modus' => 'postcode',
        'locatie' => '2011 AB',
        'berichtmail' => '1',
        'nieuwsbrief' => $nieuwsbrief,
        'adres_delen' => '0',
    ];
}

function profielUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Ben',
        'locatie_label' => '2011 Haarlem',
        'postcode_gebied' => '2011',
        'woonplaats' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ], $extra));
}

test('registratiepagina toont nieuwsbriefvinkje standaard aangevinkt', function () {
    $html = $this->get(route('account.registreren'))->assertOk()->getContent();

    expect($html)->toContain('name="nieuwsbrief"')
        ->and($html)->toMatch('/type="checkbox" name="nieuwsbrief"[^>]*checked/');
});

test('registreren met standaard vinkje zet opt-in aan zonder Brevo-call', function () {
    Notification::fake();
    Bus::fake([SyncNewsletterContactJob::class]);
    seedNieuwsbriefGeo();
    Http::preventStrayRequests();

    $this->post(route('account.registreren'), nieuwsbriefRegPayload([
        'nieuwsbrief' => '1',
        'email' => 'optin@example.com',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'optin@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->newsletter_opt_in)->toBeTrue()
        ->and($user->newsletter_opt_in_at)->not->toBeNull()
        ->and($user->newsletter_opt_out_at)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    // Onbevestigd: nog niets naar Brevo.
    Bus::assertNotDispatched(SyncNewsletterContactJob::class);
});

test('registreren zonder het veld laat de nieuwsbrief standaard aan', function () {
    Notification::fake();
    seedNieuwsbriefGeo();

    $payload = nieuwsbriefRegPayload(['email' => 'zonderveld@example.com']);
    unset($payload['nieuwsbrief']);
    $this->post(route('account.registreren'), $payload)->assertRedirect(route('account.bevestiging-nodig'));

    expect(User::query()->where('email', 'zonderveld@example.com')->first()->newsletter_opt_in)->toBeTrue();
});

test('registreren met vinkje uit houdt nieuwsbrief uit en onthoudt de keuze', function () {
    Notification::fake();
    seedNieuwsbriefGeo();

    $this->post(route('account.registreren'), nieuwsbriefRegPayload([
        'nieuwsbrief' => '0',
        'email' => 'geenoptin@example.com',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'geenoptin@example.com')->first();
    expect($user->newsletter_opt_in)->toBeFalse()
        ->and($user->newsletter_opt_in_at)->toBeNull()
        ->and($user->newsletter_opt_out_at)->not->toBeNull();
});

function bevestigUrl(User $user): string
{
    $token = $user->rotateEmailVerificationToken();

    return URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);
}

test('e-mailbevestiging met opt-in plant lijst-sync', function () {
    Bus::fake([SyncNewsletterContactJob::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'sync-na-verify@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $this->get(bevestigUrl($user))->assertRedirect(route('account.welkom'));
    Bus::assertDispatched(SyncNewsletterContactJob::class, fn ($job) => $job->userId === (int) $user->id && $job->explicit === false);
});

test('e-mailbevestiging zonder opt-in plant geen sync', function () {
    Bus::fake([SyncNewsletterContactJob::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'geen-sync@example.com',
        'newsletter_opt_in' => false,
    ]);

    $this->get(bevestigUrl($user))->assertRedirect(route('account.welkom'));
    Bus::assertNotDispatched(SyncNewsletterContactJob::class);
});

test('syncUser zet contact op lijst via upsert, zonder mail of campagne', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/*' => Http::response([], 404),
        'api.brevo.com/v3/contacts' => Http::response(['id' => 1], 201),
    ]);

    $user = User::factory()->create([
        'email' => 'upsert@example.com',
        'name' => 'Ada',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $result = app(BrevoNewsletterService::class)->syncUser($user);
    expect($result['ok'])->toBeTrue()
        ->and($user->fresh()->newsletter_listed_at)->not->toBeNull();

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r->url() === 'https://api.brevo.com/v3/contacts'
        && $r->hasHeader('api-key', 'test-brevo-key')
        && $r['listIds'] === [42]
        && $r['updateEnabled'] === true
        && ! isset($r['emailBlacklisted']));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'emailCampaigns')
        || str_contains($r->url(), '/smtp/')
        || str_contains($r->url(), 'doubleOptin'));
});

test('syncUser respecteert een afmelding in Brevo (blacklist) en zet vlag uit', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/*' => Http::response(['emailBlacklisted' => true, 'listUnsubscribed' => []], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'blacklisted@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $result = app(BrevoNewsletterService::class)->syncUser($user);
    expect($result['respected'] ?? false)->toBeTrue()
        ->and($user->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($user->fresh()->newsletter_opt_out_at)->not->toBeNull();
    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

test('expliciet opnieuw aanzetten heft de blacklist op', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/*' => Http::response(['emailBlacklisted' => true], 200),
        'api.brevo.com/v3/contacts' => Http::response([], 204),
    ]);

    $user = User::factory()->create([
        'email' => 'terug@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    expect(app(BrevoNewsletterService::class)->syncUser($user, true)['ok'])->toBeTrue();
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['emailBlacklisted'] === false && $r['listIds'] === [42]);
});

test('onbevestigd of inactief lid wordt nooit gesynct', function () {
    Http::preventStrayRequests();

    $onbevestigd = User::factory()->unverified()->create(['email' => 'onb@example.com', 'newsletter_opt_in' => true]);
    $inactief = User::factory()->create(['email' => 'inactief@example.com', 'newsletter_opt_in' => true, 'active' => false]);

    $svc = app(BrevoNewsletterService::class);
    expect($svc->syncUser($onbevestigd)['skipped'] ?? false)->toBeTrue()
        ->and($svc->syncUser($inactief)['skipped'] ?? false)->toBeTrue();
});

test('zonder BREVO_SYNC_ENABLED doet de site geen enkele Brevo-call (staging-veilig)', function () {
    config(['services.brevo.sync_enabled' => false]);
    Http::preventStrayRequests();
    seedNieuwsbriefGeo();

    $user = profielUser(['email' => 'staging@example.com', 'newsletter_opt_in' => true, 'newsletter_opt_in_at' => now(), 'newsletter_listed_at' => now()]);

    $this->actingAs($user)->post(route('account.profiel'), profielPayload('Ben', '0'))
        ->assertRedirect(route('account.profiel'));
    expect($user->fresh()->newsletter_opt_in)->toBeFalse();

    $this->actingAs($user)->post(route('account.profiel'), profielPayload('Ben', '1'))
        ->assertRedirect(route('account.profiel'));
    expect($user->fresh()->newsletter_opt_in)->toBeTrue();

    expect(app(BrevoNewsletterService::class)->sync()['skipped'] ?? false)->toBeTrue();
    Http::assertNothingSent();
});

test('profiel toont schakelaar en afmelden haalt contact van de Brevo-lijst', function () {
    seedNieuwsbriefGeo();
    Http::fake([
        'api.brevo.com/v3/contacts/lists/42/contacts/remove' => Http::response([], 204),
        '*' => Http::response([], 200),
    ]);

    $user = profielUser([
        'email' => 'profiel-nb@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now(),
    ]);

    $html = $this->actingAs($user)->get(route('account.profiel'))->assertOk()->getContent();
    expect($html)->toContain('Ik ontvang af en toe de nieuwsbrief')
        ->and($html)->toMatch('/type="checkbox" name="nieuwsbrief"[^>]*checked/');

    $this->actingAs($user)->post(route('account.profiel'), profielPayload('Ben', '0'))
        ->assertRedirect(route('account.profiel'));

    $fresh = $user->fresh();
    expect($fresh->newsletter_opt_in)->toBeFalse()
        ->and($fresh->newsletter_listed_at)->toBeNull()
        ->and($fresh->newsletter_opt_out_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/contacts/lists/42/contacts/remove'));
});

test('profiel opnieuw aanzetten plant expliciete sync en wist afmeldmoment', function () {
    Bus::fake([SyncNewsletterContactJob::class]);
    seedNieuwsbriefGeo();

    $user = profielUser([
        'email' => 'aan-via-profiel@example.com',
        'newsletter_opt_in' => false,
        'newsletter_opt_out_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->post(route('account.profiel'), profielPayload('Ben', '1'))
        ->assertRedirect(route('account.profiel'));

    expect($user->fresh()->newsletter_opt_in)->toBeTrue()
        ->and($user->fresh()->newsletter_opt_out_at)->toBeNull();
    Bus::assertDispatched(SyncNewsletterContactJob::class, fn ($job) => $job->explicit === true);
});

test('brevo webhook unsubscribed zet opt-in uit en onthoudt afmelding', function () {
    $user = User::factory()->create([
        'email' => 'unsub@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now(),
    ]);

    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        'event' => 'unsubscribed',
        'email' => 'unsub@example.com',
    ])->assertOk()->assertExactJson(['ok' => true, 'handled' => 1]);

    expect($user->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($user->fresh()->newsletter_opt_out_at)->not->toBeNull()
        ->and($user->fresh()->newsletter_listed_at)->toBeNull();
});

test('brevo webhook spam en contactDeleted (camelCase) zetten opt-in uit', function (string $event) {
    $user = User::factory()->create([
        'email' => 'ev-'.strtolower($event).'@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now(),
    ]);

    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        'event' => $event,
        'email' => $user->email,
    ])->assertOk()->assertJsonPath('handled', 1);

    expect($user->fresh()->newsletter_opt_in)->toBeFalse();
})->with(['spam', 'contactDeleted']);

test('brevo webhook negeert andere events en onbekende adressen', function () {
    $user = User::factory()->create(['email' => 'blijft@example.com', 'newsletter_opt_in' => true]);

    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        ['event' => 'delivered', 'email' => 'blijft@example.com'],
        ['event' => 'unsubscribed', 'email' => 'onbekend@example.com'],
    ])->assertOk()->assertJsonPath('handled', 0);

    expect($user->fresh()->newsletter_opt_in)->toBeTrue();
});

test('brevo webhook werkt ook als sync uit staat', function () {
    config(['services.brevo.sync_enabled' => false]);
    $user = User::factory()->create(['email' => 'uit@example.com', 'newsletter_opt_in' => true]);

    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        'event' => 'unsubscribed', 'email' => 'uit@example.com',
    ])->assertOk();

    expect($user->fresh()->newsletter_opt_in)->toBeFalse();
});

test('brevo webhook weigert verkeerde of ontbrekende token', function () {
    $user = User::factory()->create(['email' => 'veilig@example.com', 'newsletter_opt_in' => true]);

    $this->postJson(route('webhooks.brevo', ['token' => 'verkeerd-token-xxxx']), [
        'event' => 'unsubscribed', 'email' => 'veilig@example.com',
    ])->assertForbidden();

    config(['services.brevo.webhook_token' => '']);
    $this->postJson('/webhooks/brevo/', [])->assertStatus(404);
    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        'event' => 'unsubscribed', 'email' => 'veilig@example.com',
    ])->assertForbidden();

    expect($user->fresh()->newsletter_opt_in)->toBeTrue();
});

test('sync: zet leden met opt-in op lijst, opteert uit bij verdwijnen, haalt afgemelde eraf', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if ($request->method() === 'GET' && str_contains($url, '/contacts/lists/42/contacts')) {
            return Http::response(['contacts' => [
                ['email' => 'op-lijst@example.com'],
                ['email' => 'afgemeld-lokaal@example.com'],
                ['email' => 'geen-lid@example.com'],
            ]], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response([], 404);
        }

        return Http::response([], 204);
    });

    $onList = User::factory()->create(['email' => 'op-lijst@example.com', 'newsletter_opt_in' => true, 'newsletter_opt_in_at' => now()]);
    $gone = User::factory()->create(['email' => 'weg-van-lijst@example.com', 'newsletter_opt_in' => true, 'newsletter_opt_in_at' => now(), 'newsletter_listed_at' => now()->subDay()]);
    $new = User::factory()->create(['email' => 'nieuw-lid@example.com', 'newsletter_opt_in' => true, 'newsletter_opt_in_at' => now()]);
    $unverified = User::factory()->unverified()->create(['email' => 'onbevestigd@example.com', 'newsletter_opt_in' => true]);
    User::factory()->create(['email' => 'afgemeld-lokaal@example.com', 'newsletter_opt_in' => false]);

    $result = app(BrevoNewsletterService::class)->sync();

    expect($result['ok'])->toBeTrue()
        ->and($result['listed'])->toBe(1)
        ->and($result['added'])->toBe(1)
        ->and($result['opted_out'])->toBe(1)
        ->and($result['removed'])->toBe(1)
        ->and($result['pending'])->toBe(0);

    expect($onList->fresh()->newsletter_listed_at)->not->toBeNull()
        ->and($gone->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($gone->fresh()->newsletter_opt_out_at)->not->toBeNull()
        ->and($new->fresh()->newsletter_listed_at)->not->toBeNull()
        ->and($unverified->fresh()->newsletter_listed_at)->toBeNull();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/contacts/lists/42/contacts/remove')
        && $r['emails'] === ['afgemeld-lokaal@example.com']);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'emailCampaigns') || str_contains($r->url(), '/smtp/'));
});

test('sync verwerkt max. 30 nieuwe contacten per run en meldt wat nog wacht', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/contacts/lists/42/contacts')) {
            return Http::response(['contacts' => []], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response([], 404);
        }

        return Http::response([], 201);
    });

    User::factory()->count(33)->create(['newsletter_opt_in' => true, 'newsletter_opt_in_at' => now()]);

    $result = app(BrevoNewsletterService::class)->sync();
    expect($result['added'])->toBe(30)->and($result['pending'])->toBe(3);
});

test('backfill zet alleen bevestigde, actieve leden zonder keuze aan en is idempotent', function () {
    $bevestigd = User::factory()->create(['email' => 'bev@example.com']);
    $onbevestigd = User::factory()->unverified()->create(['email' => 'onbev@example.com']);
    $inactief = User::factory()->create(['email' => 'inact@example.com', 'active' => false]);
    $zelfUit = User::factory()->create(['email' => 'zelfuit@example.com', 'newsletter_opt_out_at' => now()->subDay()]);
    $alAan = User::factory()->create(['email' => 'alaan@example.com', 'newsletter_opt_in' => true, 'newsletter_opt_in_at' => now()->subDays(3)]);

    $backfill = app(NewsletterOptOutBackfill::class);
    expect($backfill->count())->toBe(1)
        ->and($backfill->apply())->toBe(1)
        ->and($backfill->apply())->toBe(0)
        ->and($backfill->count())->toBe(0);

    expect($bevestigd->fresh()->newsletter_opt_in)->toBeTrue()
        ->and($bevestigd->fresh()->newsletter_opt_in_at)->not->toBeNull()
        ->and($onbevestigd->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($inactief->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($zelfUit->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($alAan->fresh()->newsletter_opt_in_at->isToday())->toBeFalse();
});

test('backfill-command dry-run toont alleen een aantal en wijzigt niets', function () {
    $user = User::factory()->create(['email' => 'dry@example.com']);

    $this->artisan('gp:nieuwsbrief-backfill', ['--dry-run' => true])
        ->expectsOutputToContain('1 bevestigde leden')
        ->assertSuccessful();

    expect($user->fresh()->newsletter_opt_in)->toBeFalse();
});

test('account wissen verwijdert contact uit Brevo-lijst', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/lists/42/contacts/remove' => Http::response([], 204),
    ]);

    $user = User::factory()->create([
        'email' => 'wissen-nb@example.com',
        'newsletter_opt_in' => true,
    ]);
    $id = $user->id;

    app(AvgService::class)->purgeUserAccount($user);

    expect(User::query()->find($id))->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/contacts/lists/42/contacts/remove'));
});

test('cron bevat newsletter_sync job-key', function () {
    config(['stekjes.cron_token' => 'test-cron-token']);

    Http::fake([
        'api.brevo.com/*' => Http::response(['contacts' => []], 200),
    ]);

    $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->getJson('/cron')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['jobs' => ['newsletter_sync']]);
});

test('privacy pagina beschrijft nieuwsbrief, Brevo als verwerker en afmelden', function () {
    $html = $this->get(route('privacy'))->assertOk()->getContent();
    expect($html)->toContain('Nieuwsbrief en Brevo')
        ->and($html)->toContain('verwerker')
        ->and($html)->toContain('Afmelden')
        ->and($html)->toContain('afmeldlink')
        ->and($html)->not->toContain('dubbele opt-in')
        ->and($html)->not->toContain('niet vooraf aangevinkt');
});

test('Brevo-fouten breken sync niet: lid blijft aan en krijgt geen listed-stempel', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response([], 404);
        }

        return Http::response(['message' => 'fail'], 500);
    });

    $user = User::factory()->create([
        'email' => 'failsoft@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $result = app(BrevoNewsletterService::class)->syncUser($user);
    expect($result['ok'])->toBeFalse()
        ->and($user->fresh()->newsletter_opt_in)->toBeTrue()
        ->and($user->fresh()->newsletter_listed_at)->toBeNull();
});
