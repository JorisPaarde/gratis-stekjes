<?php

declare(strict_types=1);

use App\Jobs\RequestNewsletterDoiJob;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\AvgService;
use App\Services\BrevoNewsletterService;
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
        'nieuwsbrief' => '0',
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
        'services.brevo.api_key' => 'test-brevo-key',
        'services.brevo.newsletter_list_id' => 42,
        'services.brevo.doi_template_id' => 7,
        'services.brevo.doi_redirect_url' => 'https://example.test/account',
        'services.brevo.webhook_token' => 'webhook-test-token-16',
    ]);
}

beforeEach(function () {
    configureBrevoForTests();
});

test('registratiepagina toont nieuwsbriefvinkje niet vooraf aangevinkt', function () {
    $html = $this->get(route('account.registreren'))->assertOk()->getContent();

    expect($html)->toContain('Stuur me af en toe een nieuwsbrief')
        ->and($html)->toContain('name="nieuwsbrief"')
        ->and($html)->not->toMatch('/name="nieuwsbrief"[^>]*checked/');
});

test('registreren met nieuwsbriefvinkje bewaart lokale opt-in zonder Brevo-call', function () {
    Notification::fake();
    Bus::fake([RequestNewsletterDoiJob::class]);
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
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Bus::assertNotDispatched(RequestNewsletterDoiJob::class);
});

test('registreren zonder vinkje houdt nieuwsbrief uit', function () {
    Notification::fake();
    seedNieuwsbriefGeo();

    $this->post(route('account.registreren'), nieuwsbriefRegPayload([
        'nieuwsbrief' => '0',
        'email' => 'geenoptin@example.com',
    ]))->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'geenoptin@example.com')->first();
    expect($user->newsletter_opt_in)->toBeFalse()
        ->and($user->newsletter_opt_in_at)->toBeNull();
});

test('e-mailbevestiging met opt-in plant Brevo DOI-job', function () {
    Bus::fake([RequestNewsletterDoiJob::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'doi-na-verify@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);
    $token = $user->rotateEmailVerificationToken();
    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)->assertRedirect(route('account.welkom'));

    Bus::assertDispatched(RequestNewsletterDoiJob::class, fn (RequestNewsletterDoiJob $job) => $job->userId === (int) $user->id);
});

test('e-mailbevestiging zonder opt-in plant geen DOI-job', function () {
    Bus::fake([RequestNewsletterDoiJob::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'geen-doi@example.com',
        'newsletter_opt_in' => false,
    ]);
    $token = $user->rotateEmailVerificationToken();
    $url = URL::temporarySignedRoute('account.bevestigen', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
        'token' => $token,
    ]);

    $this->get($url)->assertRedirect(route('account.welkom'));
    Bus::assertNotDispatched(RequestNewsletterDoiJob::class);
});

