<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00', 'Europe/Amsterdam'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('/struikroven geeft 200 met knoppen, uitleg en lege acties', function () {
    $html = $this->get(route('struikroven'))
        ->assertOk()
        ->assertSee('Planten zoeken in je buurt', false)
        ->assertSee('Planten over? Bied ze gratis aan', false)
        ->assertSee(route('aanbod'), false)
        ->assertSee(route('account.registreren', ['struikrover' => 1]), false)
        ->assertSee('Alles is gratis', false)
        ->assertSee('Komende Struikroven-acties', false)
        ->assertSee('Er staan nu geen Struikroven-acties gepland', false)
        ->assertDontSee('Joris', false)
        ->assertDontSee('JP Web', false)
        ->assertDontSee('struikroven-logo', false)
        ->getContent();

    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('application/ld+json');
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
        'password' => 'TestOnly-Struik-Passphrase-9',
        'password_confirmation' => 'TestOnly-Struik-Passphrase-9',
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
        'password' => 'TestOnly-Struik-Passphrase-9',
        'password_confirmation' => 'TestOnly-Struik-Passphrase-9',
        'gratis_akkoord' => '1',
        'privacy' => '1',
    ])->assertRedirect(route('account.bevestiging-nodig'));

    $user = User::query()->where('email', 'tom.gewoon@example.com')->first();
    expect($user)->not->toBeNull()
        ->and((bool) $user->struikrover)->toBeFalse();
});

test('beheer toont telling Struikrovers', function () {
    config(['stekjes.admin_password' => 'TestOnly-Struik-Beheer-9']);

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

test('poster voor=struikroven geeft 200 met QR naar struikroven', function () {
    $html = $this->get(route('poster', ['voor' => 'struikroven']))
        ->assertOk()
        ->assertSee('Planten over of op zoek? Ruil ze gratis in je buurt', false)
        ->assertSee('data-qr-url="', false)
        ->assertSee('/struikroven?bron=poster-struikroven', false)
        ->assertSee('struikroven-1600.webp', false)
        ->assertDontSee('Joris', false)
        ->getContent();

    expect($html)->toContain('data-qr-url="'.route('struikroven').'?bron=poster-struikroven"');
});

test('/struikroven staat in sitemap; organisatiepagina blijft', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain('/struikroven')
        ->and($xml)->toContain('/organisaties/struikroven');

    $this->get(route('organisaties.show', ['slug' => 'struikroven']))
        ->assertOk()
        ->assertSee('https://struikroven.nu', false);
});

test('ingelogde bezoeker krijgt aanbieden-link op /struikroven', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('struikroven'))
        ->assertOk()
        ->assertSee(route('aanbieden'), false)
        ->assertDontSee(route('account.registreren', ['struikrover' => 1]), false);
});
