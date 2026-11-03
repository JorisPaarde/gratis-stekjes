<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** PDOK-nep: zonder fq wint woonplaats Nederweert (code 2037), met fq=type:postcode Haarlem. */
function fakePdok2037(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'nominatim')) {
            return Http::response([], 200);
        }
        $doc = str_contains($url, 'fq=type')
            ? ['type' => 'postcode', 'weergavenaam' => 'Adlerstraat, 2037KB Haarlem', 'centroide_ll' => 'POINT(4.6668685 52.35546039)']
            : ['type' => 'woonplaats', 'weergavenaam' => 'Nederweert, Nederweert, Limburg', 'centroide_ll' => 'POINT(5.72250254 51.29561071)'];

        return Http::response(['response' => ['docs' => [$doc]]], 200);
    });
}

test('NL-postcode 2037 geocodeert naar Haarlem, niet naar woonplaats Nederweert', function (string $invoer) {
    fakePdok2037();

    $user = User::factory()->create();
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie' => $invoer,
            'telefoon' => '',
        ])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locatie_label)->toBe('2037')
        ->and($user->lat)->toBe(52.355)
        ->and($user->lon)->toBe(4.667);
})->with(['2037', '2037 KB']);

test('aanbod van lid met postcode 2037 staat bovenaan bij zoeken op haarlem', function () {
    fakePdok2037();
    GeocodeCache::query()->create([
        'query_key' => 'nl,be:haarlem',
        'lat' => 52.383,
        'lon' => 4.648,
        'label' => 'Gemeente Haarlem',
        'country' => 'NL',
        'cached_at' => now(),
    ]);

    $user = User::factory()->create(['telefoon' => '']);
    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie' => '2037',
            'telefoon' => '',
        ])
        ->assertSessionHasNoErrors();

    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Sierappel',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $items = $this->getJson(route('api.zoeken', ['q' => 'haarlem']))
        ->assertOk()
        ->json('items');

    expect($items)->toHaveCount(1)
        ->and($items[0]['tekst'])->toBe('Sierappel')
        ->and($items[0]['afstand_km'])->toBeLessThan(10);
});

test('migratie ruimt foute postcode-cache op en laat plaatsnamen staan', function () {
    foreach (['2037', 'nl:2037', 'nl,be:2037 kb', 'nl,be:haarlem'] as $key) {
        GeocodeCache::query()->create([
            'query_key' => $key,
            'lat' => 51.296,
            'lon' => 5.723,
            'label' => '2037',
            'country' => 'NL',
            'cached_at' => now(),
        ]);
    }

    $migration = require database_path('migrations/2026_11_03_130000_purge_postcode_geocode_cache.php');
    $migration->up();

    expect(GeocodeCache::query()->pluck('query_key')->all())->toBe(['nl,be:haarlem']);
});
