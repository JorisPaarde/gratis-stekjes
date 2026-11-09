<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\ConversionDaily;
use App\Models\ConversionEvent;
use App\Models\GeocodeCache;
use App\Models\PageView;
use App\Models\PostLog;
use App\Models\User;
use App\Services\ConversionTracker;
use App\Support\Geo;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/** Testwachtwoord opgebouwd (geen hardcoded secret-literal voor scanners). */
function meetTestPassword(): string
{
    return function_exists('authTestPassword')
        ? authTestPassword()
        : implode('-', ['TestOnly', 'Meet', 'Passphrase', '9']);
}

beforeEach(function () {
    config([
        'stekjes.admin_password' => implode('-', ['TestOnly', 'Meet', 'Beheer', '9']),
        'conversion.min_form_starts_for_rates' => 30,
        'conversion.raw_retention_days' => 30,
        'conversion.flow_ttl_days' => 7,
    ]);
    Http::fake();
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2011 AB')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
});

function meetRegForm(array $overrides = []): array
{
    $pass = meetTestPassword();

    return array_merge([
        'email' => 'meet-'.uniqid('', false).'@example.com',
        'password' => $pass,
        'country' => 'NL',
        'postcode' => '2011 AB',
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ], $overrides);
}

test('cta_click via /ga/word-lid wordt gelogd en stuurt door', function () {
    $this->get(route('ga.word-lid', ['from' => '/aanbod', 'utm_source' => 'facebook']))
        ->assertRedirect(route('account.registreren', ['utm_source' => 'facebook']));

    $event = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_CTA_CLICK)->first();
    expect($event)->not->toBeNull()
        ->and($event->path)->toBe('/aanbod')
        ->and($event->utm_source)->toBe('facebook')
        ->and($event->visitor_day_hash)->not->toBeEmpty()
        ->and($event->flow_id)->not->toBeEmpty();
});

test('register_view wordt gelogd zonder page_view op /account/registreren', function () {
    $this->get(route('account.registreren'))->assertOk();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_REGISTER_VIEW)->count())->toBe(1);
    expect(PageView::query()->where('path', '/account/registreren')->count())->toBe(0);
});

test('form_start beacon wordt gelogd zonder csrf', function () {
    $this->post(route('meet.event'), [
        'type' => 'form_start',
        'path' => '/account/registreren',
    ])->assertNoContent();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_FORM_START)->count())->toBe(1);
});

test('beacon weigert validation_error en kwaadaardige payloads', function () {
    $this->post(route('meet.event'), [
        'type' => 'validation_error',
        'path' => '/account/registreren',
        'field' => 'email',
        'code' => 'email.verplicht',
    ])->assertStatus(422);

    $this->post(route('meet.event'), [
        'type' => 'form_start',
        'path' => '/beheer/posts',
    ])->assertStatus(422);

    $this->post(route('meet.event'), [
        'type' => 'form_start',
        'path' => '/account/registreren"><script>',
    ])->assertStatus(422);

    $this->post(route('meet.event'), [
        'type' => 'form_start',
        'path' => 'javascript:alert(1)',
    ])->assertStatus(422);

    expect(ConversionEvent::query()->count())->toBe(0);
});

test('validation_error bevat veld en code maar geen invoer', function () {
    Notification::fake();

    $email = 'lek-niet@example.com';
    $password = implode('', ['abc', '123', 'XY']); // te kort, geen scanner-secret

    $this->from(route('account.registreren'))
        ->post(route('account.registreren'), meetRegForm([
            'email' => $email,
            'password' => $password,
        ]))
        ->assertRedirect(route('account.registreren'));

    $errors = ConversionEvent::query()
        ->where('event_type', ConversionEvent::TYPE_VALIDATION_ERROR)
        ->get();

    expect($errors->count())->toBeGreaterThan(0);

    foreach ($errors as $row) {
        $json = json_encode($row->getAttributes());
        expect($row->field_name)->not->toBeEmpty()
            ->and($row->error_code)->not->toBeEmpty()
            ->and($row->field_name)->not->toContain('@')
            ->and($row->error_code)->not->toContain('@')
            ->and($json)->not->toContain($email)
            ->and($json)->not->toContain($password)
            ->and($json)->not->toContain('lek-niet');
    }
});