test('DOI-job stuurt Brevo doubleOptinConfirmation zonder PII in assert', function () {
    Http::fake([
        'api.brevo.com/v3/contacts/doubleOptinConfirmation' => Http::response([], 201),
    ]);

    $user = User::factory()->create([
        'email' => 'doi-job@example.com',
        'name' => 'Ada',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $result = app(BrevoNewsletterService::class)->sendDoubleOptIn($user);
    expect($result['ok'])->toBeTrue();
    expect($user->fresh()->newsletter_doi_sent_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.brevo.com/v3/contacts/doubleOptinConfirmation'
            && $request->hasHeader('api-key', 'test-brevo-key')
            && $request['templateId'] === 7
            && $request['includeListIds'] === [42]
            && $request['redirectionUrl'] === 'https://example.test/account'
            && isset($request['email']);
    });
});

test('profiel toont nieuwsbriefvinkje en afmelden haalt uit Brevo-lijst', function () {
    seedNieuwsbriefGeo();
    Http::fake([
        'api.brevo.com/v3/contacts/lists/42/contacts/remove' => Http::response([], 204),
        '*' => Http::response([], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'profiel-nb@example.com',
        'name' => 'Ben',
        'locatie_label' => '2011 Haarlem',
        'postcode_gebied' => '2011',
        'woonplaats' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now(),
    ]);

    $html = $this->actingAs($user)->get(route('account.profiel'))->assertOk()->getContent();
    expect($html)->toContain('Stuur me af en toe een nieuwsbrief')
        ->and($html)->toContain('name="nieuwsbrief"');

    $this->actingAs($user)->post(route('account.profiel'), [
        'voornaam' => 'Ben',
        'country' => 'NL',
        'locatie_modus' => 'postcode',
        'locatie' => '2011 AB',
        'berichtmail' => '1',
        'nieuwsbrief' => '0',
        'adres_delen' => '0',
    ])->assertRedirect(route('account.profiel'));

    expect($user->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($user->fresh()->newsletter_listed_at)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/contacts/lists/42/contacts/remove'));
});

test('profiel aanzetten na verify plant DOI', function () {
    Bus::fake([RequestNewsletterDoiJob::class]);
    seedNieuwsbriefGeo();

    $user = User::factory()->create([
        'email' => 'aan-via-profiel@example.com',
        'name' => 'Cara',
        'locatie_label' => '2011 Haarlem',
        'postcode_gebied' => '2011',
        'woonplaats' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'newsletter_opt_in' => false,
    ]);

    $this->actingAs($user)->post(route('account.profiel'), [
        'voornaam' => 'Cara',
        'country' => 'NL',
        'locatie_modus' => 'postcode',
        'locatie' => '2011 AB',
        'berichtmail' => '1',
        'nieuwsbrief' => '1',
        'adres_delen' => '0',
    ])->assertRedirect(route('account.profiel'));

    expect($user->fresh()->newsletter_opt_in)->toBeTrue();
    Bus::assertDispatched(RequestNewsletterDoiJob::class);
});

test('brevo webhook unsubscribe zet lokale opt-in uit', function () {
    $user = User::factory()->create([
        'email' => 'unsub@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now(),
    ]);

    $this->postJson(route('webhooks.brevo', ['token' => 'webhook-test-token-16']), [
        'event' => 'unsubscribe',
        'email' => 'unsub@example.com',
    ])->assertOk()->assertJsonPath('handled', 1);

    expect($user->fresh()->newsletter_opt_in)->toBeFalse()
        ->and($user->fresh()->newsletter_listed_at)->toBeNull();
});

test('brevo webhook weigert verkeerde token', function () {
    $this->postJson(route('webhooks.brevo', ['token' => 'verkeerd-token-xxxx']), [
        'event' => 'unsubscribe',
        'email' => 'x@example.com',
    ])->assertForbidden();
});

test('dagelijkse sync markeert listed en opteert uit bij verdwijnen van lijst', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/contacts/lists/42/contacts')
            && ! str_contains($request->url(), '/remove')) {
            return Http::response(['contacts' => [['email' => 'op-lijst@example.com']]], 200);
        }

        return Http::response([], 200);
    });

    $onList = User::factory()->create([
        'email' => 'op-lijst@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);
    $gone = User::factory()->create([
        'email' => 'weg-van-lijst@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
        'newsletter_listed_at' => now()->subDay(),
        'newsletter_doi_sent_at' => now()->subDays(2),
    ]);

    $result = app(BrevoNewsletterService::class)->syncFromBrevo();
    expect($result['ok'])->toBeTrue()
        ->and($result['listed'])->toBe(1)
        ->and($result['opted_out'])->toBe(1);

    expect($onList->fresh()->newsletter_listed_at)->not->toBeNull()
        ->and($gone->fresh()->newsletter_opt_in)->toBeFalse();
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

test('privacy pagina noemt Brevo nieuwsbrief', function () {
    $html = $this->get(route('privacy'))->assertOk()->getContent();
    expect($html)->toContain('Brevo')
        ->and($html)->toContain('nieuwsbrief')
        ->and($html)->toContain('dubbele opt-in');
});

test('DOI faalt soft: registratie/verify blijft ok en job kan falen zonder crash', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'doubleOptinConfirmation')) {
            return Http::response(['message' => 'fail'], 500);
        }

        return Http::response([], 200);
    });

    $user = User::factory()->create([
        'email' => 'failsoft@example.com',
        'newsletter_opt_in' => true,
        'newsletter_opt_in_at' => now(),
    ]);

    $result = app(BrevoNewsletterService::class)->sendDoubleOptIn($user);
    expect($result['ok'])->toBeFalse()
        ->and($user->fresh()->newsletter_opt_in)->toBeTrue()
        ->and($user->fresh()->newsletter_doi_sent_at)->toBeNull();
});
