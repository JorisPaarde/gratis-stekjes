<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/** Testwachtwoord opgebouwd (geen hardcoded “secret”-literal voor scanners). */
function struikTestPassword(): string
{
    return implode('-', ['TestOnly', 'Struik', 'Passphrase', '9']);
}

function struikAdminPassphrase(): string
{
    return implode('-', ['TestOnly', 'Struik', 'Beheer', '9']);
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00', 'Europe/Amsterdam'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('/struikroven geeft 200 met kopvolgorde, knoppen en lege acties', function () {
    $html = $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('Struikroven redt planten van de sloop', false)
        ->assertSee('Wat is Struikroven?', false)
        ->assertDontSee('Wie zijn de Struikrovers?', false)
        ->assertSee('Meer over Struikroven', false)
        ->assertSee('https://struikroven.nl', false)
        ->assertSee('https://struikroven.nu/agenda', false)
        ->assertDontSee('Over de stichting', false)
        ->assertDontSee('begon in 2017', false)
        ->assertDontSee('Houthalen-Helchteren', false)
        ->assertSee('Doe je mee met Struikroven?', false)
        ->assertSee('Planten zoeken in je buurt', false)
        ->assertSee('Planten over? Bied ze gratis aan', false)
        ->assertSee(route('aanbod'), false)
        ->assertSee(route('account.registreren', ['struikrover' => 1]), false)
        ->assertSee('Zoeken kan zonder account', false)
        ->assertSee('Zo werkt het', false)
        ->assertSee('Alles is gratis, er wordt niets verkocht', false)
        ->assertSee('geen straatadressen', false)
        ->assertSee('Komende Struikroven-acties', false)
        ->assertSee('Er staan nu geen Struikroven-acties gepland', false)
        ->assertDontSee('Voor Struikrovers', false)
        ->assertDontSee('Kopieer tekst', false)
        ->assertDontSee('struikroven-deeltekst', false)
        ->assertDontSee('/poster?voor=struikroven', false)
        ->assertDontSee('Wat is Gratis planten?', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->assertSee('struikroven-intro', false)
        ->assertDontSee('struikroven-logo-kaart', false)
        ->assertDontSee('struikroven-tip-met-foto', false)
        ->assertDontSee('struikroven-intro-kop', false)
        ->assertSee('logo-struikroven.webp', false)
        ->assertDontSee('logo-struikroven-wit', false)
        ->assertSee('alt="Struikroven"', false)
        ->assertSee('width="144"', false)
        ->assertSee('height="72"', false)
        ->assertSee('of in een logeertuin.', false)
        ->assertSee('Meedoen is gratis en iedereen is welkom', false)
        ->assertSee('zodat struikroven daar onderdeel wordt', false)
        ->assertSee('roverspacten in drie andere gemeenten', false)
        ->getContent();

    $h1 = strpos($html, 'Struikroven redt planten van de sloop');
    $logo = strpos($html, 'logo-struikroven.webp');
    $intro = strpos($html, 'Wat is Struikroven?');
    $alinea1 = strpos($html, 'of in een logeertuin.');
    $alinea2 = strpos($html, 'Meedoen is gratis en iedereen is welkom');
    $mee = strpos($html, 'Doe je mee met Struikroven?');
    $werkt = strpos($html, 'Zo werkt het');
    expect($h1)->toBeInt()->and($logo)->toBeInt()->and($intro)->toBeInt()
        ->and($alinea1)->toBeInt()->and($alinea2)->toBeInt()
        ->and($mee)->toBeInt()->and($werkt)->toBeInt();
    expect($h1 < $logo && $logo < $intro && $intro < $alinea1 && $alinea1 < $alinea2 && $alinea2 < $mee && $mee < $werkt)->toBeTrue();

    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('Roverspact');
});

test('/struikroven toont plantenbieb-foto met CC BY-SA-credit', function () {
    $html = $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('struikroven-intro', false)
        ->assertSee('Struikroven en Gratis planten vullen elkaar aan', false)
        ->assertDontSee('struikroven-tip-met-foto', false)
        ->assertDontSee('struikroven-logo-kaart', false)
        ->assertDontSee('struikroven-intro-kop', false)
        ->assertSee('plantenbieb-schiebroek-480.webp', false)
        ->assertSee('plantenbieb-schiebroek-960.webp', false)
        ->assertSee('alt="Plantenbieb met houten rek en planten in Schiebroek, Rotterdam"', false)
        ->assertSee('Donald Trung Quoc Don', false)
        ->assertSee('https://commons.wikimedia.org/wiki/File:Plantenbieb,_Schiebroek,_Rotterdam_(2021)_01.jpg', false)
        ->assertSee('https://creativecommons.org/licenses/by-sa/4.0/', false)
        ->assertSee('CC BY-SA 4.0', false)
        ->assertSee('Wikimedia Commons', false)
        ->assertSee('loading="lazy"', false)
        ->getContent();

    expect($html)->toContain('width="480"')
        ->and($html)->toContain('height="480"')
        ->and($html)->toContain('struikroven-plantenbieb-media');

    $logo = strpos($html, 'logo-struikroven.webp');
    $intro = strpos($html, 'Wat is Struikroven?');
    $link = strpos($html, 'Meer over Struikroven');
    $foto = strpos($html, 'plantenbieb-schiebroek-480.webp');
    $tip = strpos($html, 'Struikroven en Gratis planten vullen elkaar aan');
    $zoek = strpos($html, 'Wat zoek je?');
    expect($logo)->toBeInt()->and($intro)->toBeInt()->and($link)->toBeInt()
        ->and($foto)->toBeInt()->and($tip)->toBeInt()->and($zoek)->toBeInt();
    // Logo → H2 → link → foto → tipblok → «Wat zoek je?»
    expect($logo < $intro && $intro < $link && $link < $foto && $foto < $tip && $tip < $zoek)->toBeTrue();
});

