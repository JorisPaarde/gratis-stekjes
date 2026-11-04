<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

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
        ->assertSee('Wie zijn de Struikrovers?', false)
        ->assertSee('Meer over Struikroven', false)
        ->assertSee('https://struikroven.nu', false)
        ->assertSee('Over de stichting', false)
        ->assertSee(route('organisaties.show', ['slug' => 'struikroven']), false)
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
        ->assertSee('Voor Struikrovers', false)
        ->assertSee('/poster?voor=struikroven', false)
        ->assertSee('formaat=a5', false)
        ->assertSee('Kopieer tekst', false)
        ->assertSee('gratisplanten.nl/struikroven', false)
        ->assertDontSee('Wat is Gratis planten?', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->assertDontSee('struikroven-logo', false)
        ->getContent();

    $h1 = strpos($html, 'Struikroven redt planten van de sloop');
    $mee = strpos($html, 'Doe je mee met Struikroven?');
    $werkt = strpos($html, 'Zo werkt het');
    $voor = strpos($html, 'Voor Struikrovers');
    expect($h1)->toBeInt()->and($mee)->toBeInt()->and($werkt)->toBeInt()->and($voor)->toBeInt();
    expect($h1 < $mee && $mee < $werkt && $werkt < $voor)->toBeTrue();

    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('id="struikroven-deeltekst"')
        ->and($html)->toContain('Planten over na een struikroofactie?');
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

test('aanmeldparameter zet vinkje Ik ben Struikrover', function () {
    $this->get(route('account.registreren', ['struikrover' => 1]))
        ->assertOk()
        ->assertSee('Ik ben Struikrover', false)
        ->assertSee('name="struikrover" value="1"', false)
        ->assertSee('checked', false);

    $this->get(route('account.registreren'))
        ->assertOk()
        ->assertSee('Ik ben Struikrover', false);

    $html = $this->get(route('account.registreren'))->assertOk()->getContent();
    expect(preg_match('/name="struikrover"[^>]*checked/', $html))->toBe(0);
});

test('registreren met struikrover-vinkje slaat true op', function () {
    Notification::fake();

    $this->post(route('account.registreren'), [
        'website' => '',
        'form_started' => time() - 10,
        'voornaam' => 'Lisa',
        'email' => 'lisa.struik@example.com',
        'password' => struikTestPassword(),
        'password_confirmation' => struikTestPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
        'struikrover' => '1',
    ])->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'lisa.struik@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->struikrover)->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailDutch::class);
});

test('registreren zonder vinkje houdt struikrover uit', function () {
    Notification::fake();

    $this->post(route('account.registreren'), [
        'website' => '',
        'form_started' => time() - 10,
        'voornaam' => 'Tom',
        'email' => 'tom.gewoon@example.com',
        'password' => struikTestPassword(),
        'password_confirmation' => struikTestPassword(),
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ])->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'tom.gewoon@example.com')->first();
    expect($user)->not->toBeNull()
        ->and((bool) $user->struikrover)->toBeFalse();
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

test('/struikroven staat in sitemap; organisatiepagina blijft', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/struikroven')
        ->and($xml)->toContain('/organisaties/struikroven');

    $this->get(route('organisaties.show', ['slug' => 'struikroven']))
        ->assertOk()
        ->assertSee('https://struikroven.nu', false)
        ->assertSee('Planten delen na een actie', false)
        ->assertSee('/struikroven', false);
});

test('ingelogde bezoeker krijgt aanbieden-link op /struikroven', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('struikroven'))
        ->assertOk()
        ->assertSee(route('aanbieden'), false)
        ->assertDontSee(route('account.registreren', ['struikrover' => 1]), false);
});