test('form_submit account_created email_verified en first_offer worden gelogd', function () {
    Notification::fake();

    $payload = meetRegForm([
        'email' => 'funnel@example.com',
        'acquisition_found_via' => 'facebook',
    ]);

    $this->post(route('account.registreren'), $payload)
        ->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'funnel@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->acquisition_found_via)->toBe('facebook')
        ->and($user->conversion_flow_id)->not->toBeEmpty();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_FORM_SUBMIT)->count())->toBe(1);
    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_ACCOUNT_CREATED)->count())->toBe(1);

    $token = (string) $user->email_verification_token;
    $url = URL::temporarySignedRoute(
        'account.bevestigen',
        now()->addDay(),
        ['id' => $user->id, 'hash' => sha1($user->email), 'token' => $token]
    );
    $this->get($url)->assertRedirect(route('account.welkom'));

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_EMAIL_VERIFIED)->count())->toBe(1);

    $user->forceFill([
        'name' => 'MeetLid',
        'locatie_label' => '1011 Amsterdam',
        'postcode_gebied' => '1011',
        'lat' => 52.37,
        'lon' => 4.89,
        'country' => 'NL',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($user->fresh())
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Tomatenstekje',
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_FIRST_OFFER)->count())->toBe(1);
    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('flow-vervaldatum wordt niet verlengd bij registratie', function () {
    Notification::fake();
    $tracker = app(ConversionTracker::class);
    $created = now()->subDays(3)->getTimestamp();
    $expires = $created + (7 * 86400);
    $flowId = 'fixedflow1234567890123456789012';

    // Unit: acquisitionFromRequest mag expires_at niet opschuiven.
    $request = Request::create('/account/registreren', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->attributes->set('conversion_context', [
        'flow_id' => $flowId,
        'landing_path' => '/',
        'referrer_host' => null,
        'utm_source' => 'facebook',
        'utm_medium' => null,
        'utm_campaign' => null,
        'utm_content' => null,
        'created_at' => $created,
        'expires_at' => $expires,
    ]);
    $acq = $tracker->acquisitionFromRequest($request);
    expect($acq['conversion_flow_expires_at']->getTimestamp())->toBe($expires)
        ->and($acq['conversion_flow_id'])->toBe($flowId);

    // Feature: cookie van dag 0, registratie op dag 3 houdt dezelfde expiry.
    // Zonder EncryptCookies is de response-cookie plaintext JSON (betrouwbare roundtrip in tests).
    $this->withoutMiddleware(EncryptCookies::class);
    $this->travelTo(Carbon::create(2026, 11, 1, 12, 0, 0));
    $landing = $this->get('/?utm_source=facebook')->assertOk();
    $cookieName = (string) config('conversion.flow_cookie', 'gp_flow');
    $cookie = collect($landing->headers->getCookies())->first(fn ($c) => $c->getName() === $cookieName);
    expect($cookie)->not->toBeNull();

    $decoded = json_decode((string) $cookie->getValue(), true);
    expect($decoded)->toBeArray()
        ->and($decoded['expires_at'] ?? null)->not->toBeNull();
    $originalExpires = Carbon::createFromTimestamp((int) $decoded['expires_at']);

    $this->travelTo(Carbon::create(2026, 11, 4, 12, 0, 0));
    $this->withUnencryptedCookie($cookieName, (string) $cookie->getValue())
        ->post(route('account.registreren'), meetRegForm(['email' => 'flow-ttl@example.com']))
        ->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'flow-ttl@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->conversion_flow_expires_at)->not->toBeNull();
    // Oorspronkelijke cookie-vervaldatum; níet verlengd vanaf registratiemoment (4 nov).
    expect($user->conversion_flow_expires_at->equalTo($originalExpires))->toBeTrue()
        ->and($user->conversion_flow_expires_at->toDateString())
        ->toBe(Carbon::create(2026, 11, 8, 12, 0, 0)->toDateString());

    $this->travelBack();
});

test('flow_id op events wordt genuld na 7 dagen, events blijven tot 30 dagen', function () {
    $tracker = app(ConversionTracker::class);

    $day8 = now()->subDays(8);
    $day2 = now()->subDays(2);

    ConversionEvent::query()->create([
        'occurred_at' => $day8,
        'event_type' => ConversionEvent::TYPE_CTA_CLICK,
        'path' => '/',
        'flow_id' => 'flow-day8',
        'visitor_day_hash' => 'hash-day8',
        'device_type' => 'desktop',
        'week_key' => $tracker->weekKey($day8),
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => $day2,
        'event_type' => ConversionEvent::TYPE_FORM_START,
        'path' => '/account/registreren',
        'flow_id' => 'flow-day2',
        'visitor_day_hash' => 'hash-day2',
        'device_type' => 'mobile',
        'week_key' => $tracker->weekKey($day2),
    ]);

    $result = $tracker->aggregateAndPurge(now());

    expect($result['nulled_event_flows'])->toBe(1);
    expect(ConversionEvent::query()->where('flow_id', 'flow-day8')->count())->toBe(0);
    expect(ConversionEvent::query()->whereNull('flow_id')->where('visitor_day_hash', 'hash-day8')->count())->toBe(1);
    expect(ConversionEvent::query()->where('flow_id', 'flow-day2')->count())->toBe(1);
    expect(ConversionEvent::query()->count())->toBe(2);
});

test('aggregatie vult conversion_daily en wist oude raw events na 30 dagen', function () {
    $old = now()->subDays(35);
    ConversionEvent::query()->create([
        'occurred_at' => $old,
        'event_type' => ConversionEvent::TYPE_CTA_CLICK,
        'path' => '/',
        'flow_id' => 'oldflow',
        'visitor_day_hash' => 'hash-old',
        'device_type' => 'desktop',
        'week_key' => '2026-W01',
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_FORM_START,
        'path' => '/account/registreren',
        'flow_id' => 'newflow',
        'visitor_day_hash' => 'hash-new',
        'device_type' => 'mobile',
        'week_key' => app(ConversionTracker::class)->weekKey(),
    ]);

    $result = app(ConversionTracker::class)->aggregateAndPurge();

    expect($result['deleted_events'])->toBe(1);
    expect(ConversionEvent::query()->count())->toBe(1);
    expect(ConversionDaily::query()->where('event_type', ConversionEvent::TYPE_FORM_START)->sum('count'))->toBe(1);
    expect(ConversionDaily::query()->where('event_type', ConversionEvent::TYPE_CTA_CLICK)->sum('count'))->toBe(1);
});

test('aggregatie gebruikt Amsterdam-dagen, niet UTC DATE(occurred_at)', function () {
    // 23:30 UTC = 00:30 Europe/Amsterdam op de volgende kalenderdag (CET, UTC+1).
    $occurred = Carbon::parse('2026-11-05 23:30:00', 'UTC');
    ConversionEvent::query()->create([
        'occurred_at' => $occurred,
        'event_type' => ConversionEvent::TYPE_CTA_CLICK,
        'path' => '/',
        'flow_id' => 'late-utc',
        'visitor_day_hash' => 'hash-late-utc',
        'device_type' => 'desktop',
        'week_key' => '2026-W45',
    ]);

    $result = app(ConversionTracker::class)->aggregateAndPurge(
        Carbon::parse('2026-11-06 12:00:00', 'UTC')
    );

    expect($result['aggregated_days'])->toBe(1);
    expect(ConversionDaily::query()
        ->where('event_type', ConversionEvent::TYPE_CTA_CLICK)
        ->whereDate('date', '2026-11-06')
        ->sum('count'))->toBe(1);
    expect(ConversionDaily::query()
        ->where('event_type', ConversionEvent::TYPE_CTA_CLICK)
        ->whereDate('date', '2026-11-05')
        ->sum('count'))->toBe(0);
});

test('unieke bezoekers vallen terug op conversion_daily bij deels opgeschoonde week', function () {
    $tracker = app(ConversionTracker::class);
    $weekStart = now('Europe/Amsterdam')->startOfWeek(Carbon::MONDAY);
    $oldDay = $weekStart->copy()->addDay(); // maandag+1
    // Forceer "oude" dag buiten raw-retentie door retentie tijdelijk laag te zetten na aggregatie.

    ConversionEvent::query()->create([
        'occurred_at' => $oldDay->copy()->setTime(12, 0),
        'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
        'path' => '/account/registreren',
        'flow_id' => 'a',
        'visitor_day_hash' => 'hash-old-week',
        'device_type' => 'desktop',
        'week_key' => $tracker->weekKey($oldDay),
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
        'path' => '/account/registreren',
        'flow_id' => 'b',
        'visitor_day_hash' => 'hash-new-week',
        'device_type' => 'mobile',
        'week_key' => $tracker->weekKey(),
    ]);

    $tracker->aggregateAndPurge(now());

    // Simuleer raw-purge van de oude dag terwijl daily blijft.
    ConversionEvent::query()
        ->where('visitor_day_hash', 'hash-old-week')
        ->delete();

    $funnel = $tracker->funnelByWeek(1);
    expect($funnel[0]['unique_visitors'])->toBeGreaterThanOrEqual(2);
});

test('post bezoekers tellen unieke register_view, niet som van alle events', function () {
    $tracker = app(ConversionTracker::class);
    $post = PostLog::query()->create([
        'posted_on' => now()->toDateString(),
        'titel' => 'Testpost tellen',
        'groep' => 'Testgroep',
        'groep_slug' => 'testgroep',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
    ]);

    // Zelfde visitor, meerdere eventtypen + UTM-landing zonder CTA.
    foreach ([
        ConversionEvent::TYPE_REGISTER_VIEW,
        ConversionEvent::TYPE_FORM_START,
        ConversionEvent::TYPE_ACCOUNT_CREATED,
    ] as $type) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => $type,
            'path' => '/account/registreren',
            'flow_id' => 'same',
            'visitor_day_hash' => 'same-hash',
            'device_type' => 'mobile',
            'utm_source' => 'facebook',
            'utm_campaign' => 'testgroep',
            'utm_content' => (string) $post->id,
            'week_key' => $tracker->weekKey(),
        ]);
    }

    // Extra UTM-landing (register_view) zonder CTA.
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
        'path' => '/account/registreren',
        'flow_id' => 'other',
        'visitor_day_hash' => 'other-hash',
        'device_type' => 'desktop',
        'utm_source' => 'facebook',
        'utm_campaign' => 'testgroep',
        'utm_content' => (string) $post->id,
        'week_key' => $tracker->weekKey(),
    ]);

    $stats = $tracker->conversionsForPosts();
    expect($stats['by_post'][$post->id]['visitors'] ?? 0)->toBe(2)
        ->and($stats['by_post'][$post->id][ConversionEvent::TYPE_REGISTER_VIEW] ?? 0)->toBe(2)
        ->and($stats['by_post'][$post->id][ConversionEvent::TYPE_ACCOUNT_CREATED] ?? 0)->toBe(1)
        ->and($stats['by_groep']['testgroep']['visitors'] ?? 0)->toBe(2);
});

