<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakePdokHerberekening(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'q=9999')) {
            return Http::response('kapot', 500);
        }
        $doc = str_contains($url, 'fq=type')
            ? ['type' => 'postcode', 'weergavenaam' => 'Adlerstraat, 2037KB Haarlem', 'centroide_ll' => 'POINT(4.6668685 52.35546039)']
            : ['type' => 'woonplaats', 'weergavenaam' => 'Nederweert, Nederweert, Limburg', 'centroide_ll' => 'POINT(5.72250254 51.29561071)'];

        return Http::response(['response' => ['docs' => [$doc]]], 200);
    });
}

test('herbereken-postcodes zet lid met 2037 van Nederweert naar Haarlem, idempotent', function () {
    fakePdokHerberekening();

    $fout = User::factory()->create(['country' => 'NL', 'locatie_label' => '2037', 'lat' => 51.296, 'lon' => 5.723]);
    $plaats = User::factory()->create(['country' => 'NL', 'locatie_label' => 'Spierdijk', 'lat' => 52.656, 'lon' => 4.941]);
    $belg = User::factory()->create(['country' => 'BE', 'locatie_label' => '9000', 'lat' => 51.054, 'lon' => 3.723]);
    $kapot = User::factory()->create(['country' => 'NL', 'locatie_label' => '9999', 'lat' => 53.0, 'lon' => 6.0]);

    $this->artisan('gp:herbereken-postcodes', ['--pauze-ms' => 0])
        ->expectsOutput('Bijgewerkt: 1, overgeslagen: 1')
        ->assertSuccessful();

    expect($fout->fresh()->lat)->toBe(52.355)
        ->and($fout->fresh()->lon)->toBe(4.667)
        ->and($plaats->fresh()->lat)->toBe(52.656)
        ->and($belg->fresh()->lat)->toBe(51.054)
        ->and($kapot->fresh()->lat)->toBe(53.0);

    $this->artisan('gp:herbereken-postcodes', ['--pauze-ms' => 0])
        ->expectsOutput('Bijgewerkt: 0, overgeslagen: 1')
        ->assertSuccessful();
});

test('na herberekening staat aanbod van lid met 2037 bij zoeken op haarlem binnen 10 km', function () {
    fakePdokHerberekening();
    GeocodeCache::query()->create([
        'query_key' => 'nl,be:haarlem',
        'lat' => 52.383,
        'lon' => 4.648,
        'label' => 'Gemeente Haarlem',
        'country' => 'NL',
        'cached_at' => now(),
    ]);
    $user = User::factory()->create(['country' => 'NL', 'locatie_label' => '2037', 'lat' => 51.296, 'lon' => 5.723]);
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Pompoen',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->artisan('gp:herbereken-postcodes', ['--pauze-ms' => 0])->assertSuccessful();

    $items = $this->getJson(route('api.zoeken', ['q' => 'haarlem']))->assertOk()->json('items');
    expect($items[0]['tekst'])->toBe('Pompoen')
        ->and($items[0]['afstand_km'])->toBeLessThan(10);
});
