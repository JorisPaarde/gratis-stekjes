<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;

function aantalUser(): User
{
    return User::factory()->create(['locatie_label' => '2011', 'lat' => 52.381, 'lon' => 4.637]);
}

function aantalAanbod(User $u, ?int $aantal = null): Aanbod
{
    return Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => $aantal, 'visible' => true, 'expires_at' => now()->addDays(30)]);
}

test('aanbod plaatsen met aantal; zichtbaar in lijst en publieke API', function () {
    $u = aantalUser();
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '5', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasNoErrors();

    $a = Aanbod::query()->where('user_id', $u->id)->firstOrFail();
    expect($a->aantal)->toBe(5);
    $this->actingAs($u)->get(route('account.aanbod'))->assertSee('<strong>5</strong>', false)->assertSee('stuks', false);
    expect($this->getJson('/api/zoeken?recent=1')->json('items.0.aantal'))->toBe(5);
});

test('aantal valideert 1–99', function () {
    $u = aantalUser();
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '0', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasErrors('aantal');
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '100', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasErrors('aantal');
});

test('− en + passen het aantal aan; bij 0 gaat het aanbod offline', function () {
    $u = aantalUser();
    $a = aantalAanbod($u, 2);

    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => 1])->assertRedirect();
    expect($a->fresh()->aantal)->toBe(3);
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    expect($a->fresh()->aantal)->toBe(1)->and($a->fresh()->visible)->toBeTrue();
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    expect($a->fresh()->visible)->toBeFalse();

    $this->actingAs($u)->get(route('account.aanbod'))->assertSee('Weer online', false);
});

test('aantal aanpassen: alleen eigen aanbod en alleen −1/+1', function () {
    $a = aantalAanbod(aantalUser(), 3);
    $ander = aantalUser();
    $this->actingAs($ander)->post(route('account.aanbod.aantal', $a), ['delta' => 1])->assertForbidden();
    $this->actingAs($a->user)->post(route('account.aanbod.aantal', $a), ['delta' => 5])->assertStatus(422);
});