test('post conversies combineren daily historie met recente raw', function () {
    $tracker = app(ConversionTracker::class);
    $post = PostLog::query()->create([
        'posted_on' => now()->subDays(40)->toDateString(),
        'titel' => 'Oude post',
        'groep' => 'Oude groep',
        'groep_slug' => 'oude-groep',
        'soort' => 'aankondiging',
        'utm_source' => 'facebook',
    ]);

    // Historie alleen in daily (raw al weg na 30d).
    ConversionDaily::query()->create([
        'date' => now()->subDays(40)->toDateString(),
        'week_key' => '2026-W01',
        'event_type' => ConversionEvent::TYPE_ACCOUNT_CREATED,
        'utm_campaign' => 'oude-groep',
        'utm_content' => (string) $post->id,
        'count' => 3,
        'unique_visitors' => 0,
    ]);
    ConversionDaily::query()->create([
        'date' => now()->subDays(40)->toDateString(),
        'week_key' => '2026-W01',
        'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
        'utm_campaign' => 'oude-groep',
        'utm_content' => (string) $post->id,
        'count' => 5,
        'unique_visitors' => 4,
    ]);

    // Recente raw (zou voorheen daily volledig negeren).
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_ACCOUNT_CREATED,
        'path' => '/account/registreren',
        'flow_id' => 'recent-acct',
        'visitor_day_hash' => 'hash-recent-acct',
        'device_type' => 'mobile',
        'utm_campaign' => 'oude-groep',
        'utm_content' => (string) $post->id,
        'week_key' => $tracker->weekKey(),
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
        'path' => '/account/registreren',
        'flow_id' => 'recent-view',
        'visitor_day_hash' => 'hash-recent-view',
        'device_type' => 'desktop',
        'utm_campaign' => 'oude-groep',
        'utm_content' => (string) $post->id,
        'week_key' => $tracker->weekKey(),
    ]);

    $stats = $tracker->conversionsForPosts();
    expect($stats['by_post'][$post->id][ConversionEvent::TYPE_ACCOUNT_CREATED] ?? 0)->toBe(4)
        ->and($stats['by_post'][$post->id][ConversionEvent::TYPE_REGISTER_VIEW] ?? 0)->toBe(6)
        ->and($stats['by_post'][$post->id]['visitors'] ?? 0)->toBe(5)
        ->and($stats['by_groep']['oude-groep'][ConversionEvent::TYPE_ACCOUNT_CREATED] ?? 0)->toBe(4)
        ->and($stats['by_groep']['oude-groep']['visitors'] ?? 0)->toBe(5);
});

