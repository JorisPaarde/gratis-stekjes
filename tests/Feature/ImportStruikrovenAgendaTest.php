<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Models\GeocodeCache;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'Europe/Amsterdam'));
    config(['stekjes.deploy_token' => 'test-deploy-token', 'stekjes.deploy_endpoint_enabled' => true]);

    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:Theerestraat 42, Sint-Michielsgestel')],
        ['lat' => 51.642, 'lon' => 5.359, 'label' => 'Theerestraat 42, Sint-Michielsgestel', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:Erasmusweg 1657, Den Haag')],
        ['lat' => 52.046, 'lon' => 4.278, 'label' => 'Erasmusweg 1657, Den Haag', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:Sint-Michielsgestel')],
        ['lat' => 51.641, 'lon' => 5.353, 'label' => 'Sint-Michielsgestel', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:Den Haag')],
        ['lat' => 52.070, 'lon' => 4.300, 'label' => 'Den Haag', 'country' => 'NL', 'cached_at' => now()]
    );

    Http::fake([
        '*api.pdok.nl*' => Http::response(['response' => ['docs' => []]], 200),
        '*nominatim*' => Http::response([], 200),
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function struikrovenImportFixturePath(): string
{
    $path = storage_path('app/testing/struikroven-agenda-test.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    $payload = [
        'source' => 'https://www.struikroven.nu/agenda',
        'scraped_at' => '2026-11-04',
        'acties' => [
            [
                'datum' => '2026-11-06',
                'begin_tijd' => '10:00',
                'eind_tijd' => '15:00',
                'plaats' => 'Sint-Michielsgestel',
                'adres' => 'Theerestraat 42, Sint-Michielsgestel',
                'provincie' => 'Noord-Brabant',
                'titel' => 'Struikroofactie Domein aan de Dommel',
                'omschrijving' => 'Testomschrijving Dommel.',
                'url' => 'https://www.struikroven.nu/domeindommel',
                'organisator' => 'Struikroven',
                'organisatie_slug' => 'struikroven',
                'type' => 'overig',
            ],
            [
                'datum' => '2026-11-28',
                'begin_tijd' => '13:00',
                'eind_tijd' => '16:00',
                'plaats' => 'Den Haag',
                'adres' => 'Erasmusweg 1657, Den Haag',
                'provincie' => 'Zuid-Holland',
                'titel' => 'Struikroofactie Den Haag',
                'omschrijving' => 'Testomschrijving Den Haag.',
                'url' => 'https://www.struikroven.nu/denhaag',
                'organisator' => 'Struikroven',
                'organisatie_slug' => 'struikroven',
                'type' => 'overig',
            ],
        ],
    ];

    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $path;
}

test('gp:import-struikroven-agenda dry-run schrijft niets', function () {
    $path = struikrovenImportFixturePath();

    $this->artisan('gp:import-struikroven-agenda', ['--file' => $path])
        ->expectsOutputToContain('Dry-run')
        ->expectsOutputToContain('2026-11-06 · Sint-Michielsgestel · Struikroofactie Domein aan de Dommel · nieuw')
        ->expectsOutputToContain('2026-11-28 · Den Haag · Struikroofactie Den Haag · nieuw')
        ->assertSuccessful();

    expect(Evenement::query()->count())->toBe(0);
});

test('gp:import-struikroven-agenda --apply zet Struikroven-vlag en is idempotent', function () {
    $path = struikrovenImportFixturePath();

    $this->artisan('gp:import-struikroven-agenda', [
        '--file' => $path,
        '--apply' => true,
    ])->assertSuccessful();

    expect(Evenement::query()->count())->toBe(2);

    $dommel = Evenement::query()->where('url', 'https://www.struikroven.nu/domeindommel')->first();
    expect($dommel)->not->toBeNull()
        ->and($dommel->organisatie_slug)->toBe('struikroven')
        ->and($dommel->bron)->toBe('https://www.struikroven.nu/domeindommel')
        ->and($dommel->plaats)->toBe('Sint-Michielsgestel')
        ->and($dommel->provincie)->toBe('Noord-Brabant')
        ->and($dommel->visible)->toBeTrue()
        ->and($dommel->type)->toBe('overig')
        ->and($dommel->lat)->not->toBeNull()
        ->and($dommel->lon)->not->toBeNull();

    $haag = Evenement::query()->where('url', 'https://www.struikroven.nu/denhaag')->first();
    expect($haag)->not->toBeNull()
        ->and($haag->organisatie_slug)->toBe('struikroven')
        ->and($haag->start_datum->format('Y-m-d'))->toBe('2026-11-28');

    $this->artisan('gp:import-struikroven-agenda', [
        '--file' => $path,
        '--apply' => true,
    ])
        ->expectsOutputToContain('bestaat al')
        ->assertSuccessful();

    expect(Evenement::query()->count())->toBe(2);
});

test('repo-JSON van Struikroven-agenda is geldig en bevat alleen acties vanaf 4 nov 2026', function () {
    $path = base_path('data/imports/struikroven-agenda-2026-11-04.json');
    expect(is_file($path))->toBeTrue();

    $data = json_decode((string) file_get_contents($path), true);
    expect($data)->toBeArray()
        ->and($data['acties'])->toBeArray()
        ->and(count($data['acties']))->toBeGreaterThan(0);

    foreach ($data['acties'] as $actie) {
        expect($actie['datum'])->toBeGreaterThanOrEqual('2026-11-04')
            ->and($actie['organisatie_slug'])->toBe('struikroven')
            ->and($actie['url'])->toStartWith('https://');
    }
});

test('/struikroven toont geïmporteerde Struikroven-acties via organisatie_slug', function () {
    $path = struikrovenImportFixturePath();
    $this->artisan('gp:import-struikroven-agenda', [
        '--file' => $path,
        '--apply' => true,
        '--no-geocode' => true,
    ])->assertSuccessful();

    // Andere org mag niet verschijnen.
    Evenement::query()->create([
        'titel' => 'Gewone stekjesmarkt',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-11-10',
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
        'organisator' => 'Andere',
        'organisatie_slug' => null,
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);

    $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('Struikroofactie Domein aan de Dommel', false)
        ->assertSee('Struikroofactie Den Haag', false)
        ->assertSee('https://www.struikroven.nu/domeindommel', false)
        ->assertDontSee('Gewone stekjesmarkt', false);
});

test('deploy import-struikroven-agenda endpoint dry-run en apply', function () {
    $this->postJson('/deploy/import-struikroven-agenda', ['apply' => false], [
        'Authorization' => 'Bearer test-deploy-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('apply', false);

    expect(Evenement::query()->count())->toBe(0);

    $this->postJson('/deploy/import-struikroven-agenda', ['apply' => true, 'no_geocode' => true], [
        'Authorization' => 'Bearer test-deploy-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('apply', true);

    expect(Evenement::query()->where('organisatie_slug', 'struikroven')->count())->toBe(2);

    $this->postJson('/deploy/import-struikroven-agenda')->assertForbidden();
});
