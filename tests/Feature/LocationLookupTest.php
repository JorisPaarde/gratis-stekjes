<?php

declare(strict_types=1);

use App\Models\GeocodeCache;
use App\Services\LocationGeocoder;
use App\Support\Geo;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * PDOK/Nominatim-nep voor de 5 prod-twijfelgevallen + regressie 2037≠Nederweert.
 * Geen echte netwerkcalls.
 */
function fakeLocationLookupHttp(): void
{
    Http::fake(function (Request $request) {
        $url = $request->url();
        $q = (string) ($request['q'] ?? '');
        $postalcode = (string) ($request['postalcode'] ?? '');
        $fq = (string) ($request['fq'] ?? '');
        $type = (string) ($request['type'] ?? '');
        $hasPostcodeFq = str_contains($url, 'fq=type') || $fq === 'type:postcode';

        // Nominatim structured BE postalcode
        if (str_contains($url, 'nominatim')) {
            if ($postalcode === '3370' || (str_contains($url, 'postalcode=3370'))) {
                return Http::response([[
                    'lat' => '50.8348270',
                    'lon' => '4.8424848',
                    'display_name' => '3370, Vertrijk, Boutersem, Leuven, Vlaams-Brabant, België',
                    'address' => [
                        'postcode' => '3370',
                        'village' => 'Vertrijk',
                        'town' => 'Boutersem',
                        'country_code' => 'be',
                    ],
                ]], 200);
            }
            if ($postalcode === '9000') {
                return Http::response([[
                    'lat' => '51.054',
                    'lon' => '3.723',
                    'display_name' => '9000, Gent, België',
                    'address' => [
                        'postcode' => '9000',
                        'city' => 'Gent',
                        'country_code' => 'be',
                    ],
                ]], 200);
            }

            return Http::response([], 200);
        }

        // PDOK reverse
        if (str_contains($url, 'reverse') || str_contains($url, '/reverse')) {
            if ($type === 'postcode' || str_contains($url, 'type=postcode')) {
                return Http::response(['response' => ['docs' => [[
                    'type' => 'postcode',
                    'woonplaatsnaam' => 'Spierdijk',
                    'weergavenaam' => 'Verlaatsweg, 1643LX Spierdijk',
                    'postcode' => '1643LX',
                    'score' => 7.5,
                ]]]], 200);
            }

            return Http::response(['response' => ['docs' => [[
                'type' => 'adres',
                'woonplaatsnaam' => 'Spierdijk',
                'weergavenaam' => 'Verlaatsweg 44A, Spierdijk',
                'score' => 7.2,
            ]]]], 200);
        }

        // PDOK free — postcode-zoekopdrachten (fq=type:postcode)
        if ($hasPostcodeFq) {
            // Zonder fq zou 2037 → Nederweert winnen (regressie 0.58.2).
            if (preg_match('/^203[37]/', $q) || str_starts_with($q, '2037') || str_starts_with($q, '2033')) {
                $pc = str_starts_with($q, '2033') ? '2033' : '2037';

                return Http::response(['response' => ['docs' => [
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Haarlem', 'weergavenaam' => "Adlerstraat, {$pc}KB Haarlem", 'postcode' => "{$pc}KB", 'centroide_ll' => 'POINT(4.6668685 52.35546039)', 'score' => 5.6],
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Haarlem', 'weergavenaam' => "Andalusië, {$pc}AT Haarlem", 'postcode' => "{$pc}AT", 'centroide_ll' => 'POINT(4.65309266 52.35930085)', 'score' => 5.6],
                ]]], 200);
            }
            if (str_starts_with($q, '5688')) {
                return Http::response(['response' => ['docs' => [
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Oirschot', 'weergavenaam' => 'Achtbundersedijk, 5688LM Oirschot', 'postcode' => '5688LM', 'centroide_ll' => 'POINT(5.313 51.505)', 'score' => 5.5],
                    ['type' => 'postcode', 'woonplaatsnaam' => 'Oirschot', 'weergavenaam' => 'Akkerweg, 5688NS Oirschot', 'postcode' => '5688NS', 'centroide_ll' => 'POINT(5.314 51.506)', 'score' => 5.5],
                ]]], 200);
            }
            // Plaatsnaam → postcodes (Spierdijk: 1641 dominant)
            if (strcasecmp($q, 'Spierdijk') === 0) {
                $docs = [];
                for ($i = 0; $i < 27; $i++) {
                    $docs[] = ['type' => 'postcode', 'woonplaatsnaam' => 'Spierdijk', 'postcode' => '1641AA', 'weergavenaam' => 'Straat, 1641AA Spierdijk', 'centroide_ll' => 'POINT(4.941 52.656)', 'score' => 7.7];
                }
                for ($i = 0; $i < 12; $i++) {
                    $docs[] = ['type' => 'postcode', 'woonplaatsnaam' => 'Spierdijk', 'postcode' => '1643AA', 'weergavenaam' => 'Straat, 1643AA Spierdijk', 'centroide_ll' => 'POINT(4.94 52.65)', 'score' => 7.0];
                }

                return Http::response(['response' => ['docs' => $docs]], 200);
            }

            return Http::response(['response' => ['docs' => []]], 200);
        }

        // PDOK free zonder fq — woonplaats/algemeen
        if (strcasecmp($q, 'Spierdijk') === 0) {
            return Http::response(['response' => ['docs' => [[
                'type' => 'woonplaats',
                'woonplaatsnaam' => 'Spierdijk',
                'weergavenaam' => 'Spierdijk, Koggenland, Noord-Holland',
                'centroide_ll' => 'POINT(4.94089424 52.65605933)',
                'score' => 12.4,
            ]]]], 200);
        }
        // Regressie: kale 2037 zonder fq zou Nederweert geven — fake bewijst dat we fq gebruiken.
        if ($q === '2037' || $q === '2033') {
            return Http::response(['response' => ['docs' => [[
                'type' => 'woonplaats',
                'woonplaatsnaam' => 'Nederweert',
                'weergavenaam' => 'Nederweert, Nederweert, Limburg',
                'centroide_ll' => 'POINT(5.72250254 51.29561071)',
                'score' => 10,
            ]]]], 200);
        }

        return Http::response(['response' => ['docs' => []]], 200);
    });
}

