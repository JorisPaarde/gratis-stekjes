<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Services\Evenementen;
use Database\Seeders\DeelplekkenSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'Europe/Amsterdam'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function kaartEvenement(array $overrides = []): Evenement
{
    return Evenement::query()->create(array_merge([
        'titel' => 'Test bomenhub',
        'type' => 'bomenhub',
        'start_datum' => '2026-11-21',
        'eind_datum' => null,
        'start_tijd' => '10:00:00',
        'eind_tijd' => '16:00:00',
        'plaats' => 'Amersfoort',
        'adres' => 'Heiligenbergerweg 151, Amersfoort',
        'lat' => 52.155,
        'lon' => 5.388,
        'provincie' => 'Utrecht',
        'organisator' => 'Meer Bomen Nu',
        'omschrijving' => 'Gratis boompjes en struiken van Meer Bomen Nu, ophalen na aanmelding.',
        'url' => 'https://example.org/amersfoort',
        'bron' => 'https://example.org/amersfoort',
        'gecontroleerd_op' => '2026-11-16',
        'visible' => true,
    ], $overrides));
}

test('scopeOpKaart: voorbij verdwijnt, >1 maand vooruit verdwijnt, lopend blijft, zonder eind_datum op start_datum', function () {
    $past = kaartEvenement([
        'titel' => 'Voorbij',
        'start_datum' => '2026-11-10',
        'url' => 'https://example.org/past',
        'bron' => 'https://example.org/past',
    ]);
    $far = kaartEvenement([
        'titel' => 'Te ver',
        'start_datum' => '2026-12-20',
        'url' => 'https://example.org/far',
        'bron' => 'https://example.org/far',
    ]);
    $ok = kaartEvenement([
        'titel' => 'Binnen venster',
        'start_datum' => '2026-11-21',
        'url' => 'https://example.org/ok',
        'bron' => 'https://example.org/ok',
    ]);
    $lopend = kaartEvenement([
        'titel' => 'Lopend meerdaags',
        'start_datum' => '2026-11-14',
        'eind_datum' => '2026-11-18',
        'url' => 'https://example.org/lopend',
        'bron' => 'https://example.org/lopend',
    ]);
    $opStart = kaartEvenement([
        'titel' => 'Alleen vandaag',
        'start_datum' => '2026-11-16',
        'eind_datum' => null,
        'url' => 'https://example.org/vandaag',
        'bron' => 'https://example.org/vandaag',
    ]);
    $hidden = kaartEvenement([
        'titel' => 'Verborgen',
        'visible' => false,
        'url' => 'https://example.org/hidden',
        'bron' => 'https://example.org/hidden',
    ]);
    $geenCoords = kaartEvenement([
        'titel' => 'Zonder coords',
        'lat' => null,
        'lon' => null,
        'url' => 'https://example.org/nocoords',
        'bron' => 'https://example.org/nocoords',
    ]);

    $ids = Evenement::query()->opKaart()->pluck('id')->all();
    expect($ids)->toContain($ok->id)
        ->and($ids)->toContain($lopend->id)
        ->and($ids)->toContain($opStart->id)
        ->and($ids)->not->toContain($past->id)
        ->and($ids)->not->toContain($far->id)
        ->and($ids)->not->toContain($hidden->id)
        ->and($ids)->not->toContain($geenCoords->id);
});

test('API deelplekken voegt tijdelijke evenementen toe zonder bestaande velden te breken', function () {
    $this->seed(DeelplekkenSeeder::class);
    kaartEvenement();

    $response = $this->getJson(route('api.deelplekken'))->assertOk();
    $items = collect($response->json('items'));
    expect($items->firstWhere('soort', 'deelplek'))->not->toBeNull();

    $event = $items->firstWhere('soort', 'evenement');
    expect($event)->not->toBeNull()
        ->and($event['kind'])->toBe('evenement')
        ->and($event['tijdelijk'])->toBeTrue()
        ->and($event['start_datum'])->toBe('2026-11-21')
        ->and($event['start_tijd'])->toBe('10:00')
        ->and($event['eind_tijd'])->toBe('16:00')
        ->and($event['datum_label'])->toContain('21')
        ->and($event['datum_label'])->toContain('10.00')
        ->and($event['adres'])->toContain('Heiligenbergerweg')
        ->and($event)->toHaveKeys(['naam', 'type', 'lat', 'lng', 'lon', 'bron_url']);

    // Met typefilter: geen evenementen mengen.
    $filtered = collect($this->getJson(route('api.deelplekken', ['type' => 'plantenbieb']))->json('items'));
    expect($filtered->where('soort', 'evenement'))->toBeEmpty();

    // Chip «Evenementen»: alleen evenementen.
    $alleen = collect($this->getJson(route('api.deelplekken', ['type' => 'uitdeeldagen']))->json('items'));
    expect($alleen)->not->toBeEmpty()
        ->and($alleen->every(fn ($item) => ($item['soort'] ?? null) === 'evenement'))->toBeTrue();
});