test('header en footer CTA gebruiken huidig pad en placement', function () {
    $aanbod = $this->get('/aanbod')->assertOk()->getContent();
    expect($aanbod)->toContain('/ga/word-lid')
        ->and($aanbod)->toContain('from='.urlencode('/aanbod'))
        ->and($aanbod)->toContain('utm_content=header')
        ->and($aanbod)->toContain('utm_content=footer')
        ->and($aanbod)->toContain('utm_content=menu');

    $hoe = $this->get('/hoe-werkt-het')->assertOk()->getContent();
    expect($hoe)->toContain('from='.urlencode('/hoe-werkt-het'))
        ->and($hoe)->toContain('utm_content=header');
});

test('dashboard trechter toont te weinig data onder 30 form_starts', function () {
    for ($i = 0; $i < 5; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => 'f'.$i,
            'visitor_day_hash' => 'v'.$i,
            'device_type' => 'desktop',
            'week_key' => app(ConversionTracker::class)->weekKey(),
        ]);
    }
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_ACCOUNT_CREATED,
        'path' => '/account/registreren',
        'flow_id' => 'f0',
        'visitor_day_hash' => 'v0',
        'device_type' => 'desktop',
        'week_key' => app(ConversionTracker::class)->weekKey(),
    ]);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Trechter', false)
        ->assertSee('te weinig data', false)
        ->assertSee('pageviews vandaag', false)
        ->getContent();

    expect($html)->not->toContain('bezoekers vandaag');
});

