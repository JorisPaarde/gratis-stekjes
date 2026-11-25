<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\GeocodeCache;
use App\Models\User;
use App\Services\ZoekIntent;
use Illuminate\Support\Facades\Http;

function heroLid(): User
{
    return User::factory()->create([
        'name' => 'Sam',
        'locatie_label' => '2037',
        'lat' => 52.355,
        'lon' => 4.667,
        'telefoon' => '',
    ]);
}

function heroAanbod(User $user, string $tekst, ?int $soortId = null): Aanbod
{
    return Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'plantensoort_id' => $soortId,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
}

function heroCache(string $key, string $label, float $lat, float $lon): void
{
    GeocodeCache::query()->create([
        'query_key' => $key,
        'lat' => $lat,
        'lon' => $lon,
        'label' => $label,
        'cached_at' => now(),
    ]);
}

test('hero heeft één veld voor plaats, postcode of plant', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/<form class="hero-zoek".*?<\/form>/s', $html, $form);

    expect($form[0])->toContain('name="zoek"')
        ->and($form[0])->toContain('placeholder="Plaats, postcode of plant"')
        ->and($form[0])->toContain('Wat of waar zoek je?')
        ->and($form[0])->toContain('monstera Haarlem')
        ->and($form[0])->not->toContain('name="q"')
        ->and($form[0])->not->toContain('postal-code');
});

test('postcode en plaats gaan naar ?q=', function (string $invoer) {
    Http::fake();
    $this->get(route('aanbod', ['zoek' => $invoer]))
        ->assertRedirect(route('aanbod', ['q' => $invoer]));
    Http::assertNothingSent();
})->with(['8102', '8102 AB', 'Haarlem', 'Den Helder']);

test('plantnaam, volksnaam en Latijnse naam gaan naar ?plant=', function (string $invoer) {
    Http::fake();
    $this->get(route('aanbod', ['zoek' => $invoer]))
        ->assertRedirect(route('aanbod', ['plant' => $invoer]));
    Http::assertNothingSent();
})->with(['monstera', 'Gatenplant', 'monstera deliciosa']);

test('woord uit het aanbod (vetplant) wordt als plant herkend', function () {
    Http::fake();
    heroAanbod(heroLid(), 'Te geef vetplant');

    $this->get(route('aanbod', ['zoek' => 'vetplant']))
        ->assertRedirect(route('aanbod', ['plant' => 'vetplant']));
    Http::assertNothingSent();
});

test('plant plus plaats, in beide volgordes en met verbindingswoord', function (string $invoer, string $plant, string $plaats) {
    Http::fake();
    $this->get(route('aanbod', ['zoek' => $invoer]))
        ->assertRedirect(route('aanbod', ['q' => $plaats, 'plant' => $plant]));
    Http::assertNothingSent();
})->with([
    ['monstera haarlem', 'monstera', 'haarlem'],
    ['Monstera in Haarlem', 'Monstera', 'Haarlem'],
    ['haarlem gatenplant', 'gatenplant', 'haarlem'],
    ['8102 monstera', 'monstera', '8102'],
]);

test('een plaats die ook in een aanbodtekst staat blijft een plaats', function () {
    Http::fake();
    heroAanbod(heroLid(), 'Stekjes, ophalen in Haarlem');

    $this->get(route('aanbod', ['zoek' => 'Haarlem monstera']))
        ->assertRedirect(route('aanbod', ['q' => 'Haarlem', 'plant' => 'monstera']));
});

test('onbekende plaats wordt via de geocoder als plaats herkend', function () {
    heroCache('raalte', 'Raalte', 52.386, 6.275);

    $this->get(route('aanbod', ['zoek' => 'Raalte']))
        ->assertRedirect(route('aanbod', ['q' => 'Raalte']));
});

test('onbekende invoer krijgt een nette lege staat met suggestie, zonder redirect', function () {
    Http::fake(['*' => Http::response(['response' => ['docs' => []]], 200)]);

    $this->get(route('aanbod', ['zoek' => 'monstra']))
        ->assertOk()
        ->assertSee('Ik vond geen plaats of plant met “monstra”', false)
        ->assertSee('Bedoelde je', false)
        ->assertSee(route('aanbod', ['plant' => 'Monstera']), false);

    $this->get(route('aanbod', ['zoek' => 'flapdrolkwast']))
        ->assertOk()
        ->assertSee('Ik vond geen plaats of plant met “flapdrolkwast”', false)
        ->assertDontSee('Bedoelde je', false)
        ->assertSee('Probeer een postcode', false);
});

