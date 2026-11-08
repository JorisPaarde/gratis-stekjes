<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Support\AanbodTekst;

function tekstFoutUser(string $email = 'tekst-fout@example.com'): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => 'Sam',
        'locatie_label' => '2011 Haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'active' => true,
        'email_verified_at' => now(),
    ]);
}

test('create-fout toont inline fout + aria-invalid/autofocus op create-input, niet op edit-rijen', function () {
    $user = tekstFoutUser('create-fout@example.com');
    $bestaand = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Monstera',
        'aantal' => 1,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->from(route('account.aanbod'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => '   ',
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    $html = $this->actingAs($user)
        ->get(route('account.aanbod'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/id="aanbod-tekst-fout"[^>]*>\s*'.preg_quote(AanbodTekst::FOUT_VERPLICHT, '/').'/')
        ->and($html)->toMatch('/id="aanbod-tekst"[^>]*aria-invalid="true"/s')
        ->and($html)->toMatch('/id="aanbod-tekst"[^>]*autofocus/s')
        // Geen geopende edit-rij → geen foutmarkering op bestaande items.
        ->and($html)->not->toContain('id="aanbod-tekst-'.$bestaand->id.'"')
        ->and($html)->not->toContain('id="aanbod-tekst-fout-'.$bestaand->id.'"');
});

test('edit-fout toont inline fout alleen op de geopende rij; create-form blijft zonder aria-invalid', function () {
    $user = tekstFoutUser('edit-fout@example.com');
    $a = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Monstera',
        'aantal' => 1,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    $b = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Munt',
        'aantal' => 2,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->from(route('account.aanbod', ['edit' => $a->id]))
        ->post(route('account.aanbod.update', $a), [
            'edit_id' => $a->id,
            'type' => 'uitdelen',
            'tekst' => '',
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    $html = $this->actingAs($user)
        ->get(route('account.aanbod', ['edit' => $a->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/id="aanbod-tekst-fout-'.$a->id.'"[^>]*>\s*'.preg_quote(AanbodTekst::FOUT_VERPLICHT, '/').'/')
        ->and($html)->toMatch('/id="aanbod-tekst-'.$a->id.'"[^>]*aria-invalid="true"/s')
        ->and($html)->toMatch('/id="aanbod-tekst-'.$a->id.'"[^>]*autofocus/s')
        // Andere rij niet in edit-modus → geen input/fout-id.
        ->and($html)->not->toContain('id="aanbod-tekst-'.$b->id.'"')
        ->and($html)->not->toContain('id="aanbod-tekst-fout-'.$b->id.'"')
        // Create-form deelt old('tekst') maar mag geen aria-fout krijgen.
        ->and($html)->not->toMatch('/id="aanbod-tekst"[^>]*aria-invalid/s')
        ->and($html)->toMatch('/id="aanbod-tekst-fout"\s+hidden/');
});

test('aanbieden-pagina: serverfout tekst inline met aria-invalid', function () {
    $user = tekstFoutUser('aanbieden-fout@example.com');

    $this->actingAs($user)
        ->from(route('aanbieden'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => '',
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    $html = $this->actingAs($user)
        ->get(route('aanbieden'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/id="aanbod-tekst-fout"[^>]*>\s*'.preg_quote(AanbodTekst::FOUT_VERPLICHT, '/').'/')
        ->and($html)->toMatch('/id="aanbod-tekst"[^>]*aria-invalid="true"/s')
        ->and($html)->toMatch('/id="aanbod-tekst"[^>]*autofocus/s')
        ->and($html)->not->toMatch('/id="aanbod-tekst-fout"\s+hidden/');
});
