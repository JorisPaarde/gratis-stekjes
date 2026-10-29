<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;

function aanbodPageUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

test('GET account/aanbod zonder locatie redirect naar profiel (geen 500)', function () {
    $user = aanbodPageUser([
        'locatie_label' => '',
        'lat' => null,
        'lon' => null,
    ]);

    $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertRedirect(route('account.profiel'));
});

test('GET account/aanbod met locatie toont formulier (200)', function () {
    $user = aanbodPageUser();

    $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertOk()
        ->assertSee('Mijn aanbod', false)
        ->assertSee('name="tekst"', false)
        ->assertSee('invasieve', false)
        ->assertSee(route('invasieve-soorten'), false);
});

test('GET account/aanbod met bestaand aanbod toont items (200)', function () {
    $user = aanbodPageUser();
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertOk()
        ->assertSee('Muntstekjes', false)
        ->assertSee('name="tekst"', false)
        ->assertSee('Verwijderen', false)
        ->assertSee('Weet je het zeker?', false);
});

test('eigenaar kan eigen aanbod verwijderen; verdwijnt uit zoek-API en stadspagina', function () {
    $user = aanbodPageUser([
        'locatie_label' => 'Haarlem',
        'lat' => 52.387,
        'lon' => 4.646,
    ]);
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Te verwijderen monstera',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->getJson(route('api.zoeken', ['q' => 'Haarlem']))
        ->assertOk()
        ->assertJsonFragment(['tekst' => 'Te verwijderen monstera']);

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertSee('Te verwijderen monstera', false);

    $this->actingAs($user)
        ->delete(route('account.aanbod.destroy', $aanbod))
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHas('status', 'Aanbod verwijderd.');

    expect(Aanbod::query()->whereKey($aanbod->id)->exists())->toBeFalse();

    $this->getJson(route('api.zoeken', ['q' => 'Haarlem']))
        ->assertOk()
        ->assertJsonMissing(['tekst' => 'Te verwijderen monstera']);

    $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->assertDontSee('Te verwijderen monstera', false);
});

test('andere gebruiker mag aanbod niet verwijderen', function () {
    $owner = aanbodPageUser(['email' => 'owner@example.com']);
    $other = aanbodPageUser(['email' => 'other@example.com', 'name' => 'Other']);
    $aanbod = Aanbod::query()->create([
        'user_id' => $owner->id,
        'type' => 'uitdelen',
        'tekst' => 'Niet van jou',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($other)
        ->delete(route('account.aanbod.destroy', $aanbod))
        ->assertForbidden();

    expect(Aanbod::query()->whereKey($aanbod->id)->exists())->toBeTrue();
});

test('POST aanbod/nieuw met Japanse duizendknoop wordt afgewezen', function () {
    $user = aanbodPageUser();

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Japanse duizendknoop stekjes',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst')
        ->assertRedirect();

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
    expect(session('errors')->first('tekst'))->toContain('Japanse duizendknoop');
});

test('POST aanbod/nieuw met Monstera lukt', function () {
    $user = aanbodPageUser();

    $this->actingAs($user)
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => 'Monstera deliciosa stekje',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    expect(Aanbod::query()->where('user_id', $user->id)->value('tekst'))
        ->toBe('Monstera deliciosa stekje');
});
