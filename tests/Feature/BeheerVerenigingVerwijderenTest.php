<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Vereniging;
use Illuminate\Support\Facades\DB;

function beheerVerenigingAdmin(): User
{
    config(['stekjes.admin_dashboard_emails' => ['vereniging-admin@example.com']]);

    return User::factory()->create([
        'name' => 'Vereniging Admin',
        'email' => 'vereniging-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

function maakVereniging(string $slug, string $status, string $naam = 'Test Tuinclub', ?string $jsonSlug = null): Vereniging
{
    return Vereniging::query()->create([
        'slug' => $slug,
        'naam' => $naam,
        'plaats' => 'Testdorp',
        'type' => 'tuinvereniging',
        'json_slug' => $jsonSlug,
        'status' => $status,
    ]);
}

test('verwijderknop en checkbox staan alleen bij verborgen verenigingen zonder vaste lijst', function () {
    $admin = beheerVerenigingAdmin();
    $verborgen = maakVereniging('verborgen-club', Vereniging::STATUS_HIDDEN, 'Verborgen Club');
    $zichtbaar = maakVereniging('zichtbare-club', Vereniging::STATUS_APPROVED, 'Zichtbare Club');
    $wacht = maakVereniging('wachtende-club', Vereniging::STATUS_PENDING, 'Wachtende Club');
    $vast = maakVereniging('vaste-club', Vereniging::STATUS_HIDDEN, 'Vaste Club', 'vaste-club');

    $html = $this->actingAs($admin)->get(route('beheer.verenigingen'))->assertOk()
        ->assertSee('Selectie verwijderen', false)
        ->assertSee('Vaste lijst, blijft verborgen', false)
        ->getContent();

    expect($html)->toContain(route('beheer.verenigingen.destroy.confirm', $verborgen))
        ->and($html)->not->toContain(route('beheer.verenigingen.destroy.confirm', $zichtbaar))
        ->and($html)->not->toContain(route('beheer.verenigingen.destroy.confirm', $wacht))
        ->and($html)->not->toContain(route('beheer.verenigingen.destroy.confirm', $vast));
});

test('admin verwijdert verborgen vereniging via bevestiging en koppelingen verdwijnen', function () {
    $admin = beheerVerenigingAdmin();
    $vereniging = maakVereniging('weg-club', Vereniging::STATUS_HIDDEN, 'Weg Club');
    $lid = User::factory()->create();
    $vereniging->leden()->attach($lid->id, ['zichtbaar' => true]);

    $this->actingAs($admin)->get(route('beheer.verenigingen.destroy.confirm', $vereniging))
        ->assertOk()
        ->assertSee('Weet je het zeker?', false)
        ->assertSee('Weg Club', false)
        ->assertSee('Testdorp', false)
        ->assertSee('1 lid gekoppeld', false);

    $this->actingAs($admin)->post(route('beheer.verenigingen.destroy', $vereniging))
        ->assertSessionHasErrors('confirm');
    expect(Vereniging::query()->count())->toBe(1);

    $this->actingAs($admin)->post(route('beheer.verenigingen.destroy', $vereniging), ['confirm' => '1'])
        ->assertRedirect(route('beheer.verenigingen'))
        ->assertSessionHas('status');

    expect(Vereniging::query()->whereKey($vereniging->id)->exists())->toBeFalse()
        ->and(DB::table('vereniging_user')->where('vereniging_id', $vereniging->id)->count())->toBe(0)
        ->and(User::query()->whereKey($lid->id)->exists())->toBeTrue();
});

test('zichtbare, wachtende en vaste-lijst verenigingen worden geweigerd', function () {
    $admin = beheerVerenigingAdmin();
    $zichtbaar = maakVereniging('zichtbare-club', Vereniging::STATUS_APPROVED);
    $wacht = maakVereniging('wachtende-club', Vereniging::STATUS_PENDING);
    $vast = maakVereniging('vaste-club', Vereniging::STATUS_HIDDEN, 'Vaste Club', 'vaste-club');

    foreach ([$zichtbaar, $wacht, $vast] as $v) {
        $this->actingAs($admin)->get(route('beheer.verenigingen.destroy.confirm', $v))
            ->assertRedirect(route('beheer.verenigingen'))->assertSessionHasErrors('vereniging');
        $this->actingAs($admin)->post(route('beheer.verenigingen.destroy', $v), ['confirm' => '1'])
            ->assertRedirect(route('beheer.verenigingen'))->assertSessionHasErrors('vereniging');
    }

    expect(Vereniging::query()->count())->toBe(3);
});

test('bulk toont aantal en namen, weigert mix met niet-verwijderbare en verwijdert alleen na bevestiging', function () {
    $admin = beheerVerenigingAdmin();
    $a = maakVereniging('club-a', Vereniging::STATUS_HIDDEN, 'Club A');
    $b = maakVereniging('club-b', Vereniging::STATUS_HIDDEN, 'Club B');
    $zichtbaar = maakVereniging('club-c', Vereniging::STATUS_APPROVED, 'Club C');

    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy.confirm'), ['ids' => [$a->id, $b->id]])
        ->assertOk()->assertSee('Club A', false)->assertSee('Club B', false)->assertSee('<strong>2</strong>', false);

    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy.confirm'), ['ids' => [$a->id, $zichtbaar->id]])
        ->assertRedirect(route('beheer.verenigingen'))->assertSessionHasErrors('vereniging');
    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy'), ['ids' => [$a->id, $zichtbaar->id], 'confirm' => '1'])
        ->assertRedirect(route('beheer.verenigingen'))->assertSessionHasErrors('vereniging');
    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy.confirm'), [])
        ->assertRedirect(route('beheer.verenigingen'))->assertSessionHasErrors('vereniging');
    expect(Vereniging::query()->count())->toBe(3);

    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy'), ['ids' => [$a->id, $b->id]])
        ->assertSessionHasErrors('confirm');
    expect(Vereniging::query()->count())->toBe(3);

    $this->actingAs($admin)->post(route('beheer.verenigingen.bulk-destroy'), ['ids' => [$a->id, $b->id], 'confirm' => '1'])
        ->assertRedirect(route('beheer.verenigingen'))->assertSessionHas('status', '2 verenigingen zijn definitief verwijderd.');
    expect(Vereniging::query()->pluck('slug')->all())->toBe(['club-c']);
});

test('gasten en gewone leden komen niet bij verwijderen van verenigingen', function () {
    $v = maakVereniging('club-x', Vereniging::STATUS_HIDDEN);
    $lid = User::factory()->create(['email_verified_at' => now()]);

    $this->get(route('beheer.verenigingen.destroy.confirm', $v))->assertRedirect(route('beheer.login'));
    $this->post(route('beheer.verenigingen.destroy', $v), ['confirm' => '1'])->assertRedirect(route('beheer.login'));
    $this->actingAs($lid)->post(route('beheer.verenigingen.destroy', $v), ['confirm' => '1'])->assertRedirect(route('beheer.login'));
    $this->actingAs($lid)->post(route('beheer.verenigingen.bulk-destroy'), ['ids' => [$v->id], 'confirm' => '1'])->assertRedirect(route('beheer.login'));

    expect(Vereniging::query()->count())->toBe(1);
});
