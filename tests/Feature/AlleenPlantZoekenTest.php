<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function plantLid(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

function plantAanbod(User $user, string $tekst): Aanbod
{
    return Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
}

/**
 * Dichtbij Haarlem (ouder), ver weg in Groningen (nieuwer), en een niet-passende plant.
 *
 * @return array{near: User, far: User}
 */
function plantScenario(): array
{
    Http::fake(); // geen netwerk: alleen-plant mag niet geocoderen
    $near = plantLid(['name' => 'Nina', 'locatie_label' => '2037', 'lat' => 52.355, 'lon' => 4.667]);
    $far = plantLid(['name' => 'Vera', 'locatie_label' => '9711', 'lat' => 53.219, 'lon' => 6.567]);
    plantAanbod($near, 'Pompoen dichtbij');
    plantAanbod($near, 'Tomaat dichtbij');
    plantAanbod($far, 'Pompoen ver weg');

    return ['near' => $near, 'far' => $far];
}

test('alleen 2e veld, ingelogd met adres: alleen plant, dichtstbij eigen adres eerst, geen eigen coördinaten', function () {
    plantScenario();
    $zoeker = plantLid(['name' => 'Joris', 'locatie_label' => '2011', 'lat' => 52.3817, 'lon' => 4.6423]);

    $response = $this->actingAs($zoeker)->getJson(route('api.zoeken', ['plant' => 'pompoen']))->assertOk();

    $items = $response->json('items');
    expect(array_column($items, 'tekst'))->toBe(['Pompoen dichtbij', 'Pompoen ver weg'])
        ->and($items[0]['afstand_km'])->toBeLessThan(5)
        ->and($items[1]['afstand_km'])->toBeGreaterThan(100)
        ->and($response->json('meta.modus'))->toBe('plant')
        ->and($response->json('meta.sortering'))->toBe('afstand')
        ->and($response->json('meta'))->not->toHaveKey('zoek_lat')
        ->and($response->json('meta'))->not->toHaveKey('zoek_lon')
        ->and($response->json('meta'))->not->toHaveKey('lat')
        ->and($response->json('meta'))->not->toHaveKey('lon');

    $json = $response->getContent();
    expect($json)->not->toContain('52.3817')
        ->and($json)->not->toContain('4.6423')
        ->and($json)->not->toContain('52.382')
        ->and($json)->not->toContain('4.642')
        ->and($json)->not->toContain('"email"');
    Http::assertNothingSent();
});

test('alleen 2e veld als gast: fallback nieuwste eerst, zonder afstand', function () {
    plantScenario();

    $response = $this->getJson(route('api.zoeken', ['plant' => 'pompoen']))->assertOk();

    $items = $response->json('items');
    expect(array_column($items, 'tekst'))->toBe(['Pompoen ver weg', 'Pompoen dichtbij'])
        ->and($items[0])->not->toHaveKey('afstand_km')
        ->and($response->json('meta.sortering'))->toBe('recent')
        ->and($response->json('meta.reden'))->toBe('niet_ingelogd');
});

test('alleen 2e veld, ingelogd zonder adres: fallback nieuwste eerst met reden geen_adres', function () {
    plantScenario();
    $zonderAdres = plantLid(['lat' => null, 'lon' => null, 'locatie_label' => '']);

    $response = $this->actingAs($zonderAdres)->getJson(route('api.zoeken', ['plant' => 'pompoen']))->assertOk();

    expect(array_column($response->json('items'), 'tekst'))->toBe(['Pompoen ver weg', 'Pompoen dichtbij'])
        ->and($response->json('meta.sortering'))->toBe('recent')
        ->and($response->json('meta.reden'))->toBe('geen_adres');
});

test('aanbodpagina met alleen plant: lijst op afstand vanaf eigen adres, zonder JS, zonder coördinaten in HTML', function () {
    plantScenario();
    $zoeker = plantLid(['name' => 'Joris', 'lat' => 52.3817, 'lon' => 4.6423]);

    $html = $this->actingAs($zoeker)->get(route('aanbod', ['plant' => 'pompoen']))->assertOk()->getContent();

    expect($html)->toContain('dichtstbij jouw adres')
        ->and($html)->toContain('id="plant-aanbod"')
        ->and(strpos($html, 'Pompoen dichtbij'))->toBeLessThan(strpos($html, 'Pompoen ver weg'))
        ->and($html)->not->toContain('Tomaat dichtbij')
        ->and($html)->toMatch('/\d,\d km/')
        ->and($html)->not->toContain('52.3817')
        ->and($html)->not->toContain('4.6423');
});

test('aanbodpagina met alleen plant als gast: nieuwste eerst met inloghint', function () {
    plantScenario();

    $html = $this->get(route('aanbod', ['plant' => 'pompoen']))->assertOk()->getContent();

    expect($html)->toContain('nieuwste eerst')
        ->and($html)->toContain(route('account.inloggen'))
        ->and(strpos($html, 'Pompoen ver weg'))->toBeLessThan(strpos($html, 'Pompoen dichtbij'));
});

test('1e veld is niet meer verplicht, zodat alleen het 2e veld kan', function () {
    $html = $this->get(route('aanbod'))->assertOk()->getContent();

    expect($html)->toMatch('/<input[^>]*id="zoek-q"[^>]*>/')
        ->and(preg_match('/<input[^>]*id="zoek-q"[^>]*\srequired/', $html))->toBe(0);
});

test('bestaand gedrag blijft: beide leeg 400, 1e veld + plant op afstand vanaf de zoekplek', function () {
    plantScenario();
    GeocodeCache::query()->create([
        'query_key' => 'haarlem',
        'lat' => 52.383,
        'lon' => 4.648,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);
    GeocodeCache::query()->create([
        'query_key' => 'nl,be:haarlem',
        'lat' => 52.383,
        'lon' => 4.648,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);

    $this->getJson(route('api.zoeken'))->assertStatus(400);

    $beide = $this->getJson(route('api.zoeken', ['q' => 'haarlem', 'plant' => 'pompoen']))->assertOk();
    expect(array_column($beide->json('items'), 'tekst'))->toBe(['Pompoen dichtbij', 'Pompoen ver weg'])
        ->and($beide->json('meta.zoek_label'))->toBe('Haarlem');

    $alleenQ = $this->getJson(route('api.zoeken', ['q' => 'haarlem']))->assertOk();
    expect($alleenQ->json('items.0.locatie_label'))->toBe('2037')
        ->and($alleenQ->json('items.0.afstand_km'))->toBeLessThan(10)
        ->and($alleenQ->json('items'))->toHaveCount(3);

    $this->get(route('aanbod', ['q' => 'haarlem', 'plant' => 'pompoen']))
        ->assertOk()
        ->assertSee('(alle plaatsen)', false);
});

test('zoeken.js: alleen 2e veld zoekt op plant via de API, 1e veld blijft zoek(q)', function () {
    $js = (string) file_get_contents(base_path('resources/js/zoeken.js'));

    expect($js)->toContain('function zoekOpPlant(')
        ->and($js)->toContain('api + "?plant=" + encodeURIComponent(plant)')
        ->and($js)->toContain('plantModus')
        ->and($js)->toContain('zoek(startQ.trim())')
        ->and($js)->not->toContain('zoek_lat: meta.lat');
});
