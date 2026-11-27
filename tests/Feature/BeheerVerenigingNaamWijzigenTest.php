<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vereniging;
use Illuminate\Support\Facades\Mail;

test('admin kan een vereniging hernoemen en de slug blijft gelijk', function () {
    Mail::fake();
    $admin = beheerVerenigingAdmin();
    $vereniging = maakVereniging('hernoem-club', Vereniging::STATUS_APPROVED, 'Groene Waerd');
    $slug = $vereniging->slug;

    $this->actingAs($admin)
        ->get(route('beheer.verenigingen'))
        ->assertOk()
        ->assertSee('Naam wijzigen', false)
        ->assertSee('Opslaan', false)
        ->assertSee(route('beheer.verenigingen.naam', $vereniging), false);

    $this->actingAs($admin)
        ->post(route('beheer.verenigingen.naam', $vereniging), ['naam' => '  De Groene Waard  '])
        ->assertRedirect(route('beheer.verenigingen'))
        ->assertSessionHas('status', 'Naam opgeslagen');

    $vereniging->refresh();
    expect($vereniging->naam)->toBe('De Groene Waard')
        ->and($vereniging->slug)->toBe($slug);

    Mail::assertNothingSent();
});

test('een niet-admin mag de naam niet wijzigen', function () {
    Mail::fake();
    $lid = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $vereniging = maakVereniging('hernoem-verboden', Vereniging::STATUS_APPROVED, 'Blijft Zo');

    $this->post(route('beheer.verenigingen.naam', $vereniging), ['naam' => 'Nieuwe Naam'])
        ->assertRedirect(route('beheer.login'));
    $this->actingAs($lid)
        ->post(route('beheer.verenigingen.naam', $vereniging), ['naam' => 'Nieuwe Naam'])
        ->assertRedirect(route('beheer.login'));

    expect($vereniging->fresh()->naam)->toBe('Blijft Zo');
    Mail::assertNothingSent();
});

test('een lege naam geeft een validatiefout en wijzigt niets', function () {
    Mail::fake();
    $admin = beheerVerenigingAdmin();
    $vereniging = maakVereniging('hernoem-leeg', Vereniging::STATUS_APPROVED, 'Oude Naam');
    $slug = $vereniging->slug;

    $this->actingAs($admin)
        ->from(route('beheer.verenigingen'))
        ->post(route('beheer.verenigingen.naam', $vereniging), ['naam' => '   '])
        ->assertRedirect(route('beheer.verenigingen'))
        ->assertSessionHasErrors('naam');

    $vereniging->refresh();
    expect($vereniging->naam)->toBe('Oude Naam')
        ->and($vereniging->slug)->toBe($slug);

    Mail::assertNothingSent();
});