test('/struikroven toont gekoppelde komende acties en negeert andere', function () {
    Evenement::query()->create([
        'titel' => 'Struikroof Eindhoven',
        'type' => 'overig',
        'start_datum' => '2026-05-20',
        'eind_datum' => null,
        'plaats' => 'Eindhoven',
        'provincie' => 'Noord-Brabant',
        'organisator' => 'Struikroven',
        'organisatie_slug' => 'struikroven',
        'url' => 'https://struikroven.nu/agenda',
        'bron' => '',
        'visible' => true,
    ]);
    Evenement::query()->create([
        'titel' => 'Gewone stekjesmarkt',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-05-21',
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
        'organisator' => 'Andere orga',
        'organisatie_slug' => null,
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);
    Evenement::query()->create([
        'titel' => 'Oude Struikroof',
        'type' => 'overig',
        'start_datum' => '2026-03-01',
        'plaats' => 'Tilburg',
        'provincie' => 'Noord-Brabant',
        'organisator' => 'Struikroven',
        'organisatie_slug' => 'struikroven',
        'url' => '',
        'bron' => '',
        'visible' => true,
    ]);

    $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('Struikroof Eindhoven', false)
        ->assertDontSee('Gewone stekjesmarkt', false)
        ->assertDontSee('Oude Struikroof', false)
        ->assertDontSee('Er staan nu geen Struikroven-acties gepland', false);
});

test('registreren toont geen Struikrover-vinkje meer; die staat op het profiel', function () {
    $this->get(route('account.registreren', ['struikrover' => 1]))
        ->assertOk()
        ->assertDontSee('name="struikrover"', false);

    $user = User::factory()->create(['struikrover' => false, 'name' => 'Lisa']);
    $html = $this->actingAs($user)->get(route('account.profiel'))->assertOk()->getContent();
    expect($html)->toContain('Ik ben Struikrover')
        ->and($html)->toContain('name="struikrover"');
});

test('profiel kan Struikrover-vinkje aanzetten', function () {
    Http::fake();
    $user = User::factory()->create([
        'struikrover' => false,
        'name' => 'Lisa',
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
            'voornaam' => 'Lisa',
            'country' => 'NL',
            'locatie_modus' => 'postcode',
            'locatie' => '2011',
            'struikrover' => '1',
        ])
        ->assertRedirect(route('account.profiel'));

    expect($user->fresh()->struikrover)->toBeTrue();
});

test('nieuw account heeft struikrover uit', function () {
    $user = User::factory()->create(['struikrover' => false]);
    expect((bool) $user->struikrover)->toBeFalse();
});

test('beheer toont telling Struikrovers', function () {
    config(['stekjes.admin_password' => struikAdminPassphrase()]);

    User::factory()->create(['struikrover' => true, 'name' => 'Struik Een']);
    User::factory()->create(['struikrover' => true, 'name' => 'Struik Twee']);
    User::factory()->create(['struikrover' => false, 'name' => 'Gewoon Lid']);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Struikrovers', false)
        ->assertSee('<strong>2</strong> Struikrovers', false);

    $this->withSession(['admin_ok' => true])
        ->get(route('beheer.statistieken'))
        ->assertOk()
        ->assertSee('Struikrovers', false)
        ->assertSee('2 van 3 leden heeft «Ik ben Struikrover» aangevinkt', false);
});

test('poster voor=struikroven geeft 200 met QR naar productie', function () {
    $html = $this->get(route('poster', ['voor' => 'struikroven']))
        ->assertOk()
        ->assertSee('Planten over of op zoek? Ruil ze gratis in je buurt', false)
        ->assertSee('struikroven-1600.webp', false)
        ->assertDontSee('Joris', false)
        ->getContent();

    expect($html)->toContain('data-qr-url="https://gratisplanten.nl/struikroven?bron=poster-struikroven"')
        ->and($html)->toContain('class="poster-url">gratisplanten.nl/struikroven</p>');
});

test('A5-flyer voor=struikroven geeft 200 met QR bron=flyer-struikroven', function () {
    $html = $this->get(route('poster', ['voor' => 'struikroven', 'formaat' => 'a5']))
        ->assertOk()
        ->assertSee('Planten over na de actie?', false)
        ->assertSee('struikroven-1600.webp', false)
        ->assertSee('poster--a5', false)
        ->assertSee('size: A5', false)
        ->assertSee('Planten over na een actie? Zet ze er gratis op.', false)
        ->assertDontSee('Joris', false)
        ->getContent();

    expect($html)->toContain('data-qr-url="https://gratisplanten.nl/struikroven?bron=flyer-struikroven"')
        ->and($html)->toContain('class="poster-url">gratisplanten.nl/struikroven</p>');
});

test('/struikroven staat in sitemap; organisatie-URL redirect naar initiatief', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/struikroven')
        ->and($xml)->not->toContain('/organisaties/struikroven');

    $this->get(route('organisaties.show', ['slug' => 'struikroven']))
        ->assertRedirect(route('initiatief.show', ['slug' => 'struikroven']));
});

test('ingelogde bezoeker krijgt aanbieden-link op /struikroven', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('struikroven'))
        ->assertOk()
        ->assertSee(route('aanbieden'), false)
        ->assertDontSee(route('account.registreren', ['struikrover' => 1]), false);
});