test('NL kale PC4 2033 en 2037 → Haarlem via PDOK fq=type:postcode', function (string $pc) {
    fakeLocationLookupHttp();
    $geo = app(LocationGeocoder::class);

    $hit = $geo->resolve($pc, 'NL', true);

    expect($hit['ok'])->toBeTrue()
        ->and($hit['woonplaats'])->toBe('Haarlem')
        ->and($hit['postcode'])->toBe($pc)
        ->and($hit['label'])->toBe($pc.' Haarlem')
        ->and($hit['lat'])->toBe(52.355)
        ->and($hit['lon'])->toBe(4.667);

    Http::assertSent(function (Request $request) use ($pc) {
        return str_contains($request->url(), 'locatieserver')
            && (($request['fq'] ?? null) === 'type:postcode' || str_contains($request->url(), 'fq=type'))
            && str_starts_with((string) ($request['q'] ?? ''), $pc);
    });
})->with(['2033', '2037']);

test('NL kale PC4 5688 → Oirschot', function () {
    fakeLocationLookupHttp();
    $hit = app(LocationGeocoder::class)->resolve('5688', 'NL', true);

    expect($hit['ok'])->toBeTrue()
        ->and($hit['woonplaats'])->toBe('Oirschot')
        ->and($hit['postcode'])->toBe('5688')
        ->and($hit['label'])->toBe('5688 Oirschot');
});

test('BE 3370 → Boutersem via gestructureerde Nominatim (niet Vertrijk)', function () {
    fakeLocationLookupHttp();
    $hit = app(LocationGeocoder::class)->resolve('3370', 'BE', true);

    expect($hit['ok'])->toBeTrue()
        ->and($hit['woonplaats'])->toBe('Boutersem')
        ->and($hit['postcode'])->toBe('3370')
        ->and($hit['label'])->toBe('3370 Boutersem')
        ->and($hit['country'])->toBe('BE');

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'nominatim')) {
            return false;
        }
        $ua = $request->header('User-Agent')[0] ?? '';

        return ($request['postalcode'] ?? null) === '3370'
            && isset($request['country'])
            && ! isset($request['q'])
            && str_contains($ua, 'GratisPlanten/');
    });
});

test('NL plaatsnaam Spierdijk → postcodegebied 1641', function () {
    fakeLocationLookupHttp();
    $hit = app(LocationGeocoder::class)->resolve('Spierdijk', 'NL', true);

    expect($hit['ok'])->toBeTrue()
        ->and($hit['woonplaats'])->toBe('Spierdijk')
        ->and($hit['postcode'])->toBe('1641')
        ->and($hit['label'])->toBe('1641 Spierdijk');
});

test('incomplete postcode-cache zonder woonplaats wordt genegeerd en opnieuw opgehaald', function () {
    fakeLocationLookupHttp();
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('nl:2037')],
        ['lat' => 51.296, 'lon' => 5.723, 'label' => '2037', 'country' => 'NL', 'cached_at' => now()]
    );
    GeocodeCache::query()->updateOrCreate(
        ['query_key' => Geo::normalizeQuery('2037')],
        ['lat' => 51.296, 'lon' => 5.723, 'label' => '2037', 'country' => 'NL', 'cached_at' => now()]
    );

    $hit = app(LocationGeocoder::class)->resolve('2037', 'NL', true);

    expect($hit['ok'])->toBeTrue()
        ->and($hit['woonplaats'])->toBe('Haarlem')
        ->and($hit['label'])->toBe('2037 Haarlem')
        ->and($hit['lat'])->toBe(52.355);
});

test('PDOK postcode-zoekopdracht gebruikt fq zodat 2037 niet Nederweert wordt', function () {
    fakeLocationLookupHttp();
    $hit = app(LocationGeocoder::class)->resolve('2037', 'NL', true);

    expect($hit['woonplaats'])->toBe('Haarlem')
        ->and($hit['lat'])->not->toBe(51.296);
});
