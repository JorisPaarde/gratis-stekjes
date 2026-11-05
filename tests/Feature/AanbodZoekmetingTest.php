<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\ConversionEvent;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\ConversionTracker;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl,be:haarlem')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => 'Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
});

function zoekmetingLid(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Zoeker',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
        'email_verified_at' => now(),
    ], $extra));
}

function zoekmetingAanbod(User $user, string $tekst): Aanbod
{
    return Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
}

test('aanbod_search staat niet in FUNNEL_TYPES', function () {
    expect(ConversionEvent::FUNNEL_TYPES)->not->toContain(ConversionEvent::TYPE_AANBOD_SEARCH)
        ->and(ConversionEvent::TYPE_AANBOD_SEARCH)->toBe('aanbod_search');
});

test('api zoeken logt aanbod_search zonder ruwe q', function () {
    $lid = zoekmetingLid();
    zoekmetingAanbod($lid, 'Monstera stekje');

    $this->getJson(route('api.zoeken', ['q' => 'Haarlem']))
        ->assertOk();

    $row = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->first();
    expect($row)->not->toBeNull()
        ->and($row->field_name)->toBeNull()
        ->and($row->error_code)->toBe('locatie.alle.1-5.ok');

    $payload = json_encode($row->toArray());
    expect($payload)->not->toContain('Haarlem')
        ->and($payload)->not->toContain('haarlem');
});

test('api zoeken logt catalogusplant via resolveExact, nooit vrije tekst', function () {
    $lid = zoekmetingLid();
    zoekmetingAanbod($lid, 'Monstera stekje groot');

    $this->getJson(route('api.zoeken', ['plant' => 'Gatenplant']))
        ->assertOk();

    $row = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->first();
    expect($row)->not->toBeNull()
        ->and($row->field_name)->toBe('Monstera')
        ->and($row->error_code)->toStartWith('plant.alle.')
        ->and($row->error_code)->toEndWith('.na');

    $payload = json_encode($row->toArray());
    expect($payload)->not->toContain('Gatenplant');
});

test('onbekende planttekst wordt niet opgeslagen', function () {
    $this->getJson(route('api.zoeken', ['plant' => 'xyzzy-niet-bestaande-soort-42']))
        ->assertOk();

    $row = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->first();
    expect($row)->not->toBeNull()
        ->and($row->field_name)->toBeNull()
        ->and($row->error_code)->toBe('plant.alle.0.na');

    $payload = json_encode($row->toArray());
    expect($payload)->not->toContain('xyzzy')
        ->and($payload)->not->toContain('niet-bestaande');
});

test('beide modus en typefilter worden gelogd', function () {
    $lid = zoekmetingLid();
    zoekmetingAanbod($lid, 'Monstera');

    $this->getJson(route('api.zoeken', [
        'q' => 'Haarlem',
        'plant' => 'Monstera',
        'type' => 'uitdelen',
    ]))->assertOk();

    $row = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->first();
    expect($row)->not->toBeNull()
        ->and($row->field_name)->toBe('Monstera')
        ->and($row->error_code)->toStartWith('beide.uitdelen.');
});

test('geocode_miss wordt gemarkeerd zonder q-tekst', function () {
    $this->getJson(route('api.zoeken', ['q' => 'OnbestaandePlaatsXYZ999']))
        ->assertOk();

    $row = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->first();
    expect($row)->not->toBeNull()
        ->and($row->error_code)->toEndWith('.miss')
        ->and($row->error_code)->toStartWith('locatie.alle.');

    $payload = json_encode($row->toArray());
    expect($payload)->not->toContain('OnbestaandePlaatsXYZ999');
});

test('recent-only listing logt geen aanbod_search', function () {
    $this->getJson(route('api.zoeken', ['recent' => 1]))->assertOk();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->count())->toBe(0);
});

test('bots worden niet gemeten op api zoeken', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
        ->getJson(route('api.zoeken', ['plant' => 'Monstera']))
        ->assertOk();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->count())->toBe(0);
});

