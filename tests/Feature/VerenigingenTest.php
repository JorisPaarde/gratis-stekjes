<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\VerenigingAangemeld;
use Illuminate\Support\Facades\Notification;

function lid(string $email, string $name = 'Sam'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

function lidAanbod(User $u, string $tekst = 'Muntstekjes'): Aanbod
{
    return Aanbod::query()->create([
        'user_id' => $u->id,
        'type' => 'uitdelen',
        'tekst' => $tekst,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
}

function koppel($test, User $u, array $data)
{
    return $test->actingAs($u)->post(route('account.verenigingen.store'), $data);
}

test('lid voegt nieuwe vereniging toe: wacht op controle, beheer krijgt mail, nog niet openbaar', function () {
    Notification::fake();
    config(['stekjes.admin_email' => 'beheer@example.com']);
    $u = lid('l1@example.com');

    koppel($this, $u, ['vereniging' => 'Volkstuinvereniging De Eendracht', 'plaats' => 'Haarlem', 'type' => 'volkstuin'])
        ->assertRedirect(route('account.verenigingen'));

    $v = Vereniging::query()->firstOrFail();
    expect($v->status)->toBe('pending')
        ->and($v->slug)->toBe('volkstuinvereniging-de-eendracht-haarlem')
        ->and($u->verenigingen()->count())->toBe(1)
        ->and((bool) $u->verenigingen()->first()->pivot->zichtbaar)->toBeFalse();
    Notification::assertSentOnDemand(VerenigingAangemeld::class);

    $this->get(route('organisaties.show', ['slug' => $v->slug]))->assertNotFound();
    $this->actingAs($u)->get(route('account.verenigingen'))->assertOk()->assertSee('Wacht op controle', false);
});

test('tweede lid kiest dezelfde vereniging uit de lijst; geen dubbele', function () {
    Notification::fake();
    $a = lid('l2@example.com');
    $b = lid('l3@example.com');
    koppel($this, $a, ['vereniging' => 'Tuinpark Ons Genoegen', 'plaats' => 'Utrecht', 'type' => 'tuinvereniging']);
    Vereniging::query()->update(['status' => 'approved']);

    $this->actingAs($b)->get(route('account.verenigingen'))->assertOk()->assertSee('Tuinpark Ons Genoegen — Utrecht', false);
    koppel($this, $b, ['vereniging' => 'Tuinpark Ons Genoegen — Utrecht'])->assertRedirect();

    expect(Vereniging::query()->count())->toBe(1)
        ->and(Vereniging::query()->first()->leden()->count())->toBe(2);
});

test('nieuwe vereniging zonder plaats of soort wordt geweigerd', function () {
    $u = lid('l4@example.com');
    koppel($this, $u, ['vereniging' => 'Iets Nieuws'])->assertSessionHasErrors('vereniging');
    expect(Vereniging::query()->count())->toBe(0);
});

test('maximaal vijf verenigingen per lid', function () {
    Notification::fake();
    $u = lid('l5@example.com');
    foreach (range(1, 5) as $i) {
        koppel($this, $u, ['vereniging' => 'Tuin '.$i, 'plaats' => 'Haarlem', 'type' => 'buurttuin']);
    }
    koppel($this, $u, ['vereniging' => 'Tuin 6', 'plaats' => 'Haarlem', 'type' => 'buurttuin'])->assertSessionHasErrors('vereniging');
    expect($u->verenigingen()->count())->toBe(5);
});

test('lidmaatschap alleen openbaar bij vinkje én goedgekeurd; pagina toont aantal, geen namen van anderen', function () {
    Notification::fake();
    $zicht = lid('l6@example.com', 'Zichtbare Zus');
    $stil = lid('l7@example.com', 'Stille Sjaak');
    lidAanbod($zicht, 'Rabarber uit de tuin');
    lidAanbod($stil, 'Geheime courgette');
    koppel($this, $zicht, ['vereniging' => 'VTV Groenrijk', 'plaats' => 'Haarlem', 'type' => 'volkstuin']);
    $v = Vereniging::query()->firstOrFail();
    koppel($this, $stil, ['vereniging' => 'VTV Groenrijk — Haarlem']);

    // Nog pending: niets openbaar.
    $this->actingAs($zicht)->post(route('account.verenigingen.update', $v), ['zichtbaar' => '1'])->assertRedirect();
    $items = collect($this->getJson('/api/zoeken?recent=1')->json('items'));
    expect($items->firstWhere('tekst', 'Rabarber uit de tuin')['verenigingen'])->toBe([]);

    $v->update(['status' => 'approved']);
    $items = collect($this->getJson('/api/zoeken?recent=1')->json('items'));
    expect($items->firstWhere('tekst', 'Rabarber uit de tuin')['verenigingen'][0]['naam'])->toBe('VTV Groenrijk')
        ->and($items->firstWhere('tekst', 'Geheime courgette')['verenigingen'])->toBe([]);

    auth()->logout();
    $html = $this->get($v->url())->assertOk()->getContent();
    expect($html)->toContain('2 leden zijn')
        ->and($html)->toContain('Rabarber uit de tuin')
        ->and($html)->not->toContain('Geheime courgette')
        ->and($html)->not->toContain('Stille Sjaak')
        ->and($html)->toContain(route('organisaties.poster', ['slug' => $v->slug]));

    $this->get(route('organisaties.index'))->assertOk()->assertSee('VTV Groenrijk', false);
    expect($this->get('/sitemap.xml')->getContent())->toContain($v->url());
});

test('poster met QR-code, ook voor een organisatie uit de vaste lijst', function () {
    Notification::fake();
    $u = lid('l8@example.com');
    koppel($this, $u, ['vereniging' => 'Poster Tuin', 'plaats' => 'Leiden', 'type' => 'volkstuin']);
    $v = Vereniging::query()->firstOrFail();
    $v->update(['status' => 'approved']);

    $this->get(route('organisaties.poster', ['slug' => $v->slug]))
        ->assertOk()
        ->assertSee('data-qr-url="'.$v->url().'"', false)
        ->assertSee('Poster Tuin', false)
        ->assertSee('noindex', false);

    $this->get(route('organisaties.poster', ['slug' => 'velt']))->assertOk();
    $this->get(route('organisaties.poster', ['slug' => 'bestaat-niet']))->assertNotFound();
});

test('koppelen aan organisatie uit de vaste lijst: direct goedgekeurd, ledenblok op bestaande pagina', function () {
    $u = lid('l9@example.com', 'Vera');
    lidAanbod($u, 'Zaden van Velt');
    koppel($this, $u, ['vereniging' => 'Velt Nederland'])->assertRedirect();

    $v = Vereniging::query()->firstOrFail();
    expect($v->json_slug)->toBe('velt')->and($v->status)->toBe('approved');
    $this->actingAs($u)->post(route('account.verenigingen.update', $v), ['zichtbaar' => '1']);

    $this->get(route('organisaties.show', ['slug' => 'velt']))
        ->assertOk()
        ->assertSee('Velt Nederland', false)
        ->assertSee('Gedeeld door leden', false)
        ->assertSee('Zaden van Velt', false);
});

test('ontkoppelen, export en account wissen', function () {
    Notification::fake();
    $u = lid('l10@example.com');
    koppel($this, $u, ['vereniging' => 'Wis Tuin', 'plaats' => 'Delft', 'type' => 'buurttuin']);
    $v = Vereniging::query()->firstOrFail();

    $data = json_decode($this->actingAs($u)->get(route('account.export'))->streamedContent(), true);
    expect($data['verenigingen'][0]['naam'])->toBe('Wis Tuin');

    $this->actingAs($u)->delete(route('account.verenigingen.destroy', $v))->assertRedirect();
    expect($u->verenigingen()->count())->toBe(0)->and(Vereniging::query()->count())->toBe(1);
});

test('beheer keurt goed en verbergt; leden kunnen niet', function () {
    Notification::fake();
    config(['stekjes.admin_dashboard_emails' => ['admin@example.com']]);
    $admin = lid('admin@example.com', 'Joris');
    $u = lid('l11@example.com');
    koppel($this, $u, ['vereniging' => 'Beheer Tuin', 'plaats' => 'Gouda', 'type' => 'volkstuin']);
    $v = Vereniging::query()->firstOrFail();

    $this->actingAs($admin)->get(route('beheer.index'))->assertOk()->assertSee('Beheer Tuin', false)->assertSee('wacht op controle', false);
    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'approved'])->assertRedirect();
    expect($v->fresh()->status)->toBe('approved');

    $this->actingAs($u)->post(route('beheer.verenigingen.status', $v), ['status' => 'hidden'])->assertRedirect(route('beheer.login'));
    expect($v->fresh()->status)->toBe('approved');

    $this->actingAs($admin)->post(route('beheer.verenigingen.status', $v), ['status' => 'hidden']);
    $this->get($v->url())->assertNotFound();
});
