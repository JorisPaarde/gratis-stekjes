<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MilestoneService;
use Illuminate\Support\Facades\Http;

function adresUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Ada',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ], $extra));
}

function profielPost($test, User $u, array $extra)
{
    Http::fake(['*' => Http::response(['response' => ['docs' => [
        ['centroide_ll' => 'POINT(4.637 52.381)', 'weergavenaam' => '2011AB Haarlem', 'type' => 'postcode', 'score' => 10],
    ]]])]);

    return $test->actingAs($u)->post(route('account.profiel'), array_merge([
        'voornaam' => 'Ada',
        'country' => 'NL',
        'locatie' => '2011 AB',
        'telefoon' => '',
    ], $extra));
}

test('adres opslaan: versleuteld, openbaar alleen postcodegebied', function () {
    $u = adresUser();
    profielPost($this, $u, ['huisnummer' => '12a', 'straat' => 'Grote Markt', 'plaats' => 'Haarlem', 'adres_delen' => '1'])
        ->assertRedirect(route('account.profiel'))
        ->assertSessionHasNoErrors();

    $f = $u->fresh();
    expect($f->volledigAdres())->toBe('Grote Markt 12A, 2011 AB Haarlem')
        ->and($f->locatie_label)->toBe('2011')
        ->and((bool) $f->adres_delen)->toBeTrue();

    $raw = DB::table('users')->where('id', $u->id)->value('adres_straat');
    expect($raw)->not->toContain('Grote Markt');

    Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Munt', 'visible' => true, 'expires_at' => now()->addDays(9)]);
    $json = $this->getJson('/api/zoeken?recent=1')->getContent();
    expect($json)->not->toContain('Grote Markt')->and($json)->not->toContain('2011 AB');
});

test('adres onvolledig of zonder volledige postcode wordt geweigerd; leeg maken mag', function () {
    $u = adresUser();
    profielPost($this, $u, ['huisnummer' => '12', 'straat' => '', 'plaats' => 'Haarlem'])->assertSessionHasErrors('huisnummer');
    profielPost($this, $u, ['locatie' => '2011', 'huisnummer' => '12', 'straat' => 'X', 'plaats' => 'Haarlem'])->assertSessionHasErrors('locatie');

    profielPost($this, $u, ['huisnummer' => '12', 'straat' => 'Grote Markt', 'plaats' => 'Haarlem']);
    expect($u->fresh()->volledigAdres())->not->toBeNull();
    profielPost($this, $u, ['huisnummer' => '', 'straat' => '', 'plaats' => '']);
    expect($u->fresh()->volledigAdres())->toBeNull();
});

test('adres zichtbaar in gesprek pas na wederzijds ja en alleen als delen aan staat', function () {
    $a = adresUser(['email' => 'a@example.com', 'adres_straat' => 'Grote Markt', 'adres_huisnummer' => '1', 'adres_postcode' => '2011 AB', 'adres_plaats' => 'Haarlem']);
    $b = adresUser(['email' => 'b@example.com', 'name' => 'Ben']);
    $conv = Conversation::query()->create(['user_a_id' => min($a->id, $b->id), 'user_b_id' => max($a->id, $b->id)]);

    $this->actingAs($b)->get(route('account.gesprek', $conv))->assertOk()->assertDontSee('Grote Markt', false);

    $conv->update(['contact_a' => 'ja', 'contact_b' => 'ja']);
    $this->actingAs($b)->get(route('account.gesprek', $conv))->assertOk()->assertSee('Grote Markt 1, 2011 AB Haarlem', false);

    $a->forceFill(['adres_delen' => false])->save();
    $this->actingAs($b)->get(route('account.gesprek', $conv))->assertOk()->assertDontSee('Grote Markt', false);
});

