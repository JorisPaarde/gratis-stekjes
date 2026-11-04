<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Seed + HTTP-fake voor normaliseer-command (geen echte PDOK/Nominatim). */
function fakeNormaliseerGeo(): void
{
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2037')],
        ['lat' => 52.355, 'lon' => 4.667, 'label' => '2037 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('be:9000')],
        ['lat' => 51.054, 'lon' => 3.723, 'label' => '9000 Gent', 'country' => 'BE', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:3370')],
        ['lat' => 50.84, 'lon' => 4.83, 'label' => '3370 Boutersem', 'country' => 'BE', 'cached_at' => now()]
    );

    Http::fake(function (Request $request) {
        $url = $request->url();
        $q = (string) ($request['q'] ?? '');
        $fq = (string) ($request['fq'] ?? '');
        $hasPostcodeFq = str_contains($url, 'fq=type') || $fq === 'type:postcode';

        if (str_contains($url, 'reverse') || str_contains($url, '/reverse')) {
            $lat = (float) $request['lat'];
            // Haarlem-achtig
            if ($lat > 52.3 && $lat < 52.4) {
                if (str_contains($url, 'pdok') || str_contains($url, 'locatieserver')) {
                    return Http::response(['response' => ['docs' => [[
                        'woonplaatsnaam' => 'Haarlem',
                        'postcode' => '2011AB',
                        'weergavenaam' => '2011AB Haarlem',
                        'type' => 'postcode',
                    ]]]], 200);
                }

                return Http::response([
                    'lat' => $lat,
                    'lon' => $request['lon'],
                    'address' => ['postcode' => '2011', 'city' => 'Haarlem', 'country_code' => 'nl'],
                    'display_name' => 'Haarlem',
                ], 200);
            }
            // Utrecht-achtig
            if ($lat > 52.0 && $lat < 52.2) {
                if (str_contains($url, 'pdok') || str_contains($url, 'locatieserver')) {
                    return Http::response(['response' => ['docs' => [[
                        'woonplaatsnaam' => 'Utrecht',
                        'postcode' => '3511AB',
                        'weergavenaam' => '3511AB Utrecht',
                        'type' => 'postcode',
                    ]]]], 200);
                }

                return Http::response([
                    'lat' => $lat,
                    'lon' => $request['lon'],
                    'address' => ['postcode' => '3511', 'city' => 'Utrecht', 'country_code' => 'nl'],
                    'display_name' => 'Utrecht',
                ], 200);
            }

            return Http::response(['response' => ['docs' => []]], 200);
        }

        // Forward plaatsnaam Spierdijk → 1641
        if (strcasecmp($q, 'Spierdijk') === 0) {
            if ($hasPostcodeFq) {
                return Http::response(['response' => ['docs' => [
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Spierdijk', 'postcode' => '1641AA', 'weergavenaam' => 'Straat, 1641AA Spierdijk', 'centroide_ll' => 'POINT(4.941 52.656)', 'score' => 8],
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Spierdijk', 'postcode' => '1641AB', 'weergavenaam' => 'Straat, 1641AB Spierdijk', 'centroide_ll' => 'POINT(4.942 52.656)', 'score' => 8],
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Spierdijk', 'postcode' => '1643AA', 'weergavenaam' => 'Straat, 1643AA Spierdijk', 'centroide_ll' => 'POINT(4.94 52.65)', 'score' => 7],
                ]]], 200);
            }

            return Http::response(['response' => ['docs' => [[
                'type' => 'woonplaats',
                'woonplaatsnaam' => 'Spierdijk',
                'weergavenaam' => 'Spierdijk, Koggenland, Noord-Holland',
                'centroide_ll' => 'POINT(4.94089424 52.65605933)',
                'score' => 12,
            ]]]], 200);
        }

        // Forward plaatsnaam Haarlem (als resolve i.p.v. reverse)
        if (strcasecmp($q, 'Haarlem') === 0) {
            if ($hasPostcodeFq) {
                return Http::response(['response' => ['docs' => [[
                    'type' => 'postcode', 'woonplaatsnaam' => 'Haarlem', 'postcode' => '2011AB',
                    'weergavenaam' => '2011AB Haarlem', 'centroide_ll' => 'POINT(4.637 52.381)', 'score' => 9,
                ]]]], 200);
            }

            return Http::response(['response' => ['docs' => [[
                'type' => 'woonplaats', 'woonplaatsnaam' => 'Haarlem',
                'weergavenaam' => 'Haarlem, Noord-Holland', 'centroide_ll' => 'POINT(4.637 52.381)', 'score' => 12,
            ]]]], 200);
        }

        return Http::response(['response' => ['docs' => []]], 200);
    });
}

test('gp:normaliseer-locaties dry-run schrijft niets', function () {
    fakeNormaliseerGeo();

    $user = User::factory()->create([
        'country' => 'NL',
        'locatie_label' => '2037',
        'lat' => 52.355,
        'lon' => 4.667,
    ]);

    $this->artisan('gp:normaliseer-locaties', ['--dry-run' => true, '--pauze-ms' => 0])
        ->assertSuccessful();

    $user->refresh();
    expect($user->locatie_label)->toBe('2037')
        ->and($user->postcode_gebied)->toBeNull()
        ->and($user->woonplaats)->toBeNull();
});

test('gp:normaliseer-locaties categoriseert kaal postcode, plaatsnaam, half adres, twijfel', function () {
    fakeNormaliseerGeo();

    $leeg = User::factory()->create(['locatie_label' => '', 'lat' => null, 'lon' => null]);
    $kaalNl = User::factory()->create(['country' => 'NL', 'locatie_label' => '2037', 'lat' => 52.355, 'lon' => 4.667]);
    $kaalBe = User::factory()->create(['country' => 'BE', 'locatie_label' => '9000', 'lat' => 51.054, 'lon' => 3.723]);
    $plaats = User::factory()->create(['country' => 'NL', 'locatie_label' => 'Haarlem', 'lat' => 52.381, 'lon' => 4.637]);
    $half = User::factory()->create(['country' => 'NL', 'locatie_label' => 'Kerkstraat 12', 'lat' => 52.09, 'lon' => 5.12]);
    $twijfelLand = User::factory()->create(['country' => 'NL', 'locatie_label' => '3370', 'lat' => 50.84, 'lon' => 4.83]);
    $spierdijk = User::factory()->create(['country' => 'NL', 'locatie_label' => 'Spierdijk', 'lat' => null, 'lon' => null]);
    $twijfelOnbekend = User::factory()->create(['country' => 'NL', 'locatie_label' => 'Xyzonbekenddorp', 'lat' => null, 'lon' => null]);

    $this->artisan('gp:normaliseer-locaties', ['--no-dry-run' => true, '--pauze-ms' => 0])
        ->assertSuccessful();

    expect($leeg->fresh()->locatie_label)->toBe('')
        ->and($kaalNl->fresh()->locatie_label)->toBe('2037 Haarlem')
        ->and($kaalNl->fresh()->postcode_gebied)->toBe('2037')
        ->and($kaalNl->fresh()->woonplaats)->toBe('Haarlem')
        ->and($kaalBe->fresh()->locatie_label)->toBe('9000 Gent')
        ->and($plaats->fresh()->postcode_gebied)->toBe('2011')
        ->and($plaats->fresh()->woonplaats)->toBe('Haarlem')
        ->and($half->fresh()->locatie_label)->toBe('3511 Utrecht')
        ->and($twijfelLand->fresh()->locatie_label)->toBe('3370') // land komt niet overeen
        ->and($spierdijk->fresh()->locatie_label)->toBe('1641 Spierdijk')
        ->and($spierdijk->fresh()->postcode_gebied)->toBe('1641')
        ->and($twijfelOnbekend->fresh()->locatie_label)->toBe('Xyzonbekenddorp');
});

test('gp:normaliseer-locaties is idempotent', function () {
    fakeNormaliseerGeo();

    $user = User::factory()->create([
        'country' => 'NL',
        'locatie_label' => '2037 Haarlem',
        'postcode_gebied' => '2037',
        'woonplaats' => 'Haarlem',
        'lat' => 52.355,
        'lon' => 4.667,
    ]);

    $this->artisan('gp:normaliseer-locaties', ['--no-dry-run' => true, '--pauze-ms' => 0])
        ->assertSuccessful();

    expect($user->fresh()->locatie_label)->toBe('2037 Haarlem');
});

test('deploy normaliseer-locaties endpoint dry-run en apply', function () {
    config(['stekjes.deploy_token' => 'test-token', 'stekjes.deploy_endpoint_enabled' => true]);
    fakeNormaliseerGeo();

    $this->postJson('/deploy/normaliseer-locaties', ['apply' => false], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('apply', false)
        ->assertJsonStructure(['output']);

    $this->postJson('/deploy/normaliseer-locaties', ['apply' => true], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()
        ->assertJsonPath('apply', true);

    $this->postJson('/deploy/normaliseer-locaties')->assertForbidden();
});

test('geocode timeout wordt gelogd zonder persoonsgegevens', function () {
    Log::spy();
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('timed out');
    });

    $geo = app(\App\Services\PdokGeocoder::class);
    expect($geo->geocode('2037', true))->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
        return $message === 'geocode timeout/fout'
            && ($context['land'] ?? null) === 'NL'
            && ($context['invoertype'] ?? null) === 'postcode'
            && ! isset($context['email'])
            && ! isset($context['query']);
    })->atLeast()->once();
});
