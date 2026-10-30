<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;

function aanbodUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Sam',
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
        'telefoon' => '',
    ], $extra));
}

test('uitlegpagina invasieve soorten is 200', function () {
    $this->get(route('invasieve-soorten'))
        ->assertOk()
        ->assertSee('Invasieve soorten', false)
        ->assertSee('Reuzenberenklauw', false)
        ->assertSee('Japanse duizendknoop', false)
        ->assertSee('EU-Unielijst', false);
});

test('sitemap bevat invasieve-soorten', function () {
    $xml = $this->get(route('sitemap'))
        ->assertOk()
        ->getContent();

    expect($xml)->toContain(route('invasieve-soorten'));
});

test('aanbod plaatsen blokkeert op Nederlandse naam', function () {
    $user = aanbodUser();

    $this->actingAs($user)
        ->post(route('account.aanbod'), [
            'type' => 'uitdelen',
            'tekst' => 'Reuzenberenklauw stekjes te geef',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst')
        ->assertRedirect();

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('aanbod plaatsen blokkeert op wetenschappelijke naam met accenten/hoofdletters', function () {
    $user = aanbodUser();

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod'), [
            'type' => 'uitdelen',
            'tekst' => 'Reynoutria × bohemica wortels',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    expect(Aanbod::query()->count())->toBe(0);
});

test('aanbod plaatsen blokkeert op synoniem', function () {
    $user = aanbodUser();

    $this->actingAs($user)
        ->post(route('account.aanbod'), [
            'type' => 'ophalen',
            'tekst' => 'Zoek Fallopia japonica',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors('tekst');

    expect(session('errors')->first('tekst'))->toContain('Japanse duizendknoop');
    expect(session('errors')->first('tekst'))->toContain(route('invasieve-soorten'));
});

test('gewone plant mag wel geplaatst worden', function () {
    $user = aanbodUser();

    $this->actingAs($user)
        ->post(route('account.aanbod'), [
            'type' => 'uitdelen',
            'tekst' => 'Zwarte bessenstekken en munt',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('aanbod wijzigen blokkeert invasieve soort', function () {
    $user = aanbodUser();
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->from(route('account.aanbod', ['edit' => $aanbod->id]))
        ->post(route('account.aanbod.update', $aanbod), [
            'type' => 'uitdelen',
            'tekst' => 'Hemelboom zaailingen',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
            'edit_id' => $aanbod->id,
        ])
        ->assertSessionHasErrors('tekst');

    expect($aanbod->fresh()->tekst)->toBe('Muntstekjes');
});

test('aanbod wijzigen met gewone plant lukt', function () {
    $user = aanbodUser();
    $aanbod = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->post(route('account.aanbod.update', $aanbod), [
            'type' => 'ophalen',
            'tekst' => 'Tomatenplantjes gezocht',
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect(route('account.aanbod'))
        ->assertSessionHasNoErrors();

    $aanbod->refresh();
    expect($aanbod->tekst)->toBe('Tomatenplantjes gezocht');
    expect($aanbod->type)->toBe('ophalen');
});