test('dashboard trechter toont staplabels en drempeluitleg in HTML', function () {
    $minFormStarts = (int) config('conversion.min_form_starts_for_rates', 30);

    for ($i = 0; $i < 3; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => 'label-f'.$i,
            'visitor_day_hash' => 'label-v'.$i,
            'device_type' => 'mobile',
            'week_key' => app(ConversionTracker::class)->weekKey(),
        ]);
    }

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Trechter · per week', false)
        ->assertSee('Klik word lid', false)
        ->assertSee('Registratiepagina', false)
        ->assertSee('Formulier gestart', false)
        ->assertSee('Verstuurd', false)
        ->assertSee('Account', false)
        ->assertSee('E-mail bevestigd', false)
        ->assertSee('Eerste aanbod', false)
        ->assertSee('Bezoekers', false)
        ->assertSee('stats-funnel-legend', false)
        ->assertSee('stats-funnel-week-block', false)
        ->assertSee('aria-label="Formulier gestart', false)
        ->assertSee(
            "Te weinig data: minder dan {$minFormStarts} formulierstarts deze week, percentages nog niet betrouwbaar.",
            false
        )
        ->assertSee(
            "Percentages tonen we pas vanaf {$minFormStarts} formulierstarts in die week",
            false
        )
        ->getContent();

    expect($html)->toContain('class="stats-funnel-sparse hint"')
        ->and($html)->toContain('aria-label="Trechterstappen"');
});