test('postcode opzoeken via PDOK geeft straat, plaats en toevoegingen', function () {
    Http::fake(['*' => Http::response(['response' => ['docs' => [
        ['straatnaam' => 'Grote Markt', 'huisnummer' => 12, 'huisletter' => 'A', 'postcode' => '2011AB', 'woonplaatsnaam' => 'Haarlem'],
        ['straatnaam' => 'Grote Markt', 'huisnummer' => 12, 'postcode' => '2011AB', 'woonplaatsnaam' => 'Haarlem'],
    ]]])]);
    $u = adresUser();

    $this->actingAs($u)->getJson(route('account.adres-zoeken', ['postcode' => '2011 ab', 'huisnummer' => '12']))
        ->assertOk()
        ->assertJson(['ok' => true, 'straat' => 'Grote Markt', 'plaats' => 'Haarlem', 'postcode' => '2011 AB', 'toevoegingen' => ['A']]);

    $this->actingAs($u)->getJson(route('account.adres-zoeken', ['postcode' => 'abc', 'huisnummer' => '12']))->assertStatus(422);
    auth()->logout();
    $this->getJson(route('account.adres-zoeken', ['postcode' => '2011AB', 'huisnummer' => '12']))->assertStatus(401);
});

test('naam mag geen e-mailadres zijn; bestaande e-mailnamen openbaar als Lid', function () {
    $u = adresUser(['name' => 'ada@example.com']);
    profielPost($this, $u, ['voornaam' => 'ada@example.com'])->assertSessionHasErrors('voornaam');
    expect($u->publiekeNaam())->toBe('Lid');

    Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Munt', 'visible' => true, 'expires_at' => now()->addDays(9)]);
    $json = $this->getJson('/api/zoeken?recent=1')->getContent();
    expect($json)->not->toContain('ada@example.com')->and($json)->toContain('"voornaam":"Lid"');
});

test('volgende stap bovenaan accountpagina: postcode, eerste stekje, daarna badge; ongelezen eerst', function () {
    $zonderPlek = adresUser(['locatie_label' => '', 'lat' => null, 'lon' => null]);
    $this->actingAs($zonderPlek)->get(route('account.index'))->assertOk()
        ->assertSee('class="volgende-stap"', false)->assertSee('Vul je postcode in', false);

    $u = adresUser(['email' => 'stap@example.com']);
    $this->actingAs($u)->get(route('account.index'))->assertOk()->assertSee('Zet je eerste stekje online', false);
    // Op de pagina waar de stap naartoe wijst, geen dubbele balk.
    $this->actingAs($u)->get(route('account.aanbod'))->assertOk()->assertDontSee('class="volgende-stap"', false);

    Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Munt', 'visible' => true, 'expires_at' => now()->addDays(9)]);
    $this->actingAs($u)->get(route('account.index'))->assertOk()
        ->assertSee('Volgende badge: Profiel compleet', false)
        ->assertSee('/assets/badges/profiel_compleet.svg', false);

    $b = adresUser(['email' => 'stuurder@example.com']);
    $conv = Conversation::query()->create(['user_a_id' => min($u->id, $b->id), 'user_b_id' => max($u->id, $b->id)]);
    Message::query()->create(['conversation_id' => $conv->id, 'sender_id' => $b->id, 'body' => 'Hoi', 'created_at' => now()]);
    $this->actingAs($u)->get(route('account.index'))->assertOk()->assertSee('Je hebt een nieuw bericht', false);

    // Niet op publieke pagina's.
    $this->actingAs($u)->get(route('aanbod'))->assertOk()->assertDontSee('class="volgende-stap"', false);
});

test('badge profiel compleet', function () {
    $u = adresUser(['adres_straat' => 'Grote Markt', 'adres_huisnummer' => '1', 'adres_postcode' => '2011 AB', 'adres_plaats' => 'Haarlem']);
    $b = collect(app(MilestoneService::class)->badgesFor($u))->firstWhere('key', 'profiel_compleet');
    expect($b['earned'])->toBeTrue();
});
