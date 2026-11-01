<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\AdminDashboard;

beforeEach(function () {
    config([
        'stekjes.admin_dashboard_emails' => ['iemand-anders@example.test'],
    ]);
});

test('is_admin geeft alleen toegang met geverifieerd e-mailadres', function () {
    $verified = User::factory()->create(['is_admin' => false]);
    $verified->forceFill(['is_admin' => true])->save();
    $unverified = User::factory()->unverified()->create();
    $unverified->forceFill(['is_admin' => true])->save();
    $gewoon = User::factory()->create();

    expect(AdminDashboard::userMayAccess($verified->fresh()))->toBeTrue()
        ->and(AdminDashboard::userMayAccess($unverified->fresh()))->toBeFalse()
        ->and(AdminDashboard::userMayAccess($gewoon->fresh()))->toBeFalse();

    $this->actingAs($verified->fresh())->get('/beheer/overzicht')->assertOk();
    $this->actingAs($gewoon->fresh())->get('/beheer/overzicht')->assertRedirect(route('beheer.login'));
});

test('is_admin is niet mass-assignable', function () {
    expect((new User)->isFillable('is_admin'))->toBeFalse();
});

test('knop maak beheerder / beheerder af op detailpagina, met bevestiging en CSRF-form', function () {
    $admin = User::factory()->create(['email' => 'iemand-anders@example.test']);
    $lid = User::factory()->create(['name' => "Lid O'Test"]);

    $html = $this->actingAs($admin)->get(route('beheer.gebruikers.show', $lid))->assertOk()->getContent();
    expect($html)->toContain('Maak beheerder')
        ->and($html)->toContain('action="'.route('beheer.gebruikers.beheerder', $lid).'"')
        ->and($html)->toContain('return confirm(')
        ->and($html)->not->toContain("Lid O'Test beheerder maken");

    $this->actingAs($admin)->from(route('beheer.gebruikers.show', $lid))
        ->post(route('beheer.gebruikers.beheerder', $lid))
        ->assertRedirect(route('beheer.gebruikers.show', $lid))
        ->assertSessionHas('status');
    expect((bool) $lid->fresh()->is_admin)->toBeTrue()
        ->and(AdminDashboard::userMayAccess($lid->fresh()))->toBeTrue();

    $this->actingAs($admin)->get(route('beheer.gebruikers.show', $lid))->assertOk()->assertSee('Beheerder af', false);

    $this->actingAs($admin)->post(route('beheer.gebruikers.beheerder', $lid))->assertRedirect();
    expect((bool) $lid->fresh()->is_admin)->toBeFalse();
});

test('eigen beheerdersvlag kan niet uit', function () {
    $zelf = User::factory()->create();
    $zelf->forceFill(['is_admin' => true])->save();

    $this->actingAs($zelf->fresh())->post(route('beheer.gebruikers.beheerder', $zelf))->assertSessionHasErrors('user');
    expect((bool) $zelf->fresh()->is_admin)->toBeTrue();
});

test('gewoon lid en gast kunnen niemand beheerder maken', function () {
    $lid = User::factory()->create();
    $doel = User::factory()->create();

    $this->actingAs($lid)->post(route('beheer.gebruikers.beheerder', $doel))->assertRedirect(route('beheer.login'));
    expect((bool) $doel->fresh()->is_admin)->toBeFalse();

    auth()->logout();
    $this->post(route('beheer.gebruikers.beheerder', $doel))->assertRedirect(route('beheer.login'));
    expect((bool) $doel->fresh()->is_admin)->toBeFalse();
});