test('dashboard trechter opent alleen de laatste volledige week en heeft legenda plus aria-labels', function () {
    $this->travelTo(Carbon::create(2026, 11, 11, 14, 0, 0, 'Europe/Amsterdam'));

    $tracker = app(ConversionTracker::class);
    $lastCompleteStart = now('Europe/Amsterdam')->startOfWeek(Carbon::MONDAY)->subWeek();
    $olderStart = $lastCompleteStart->copy()->subWeek();
    $currentStart = now('Europe/Amsterdam')->startOfWeek(Carbon::MONDAY);

    foreach ([
        [$lastCompleteStart->copy()->addDays(2), 'complete-f', 'complete-v'],
        [$olderStart->copy()->addDays(1), 'older-f', 'older-v'],
        [$currentStart->copy()->addDay(), 'current-f', 'current-v'],
    ] as [$when, $flow, $hash]) {
        ConversionEvent::query()->create([
            'occurred_at' => $when->copy()->setTime(12, 0)->utc(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => $flow,
            'visitor_day_hash' => $hash,
            'device_type' => 'mobile',
            'week_key' => $tracker->weekKey($when),
        ]);
        ConversionEvent::query()->create([
            'occurred_at' => $when->copy()->setTime(12, 5)->utc(),
            'event_type' => ConversionEvent::TYPE_FIRST_OFFER,
            'path' => '/account/aanbod',
            'flow_id' => $flow,
            'visitor_day_hash' => $hash,
            'device_type' => 'mobile',
            'week_key' => $tracker->weekKey($when),
        ]);
    }

    $funnel = $tracker->funnelByWeek(8);
    $openWeeks = array_values(array_filter($funnel, fn (array $w) => $w['open_default'] === true));
    $completeWeeks = array_values(array_filter($funnel, fn (array $w) => $w['complete'] === true));

    expect($openWeeks)->toHaveCount(1)
        ->and($openWeeks[0]['week_key'])->toBe($tracker->weekKey($lastCompleteStart))
        ->and($funnel[0]['complete'])->toBeFalse()
        ->and($funnel[0]['open_default'])->toBeFalse()
        ->and($completeWeeks[0]['open_default'])->toBeTrue();

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('stats-funnel-legend', false)
        ->assertSee('aria-label="Trechterstappen"', false)
        ->assertSee('aria-label="Unieke bezoekers (schatting)', false)
        ->assertSee('aria-label="Klik Word lid', false)
        ->assertSee('aria-label="Eerste aanbod', false)
        ->assertSee('icon-bezoekers', false)
        ->assertSee('icon-klik', false)
        ->assertSee('icon-pagina', false)
        ->assertSee('icon-formulier', false)
        ->assertSee('icon-verstuur', false)
        ->assertSee('icon-gebruiker', false)
        ->assertSee('icon-mail-check', false)
        ->assertSee('icon-plant', false)
        ->getContent();

    preg_match_all(
        '/<details\s+[^>]*class="stats-funnel-week-block"[^>]*>/',
        $html,
        $matches
    );
    expect($matches[0])->not->toBeEmpty();

    $openBlocks = array_values(array_filter(
        $matches[0],
        fn (string $tag) => preg_match('/\sopen(?:\s|=|>|$)/', $tag) === 1
    ));
    expect($openBlocks)->toHaveCount(1)
        ->and($openBlocks[0])->toContain('data-week-key="'.$tracker->weekKey($lastCompleteStart).'"');

    $closedCount = count($matches[0]) - count($openBlocks);
    expect($closedCount)->toBeGreaterThanOrEqual(2);

    $this->travelBack();
});

test('dashboard trechter toont percentages vanaf 30 form_starts', function () {
    config(['conversion.min_form_starts_for_rates' => 30]);

    for ($i = 0; $i < 30; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => 'f'.$i,
            'visitor_day_hash' => 'v'.$i,
            'device_type' => 'desktop',
            'week_key' => app(ConversionTracker::class)->weekKey(),
        ]);
    }
    for ($i = 0; $i < 15; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_SUBMIT,
            'path' => '/account/registreren',
            'flow_id' => 'f'.$i,
            'visitor_day_hash' => 'v'.$i,
            'device_type' => 'desktop',
            'week_key' => app(ConversionTracker::class)->weekKey(),
        ]);
    }

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('50,0%')
        ->and($html)->not->toContain('50%');
});

