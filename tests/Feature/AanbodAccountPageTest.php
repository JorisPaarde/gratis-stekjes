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
        ->assertSee('name="tekst"', false);
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