test('leeg zoekveld gaat naar de gewone aanbodpagina', function () {
    $this->get(route('aanbod', ['zoek' => '  ']))->assertRedirect(route('aanbod'));
});

test('zonder netwerkruimte gaat onbekende tekst door als plaats', function () {
    Http::fake();
    $intent = app(ZoekIntent::class)->bepaal('Raalte', false);

    expect($intent['soort'])->toBe(ZoekIntent::PLAATS)->and($intent['q'])->toBe('Raalte');
    Http::assertNothingSent();
});

test('plant zoeken vindt ook volksnaam, Latijnse naam en soort-id in aanbod', function () {
    Http::fake();
    $lid = heroLid();
    heroAanbod($lid, 'Monstera stek');
    heroAanbod($lid, 'Gatenplant, flink groot');
    heroAanbod($lid, 'Stek met id zonder naam', 28); // Monstera in plantensoorten.json
    heroAanbod($lid, 'Pompoen');

    $teksten = fn (string $plant): array => collect($this->getJson(route('api.zoeken', ['plant' => $plant]))->assertOk()->json('items'))
        ->pluck('tekst')->sort()->values()->all();

    $verwacht = ['Gatenplant, flink groot', 'Monstera stek', 'Stek met id zonder naam'];
    expect($teksten('monstera'))->toBe($verwacht)
        ->and($teksten('gatenplant'))->toBe($verwacht)
        ->and($teksten('Monstera deliciosa'))->toBe($verwacht)
        ->and($teksten('pompoen'))->toBe(['Pompoen'])
        ->and($teksten('onbestaande plant'))->toBe([]);
});

test('plant plus plaats via de API filtert op plant en sorteert op afstand', function () {
    Http::fake();
    heroCache('haarlem', 'Haarlem', 52.381, 4.637);
    $dichtbij = heroLid();
    $ver = User::factory()->create(['name' => 'Vera', 'locatie_label' => '9711', 'lat' => 53.219, 'lon' => 6.567, 'telefoon' => '']);
    heroAanbod($ver, 'Monstera ver weg');
    heroAanbod($dichtbij, 'Gatenplant dichtbij');
    heroAanbod($dichtbij, 'Pompoen dichtbij');

    $res = $this->getJson(route('api.zoeken', ['q' => 'haarlem', 'plant' => 'monstera']))->assertOk();

    expect(array_column($res->json('items'), 'tekst'))->toBe(['Gatenplant dichtbij', 'Monstera ver weg'])
        ->and($res->json('meta.zoek_label'))->toBe('Haarlem');
});

test('typfout in een plantnaam wint niet van een willekeurig geocoder-resultaat', function () {
    // Nominatim geeft «monstra» graag een Belgische plaats terug (Meise); de suggestie wint.
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'nominatim')) {
            return Http::response([[
                'lat' => '50.9333',
                'lon' => '4.3333',
                'display_name' => 'Meise, Vlaams-Brabant, België',
                'address' => ['postcode' => '1860', 'town' => 'Meise', 'country_code' => 'be'],
            ]], 200);
        }

        return Http::response(['response' => ['docs' => []]], 200);
    });

    $this->get(route('aanbod', ['zoek' => 'monstra']))
        ->assertOk()
        ->assertSee('Ik vond geen plaats of plant met “monstra”', false)
        ->assertSee(route('aanbod', ['plant' => 'Monstera']), false);
});

test('een echte plaats zonder plantnaam in de buurt blijft een plaats, ook bij een andere schrijfwijze', function () {
    heroCache('nl,be:raalte', 'Raalte', 52.386, 6.275);

    $intent = app(ZoekIntent::class)->bepaal('Raalte', true);

    expect($intent['soort'])->toBe(ZoekIntent::PLAATS)->and($intent['q'])->toBe('Raalte');
});
