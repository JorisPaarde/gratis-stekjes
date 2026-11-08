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
        ->and($html)->not->toContain('id="aanbod-tekst-'.$bestaand->id.'"')
        ->and($html)->not->toContain('id="aanbod-tekst-fout-'.$bestaand->id.'"');
});

test('create-fout terwijl ?edit= open: fout op create, niet op ongerelateerde edit-rij', function () {
    $user = tekstFoutUser('create-while-edit@example.com');
    $bestaand = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Monstera',
        'aantal' => 1,
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);

    // NoJS-achtig: create submit zonder edit_id, vanaf URL met ?edit=N.
    $this->actingAs($user)
        ->from(route('account.aanbod', ['edit' => $bestaand->id]))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => "\u{00A0}",
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    $html = $this->actingAs($user)
        ->get(route('account.aanbod', ['edit' => $bestaand->id]))
        ->assertOk()
        ->getContent();

    // Create owned error.
    expect($html)->toMatch('/id="aanbod-tekst"[^>]*aria-invalid="true"/s')
        ->and($html)->toMatch('/id="aanbod-tekst-fout"[^>]*>\s*'.preg_quote(AanbodTekst::FOUT_VERPLICHT, '/').'/')
        // Edit-UI mag openstaan via GET edit, maar géén foutmarkering zonder old(edit_id).
        ->and($html)->toContain('id="aanbod-tekst-'.$bestaand->id.'"')
        ->and($html)->not->toMatch('/id="aanbod-tekst-'.$bestaand->id.'"[^>]*aria-invalid/s')
        ->and($html)->toMatch('/id="aanbod-tekst-fout-'.$bestaand->id.'"\s+hidden/');
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
        ->and($html)->not->toContain('id="aanbod-tekst-'.$b->id.'"')
        ->and($html)->not->toContain('id="aanbod-tekst-fout-'.$b->id.'"')
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

test('NEL-only plantnaam (U+0085) wordt server-side afgewezen met exacte foutcopy', function () {
    $user = tekstFoutUser('nel-only@example.com');

    $this->actingAs($user)
        ->from(route('aanbieden'))
        ->post(route('account.aanbod.store'), [
            'type' => 'uitdelen',
            'tekst' => "\u{0085}\u{0085}",
            'aantal' => 1,
            'gratis_akkoord' => '1',
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertSessionHasErrors(['tekst' => AanbodTekst::FOUT_VERPLICHT]);

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('progressive enhancement: tekst-input heeft geen HTML required; JS zet required=true', function () {
    $user = tekstFoutUser('pe-required@example.com');

    $html = $this->actingAs($user)
        ->get(route('aanbieden'))
        ->assertOk()
        ->getContent();

    // Geen native required-attribuut op plantnaam (NoJS → serverfout i.p.v. browserlocale).
    expect($html)->not->toMatch('/id="aanbod-tekst"[^>]*\srequired(\s|>)/s')
        // Andere velden mogen required houden.
        ->and($html)->toMatch('/<select[^>]*name="type"[^>]*required/s')
        // JS enhancement + gedeelde FORM-ws (json-encoded in Blade).
        ->and($html)->toContain('input.required = true')
        ->and($html)->toContain('formWsClass')
        ->and($html)->toContain('formWsTrimRe')
        ->and($html)->toContain('\\u0085')
        ->and($html)->toContain('\\u00A0')
        ->and($html)->toContain('\\uFEFF');
});
