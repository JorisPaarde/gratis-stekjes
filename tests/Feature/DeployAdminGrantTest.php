<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\AdminDashboard;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config([
        'stekjes.deploy_token' => 'test-deploy-token',
        'stekjes.deploy_endpoint_enabled' => true,
        'stekjes.admin_dashboard_emails' => ['iemand-anders@example.test'],
    ]);
    RateLimiter::clear('deploy-admin-grant:127.0.0.1');
});

function grantHash(string $email): string
{
    return hash('sha256', strtolower(trim($email)));
}

test('admin-grant weigert zonder of met verkeerde token', function () {
    $this->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('x@example.test')])
        ->assertForbidden()->assertJsonPath('error', 'forbidden');

    $this->withToken('wrong', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('x@example.test')])
        ->assertForbidden()->assertJsonPath('error', 'forbidden');
});

test('admin-grant uitgeschakeld zonder deploy-flag buiten staging', function () {
    config(['stekjes.deploy_endpoint_enabled' => false]);

    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('x@example.test')])
        ->assertForbidden()->assertJsonPath('error', 'disabled');
});

test('admin-grant weigert ongeldige hash', function () {
    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => 'kandidaat@example.test'])
        ->assertStatus(422)->assertJsonPath('error', 'invalid_hash');
});

test('admin-grant zet niets bij nul matches', function () {
    $user = User::factory()->create(['email' => 'lid@example.test']);

    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('onbekend@example.test')])
        ->assertOk()
        ->assertJsonPath('matches', 0)
        ->assertJsonPath('granted', false);

    expect((bool) $user->fresh()->is_admin)->toBeFalse();
});

test('admin-grant zet beheerder bij precies één match, idempotent, zonder persoonsgegevens', function () {
    $kandidaat = User::factory()->create(['email' => 'kandidaat@example.test', 'name' => 'Kandidaat Test']);
    $ander = User::factory()->create(['email' => 'ander@example.test']);

    expect(AdminDashboard::userMayAccess($kandidaat))->toBeFalse();

    $response = $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('  Kandidaat@Example.TEST ')])
        ->assertOk()
        ->assertJsonPath('matches', 1)
        ->assertJsonPath('granted', true)
        ->assertJsonPath('already_admin', false)
        ->assertJsonPath('email_verified', true);

    expect($response->getContent())->not->toContain('kandidaat@example.test')
        ->and($response->getContent())->not->toContain('Kandidaat Test');

    expect((bool) $kandidaat->fresh()->is_admin)->toBeTrue()
        ->and((bool) $ander->fresh()->is_admin)->toBeFalse()
        ->and(AdminDashboard::userMayAccess($kandidaat->fresh()))->toBeTrue();

    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/admin-grant', ['email_sha256' => grantHash('kandidaat@example.test')])
        ->assertOk()
        ->assertJsonPath('matches', 1)
        ->assertJsonPath('granted', false)
        ->assertJsonPath('already_admin', true);
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