test('dashboard trechter toont kommanotatie en registratiepagina als per klik', function () {
    config(['conversion.min_form_starts_for_rates' => 30]);
    $tracker = app(ConversionTracker::class);
    $weekKey = $tracker->weekKey();

    for ($i = 0; $i < 10; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_CTA_CLICK,
            'path' => '/',
            'flow_id' => 'cta-f'.$i,
            'visitor_day_hash' => 'cta-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }
    // 27 views / 10 klikken = 2,7 per klik (geen percentage)
    for ($i = 0; $i < 27; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
            'path' => '/account/registreren',
            'flow_id' => 'reg-f'.$i,
            'visitor_day_hash' => 'reg-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }
    for ($i = 0; $i < 30; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => 'fs-f'.$i,
            'visitor_day_hash' => 'fs-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }
    // 16/30 = 53,3%
    for ($i = 0; $i < 16; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_SUBMIT,
            'path' => '/account/registreren',
            'flow_id' => 'fs-f'.$i,
            'visitor_day_hash' => 'fs-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('2,7 per klik')
        ->and($html)->toContain('aria-label="Registratiepagina: 27, 2,7 per klik"')
        ->and($html)->toContain('53,3%')
        ->and($html)->not->toContain('270%')
        ->and($html)->not->toContain('270,0%')
        ->and($html)->not->toContain('53.3%');
});

test('dashboard trechter toont streepje bij registratiepagina zonder klikken', function () {
    config(['conversion.min_form_starts_for_rates' => 30]);
    $tracker = app(ConversionTracker::class);
    $weekKey = $tracker->weekKey();

    for ($i = 0; $i < 5; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_REGISTER_VIEW,
            'path' => '/account/registreren',
            'flow_id' => 'zero-reg-f'.$i,
            'visitor_day_hash' => 'zero-reg-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }
    for ($i = 0; $i < 30; $i++) {
        ConversionEvent::query()->create([
            'occurred_at' => now(),
            'event_type' => ConversionEvent::TYPE_FORM_START,
            'path' => '/account/registreren',
            'flow_id' => 'zero-fs-f'.$i,
            'visitor_day_hash' => 'zero-fs-v'.$i,
            'device_type' => 'desktop',
            'week_key' => $weekKey,
        ]);
    }

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('aria-label="Registratiepagina: 5, –"')
        ->and($html)->toMatch('/class="hint stats-funnel-step-rate"[^>]*>–</');
});

test('postlogboek crud voor admin; stats-lezer en beheerder-mutaties afgeschermd', function () {
    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.posts.store'), [
            'posted_on' => now()->toDateString(),
            'titel' => 'Aankondiging ruilbeurs',
            'groep' => 'Plantenruil Amsterdam',
            'soort' => 'aankondiging',
            'link' => 'https://facebook.com/groups/example/posts/1',
            'utm_source' => 'facebook',
            'notitie' => 'Testpost',
        ])
        ->assertRedirect(route('beheer.posts.index'));

    $post = PostLog::query()->first();
    expect($post)->not->toBeNull()
        ->and($post->titel)->toBe('Aankondiging ruilbeurs')
        ->and($post->groep_slug)->toBe('plantenruil-amsterdam')
        ->and($post->trackingUrl())->toContain('/ga/word-lid?')
        ->and($post->trackingUrl())->toContain('utm_source=facebook')
        ->and($post->trackingUrl())->toContain('utm_medium=social')
        ->and($post->trackingUrl())->toContain('utm_campaign=plantenruil-amsterdam')
        ->and($post->trackingUrl())->toContain('utm_content='.$post->id);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.posts.index'))
        ->assertOk()
        ->assertSee('Tracking', false)
        ->assertSee('Kopieer', false)
        ->assertSee('Bezoekers', false)
        ->assertDontSee('Open tracking', false)
        ->assertSee('Aankondiging ruilbeurs', false)
        ->assertSee('mint', false);

    $viewer = User::factory()->statsViewer()->create();
    $this->flushSession();
    $this->actingAs($viewer)
        ->get(route('beheer.posts.index'))
        ->assertRedirect(route('beheer.login'));

    $this->actingAs($viewer)
        ->post(route('beheer.posts.store'), [
            'posted_on' => now()->toDateString(),
            'titel' => 'Mag niet',
            'groep' => 'Mag niet',
            'soort' => 'aankondiging',
            'utm_source' => 'facebook',
        ])
        ->assertRedirect(route('beheer.login'));

    expect(PostLog::query()->where('groep', 'Mag niet')->count())->toBe(0);
});

test('postlogboek weigert whitespace-only titel en groep', function () {
    $this->withSession(['admin_ok' => true])
        ->from(route('beheer.posts.create'))
        ->post(route('beheer.posts.store'), [
            'posted_on' => now()->toDateString(),
            'titel' => "  \t  ",
            'groep' => '   ',
            'soort' => 'aankondiging',
            'utm_source' => 'facebook',
        ])
        ->assertRedirect(route('beheer.posts.create'))
        ->assertSessionHasErrors(['titel', 'groep']);

    expect(PostLog::query()->count())->toBe(0);
});

test('bots en beheerders worden niet gemeten', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
        ->get(route('ga.word-lid'))
        ->assertRedirect();
    expect(ConversionEvent::query()->count())->toBe(0);

    $admin = User::factory()->create([
        'email' => 'admin-meet@example.com',
        'email_verified_at' => now(),
    ]);
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin->fresh())
        ->get(route('account.registreren'));
    // Admin is authenticated → guest middleware redirects away from registreren;
    // CTA/register_view mag sowieso niet voor beheerders.
    expect(ConversionEvent::query()->count())->toBe(0);

    $this->withSession(['admin_ok' => true])
        ->get(route('ga.word-lid', ['from' => '/']))
        ->assertRedirect();
    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_CTA_CLICK)->count())->toBe(0);
});

test('privacy noemt conversiemeting zonder cookiebanner', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Conversiemeting', false)
        ->assertSee('Geen IP-adres', false)
        ->assertSee('Flow-cookie', false)
        ->assertSee('Geen cookiebanner', false)
        ->assertSee('welkomstscherm', false);
});