test('ssr plant-url logt aanbod_search eenmaal', function () {
    $lid = zoekmetingLid();
    zoekmetingAanbod($lid, 'Munt stekje');

    $this->get(route('aanbod', ['plant' => 'Munt']))->assertOk();

    $rows = ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->field_name)->toBe('Munt')
        ->and($rows[0]->error_code)->toStartWith('plant.alle.');
});

test('ssr kale aanbodpagina zonder plant/q logt niets', function () {
    $this->get(route('aanbod'))->assertOk();

    expect(ConversionEvent::query()->where('event_type', ConversionEvent::TYPE_AANBOD_SEARCH)->count())->toBe(0);
});

test('statistieken toont top-10 met lege resultaten', function () {
    config(['stekjes.admin_password' => implode('-', ['TestOnly', 'Stats', 'Beheer', '9'])]);

    $tracker = app(ConversionTracker::class);

    // Drie Monstera-zoeken waarvan twee leeg; één Tomaat met treffer.
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_AANBOD_SEARCH,
        'path' => '/api/zoeken',
        'field_name' => 'Monstera',
        'error_code' => 'plant.alle.0.na',
        'week_key' => $tracker->weekKey(now()),
        'device_type' => 'desktop',
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_AANBOD_SEARCH,
        'path' => '/api/zoeken',
        'field_name' => 'Monstera',
        'error_code' => 'plant.alle.0.na',
        'week_key' => $tracker->weekKey(now()),
        'device_type' => 'desktop',
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_AANBOD_SEARCH,
        'path' => '/api/zoeken',
        'field_name' => 'Monstera',
        'error_code' => 'plant.alle.1-5.na',
        'week_key' => $tracker->weekKey(now()),
        'device_type' => 'desktop',
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_AANBOD_SEARCH,
        'path' => '/api/zoeken',
        'field_name' => 'Tomaat',
        'error_code' => 'plant.alle.1-5.na',
        'week_key' => $tracker->weekKey(now()),
        'device_type' => 'desktop',
    ]);
    ConversionEvent::query()->create([
        'occurred_at' => now(),
        'event_type' => ConversionEvent::TYPE_AANBOD_SEARCH,
        'path' => '/api/zoeken',
        'field_name' => null,
        'error_code' => 'locatie.alle.6-20.ok',
        'week_key' => $tracker->weekKey(now()),
        'device_type' => 'desktop',
    ]);

    $summary = $tracker->aanbodSearchWeekSummary(0);
    expect($summary['total'])->toBe(5)
        ->and($summary['empty_count'])->toBe(2)
        ->and($summary['modus']['plant'])->toBe(4)
        ->and($summary['modus']['locatie'])->toBe(1);

    $top = $tracker->topAanbodSearchPlants(10, 0);
    expect($top[0]['naam'])->toBe('Monstera')
        ->and($top[0]['count'])->toBe(3)
        ->and($top[0]['empty_count'])->toBe(2)
        ->and($top[0]['empty_pct'])->toBe(66.7)
        ->and($top[1]['naam'])->toBe('Tomaat')
        ->and($top[1]['empty_count'])->toBe(0);

    $html = $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Aanbodzoeken', false)
        ->assertSee('Top 10 gezochte planten', false)
        ->assertSee('Monstera', false)
        ->getContent();

    expect($html)->toContain('66.7%');
});

test('privacy noemt aanbodzoeken zonder plaats- of vrijetekst', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Conversiemeting', false)
        ->assertSee('aanbodlijst zocht', false)
        ->assertSee('nooit de getypte plaats/postcode', false)
        ->assertSee('nooit vrije planttekst', false);
});

test('resultaatbuckets kloppen', function () {
    expect(ConversionTracker::aanbodResultBucket(0))->toBe('0')
        ->and(ConversionTracker::aanbodResultBucket(1))->toBe('1-5')
        ->and(ConversionTracker::aanbodResultBucket(5))->toBe('1-5')
        ->and(ConversionTracker::aanbodResultBucket(6))->toBe('6-20')
        ->and(ConversionTracker::aanbodResultBucket(20))->toBe('6-20')
        ->and(ConversionTracker::aanbodResultBucket(21))->toBe('21+');
});