test('deelplekkenpagina toont filterchip Evenementen en filtert de lijst', function () {
    $this->seed(DeelplekkenSeeder::class);
    kaartEvenement(['titel' => 'Chip-test uitdeeldag']);

    $this->get(route('deelplekken'))
        ->assertOk()
        ->assertSee('Evenementen', false)
        ->assertSee('type=uitdeeldagen', false);

    $this->get(route('deelplekken', ['type' => 'uitdeeldagen']))
        ->assertOk()
        ->assertSee('Chip-test uitdeeldag', false)
        ->assertSee('badge--tijdelijk', false)
        ->assertDontSee('Plantenasiel Zeist — De Boswerf', false);
});

test('formatDateTimeLabel toont tijd alleen als die er is', function () {
    $with = kaartEvenement(['start_tijd' => '09:00:00', 'eind_tijd' => '15:00:00', 'start_datum' => '2026-12-12']);
    $without = kaartEvenement([
        'titel' => 'Zonder tijd',
        'start_tijd' => null,
        'eind_tijd' => null,
        'start_datum' => '2026-12-12',
        'url' => 'https://example.org/notime',
        'bron' => 'https://example.org/notime',
    ]);

    $svc = app(Evenementen::class);
    expect($svc->formatDateTimeLabel($with))->toBe('za 12 dec, 09.00–15.00')
        ->and($svc->formatDateTimeLabel($without))->toBe('za 12 dec');
});

test('agenda toont start- en eindtijd', function () {
    kaartEvenement(['start_datum' => '2026-12-12', 'start_tijd' => '10:00:00', 'eind_tijd' => '16:00:00']);

    $this->get(route('agenda'))
        ->assertOk()
        ->assertSee('za 12 dec, 10.00–16.00', false);
});

test('import meerbomen-uitdeeldagen is idempotent op url+datum', function () {
    $path = base_path('data/imports/meerbomen-uitdeeldagen-2026-11-16.json');
    expect(is_file($path))->toBeTrue();

    $this->artisan('gp:import-meerbomen-uitdeeldagen', ['--apply' => true, '--file' => $path])
        ->assertSuccessful();
    $count = Evenement::query()->where('type', 'bomenhub')->count();
    expect($count)->toBe(24);

    $this->artisan('gp:import-meerbomen-uitdeeldagen', ['--apply' => true, '--file' => $path])
        ->assertSuccessful();
    expect(Evenement::query()->where('type', 'bomenhub')->count())->toBe(24);

    $schaarsbergen = Evenement::query()
        ->where('type', 'bomenhub')
        ->where('plaats', 'Arnhem')
        ->whereDate('start_datum', '2026-11-21')
        ->orWhere(function ($q) {
            $q->where('type', 'bomenhub')
                ->whereDate('start_datum', '2026-11-28')
                ->where('url', 'like', '%schaarsbergen%');
        })
        ->count();
    // Twee Schaarsbergen-data.
    expect(
        Evenement::query()->where('type', 'bomenhub')
            ->where(function ($q) {
                $q->where('url', 'like', '%schaarsbergen%')
                    ->orWhere('titel', 'like', '%Schaarsbergen%');
            })->count()
    )->toBe(2);

    $opKaart = Evenement::query()->opKaart()->where('type', 'bomenhub')->count();
    expect($opKaart)->toBe(23); // Middenmeer 19 dec valt buiten venster op 16 nov
});
