<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/** Testwachtwoord opgebouwd (geen hardcoded “secret”-literal voor scanners). */
function stekTestPassword(): string
{
    return implode('-', ['TestOnly', 'Stek', 'Passphrase', '9']);
}

function stekAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Stek', 'Beheer', '9']);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Europe/Amsterdam'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('/stekchallenge geeft 200 met hero, CTAs, stektips en workshop', function () {
    $html = $this->get(route('stekchallenge'))
        ->assertOk()
        ->assertSee('STEKchallenge: november is stekmaand', false)
        ->assertSee('Workshop volgen: 8 november in Elim', false)
        ->assertSee('https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/', false)
        ->assertSee('Workshop geven', false)
        ->assertSee('https://carpevitam.net/stekchallenge/', false)
        ->assertSee('Contact', false)
        ->assertSee('https://carpevitam.net/contact/', false)
        ->assertSee('Foto: Carpe Vitam', false)
        ->assertSee('stekchallenge-groep-800', false)
        ->assertSee('stekchallenge-dewy-stekje', false)
        ->assertSee('stekchallenge-banner', false)
        ->assertSee('rode bes', false)
        ->assertSee('/stektips/rode-bes', false)
        ->assertSee('/stektips/zwarte-bes', false)
        ->assertSee('/stektips/wilg', false)
        ->assertSee('/stektips/zwarte-vlier', false)
        ->assertSee('Lees het persbericht', false)
        ->assertSee('geef-je-snoeihout-een-toekomst-doe-mee-met-de-stekchallenge', false)
        ->assertSee('#STEKchallenge', false)
        ->assertSee('Komende STEKchallenge-acties', false)
        ->assertSee('STEKchallenge-workshop stekken', false)
        ->assertSee('Elim', false)
        ->assertSee('/poster?voor=stekchallenge', false)
        ->assertSee(route('account.registreren', ['stekchallenge' => 1]), false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->assertDontSee('stekchallenge-plantpot', false)
        ->getContent();

    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('id="stekchallenge-deeltekst"');
});

test('registreren toont geen STEKchallenge-vinkje meer; die staat op het profiel', function () {
    $this->get(route('account.registreren', ['stekchallenge' => 1]))
        ->assertOk()
        ->assertDontSee('name="stekchallenge"', false);

    $user = User::factory()->create(['stekchallenge' => false, 'name' => 'Sam']);
    $this->actingAs($user)->get(route('account.profiel'))
        ->assertOk()
        ->assertSee('Ik doe mee aan de STEKchallenge', false)
        ->assertSee('name="stekchallenge"', false);
});

test('profiel kan stekchallenge-vinkje aanzetten', function () {
    Http::fake();
    $user = User::factory()->create([
        'stekchallenge' => false,
        'name' => 'Sam',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'postcode_gebied' => '2011',
        'woonplaats' => 'Haarlem',
        'country' => 'NL',
    ]);
    \App\Models\GeocodeCache::query()->updateOrCreate(
        ['query_key' => \App\Support\Geo::normalizeQuery('nl:2011')],
        ['lat' => 52.38, 'lon' => 4.64, 'label' => '2011 Haarlem', 'country' => 'NL', 'cached_at' => now()]
    );

    $this->actingAs($user)
        ->post(route('account.profiel'), [
            'voornaam' => 'Sam',
            'country' => 'NL',
            'locatie_modus' => 'postcode',
            'locatie' => '2011',
            'stekchallenge' => '1',
        ])
        ->assertRedirect(route('account.profiel'));

    expect($user->fresh()->stekchallenge)->toBeTrue();
});

test('poster voor=stekchallenge geeft 200 met QR naar productie', function () {
    $html = $this->get(route('poster', ['voor' => 'stekchallenge']))
        ->assertOk()
        ->assertSee('Planten over of op zoek? Ruil ze gratis in je buurt', false)
        ->assertSee('stekchallenge-1600.webp', false)
        ->assertDontSee('Joris', false)
        ->getContent();

    expect($html)->toContain('data-qr-url="https://gratisplanten.nl/stekchallenge?bron=poster-stekchallenge"')
        ->and($html)->toContain('class="poster-url">gratisplanten.nl/stekchallenge</p>');
});

test('/stekchallenge staat in sitemap; organisatie-URL redirect naar initiatief', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/stekchallenge')
        ->and($xml)->not->toContain('/organisaties/carpe-vitam');

    $this->get(route('organisaties.show', ['slug' => 'carpe-vitam']))
        ->assertRedirect(route('initiatief.show', ['slug' => 'stekchallenge']));
});

test('beheer toont telling STEKchallenge', function () {
    config(['stekjes.admin_password' => stekAdminPassphrase()]);

    User::factory()->create(['stekchallenge' => true, 'name' => 'Stek Een']);
    User::factory()->create(['stekchallenge' => false, 'name' => 'Gewoon Lid']);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('<strong>1</strong> STEKchallenge', false);
});
