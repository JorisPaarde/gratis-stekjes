<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Js;

beforeEach(function () {
    config([
        'stekjes.admin_dashboard_emails' => ['iemand-anders@example.test'],
    ]);
});

test('bevestigingsteksten op detailpagina breken niet op een naam met aanhalingstekens', function () {
    $admin = User::factory()->create(['email' => 'iemand-anders@example.test']);
    $lid = User::factory()->create(['name' => 'Jan O\'Brien "de Stek"']);

    $html = $this->actingAs($admin)->get(route('beheer.gebruikers.show', $lid))->assertOk()->getContent();

    $pionier = (string) Js::from('Pionier-badge naar '.$lid->name.' verplaatsen? Er is er maar één.');
    $blokkeer = (string) Js::from('Account blokkeren? '.$lid->name.' wordt uitgelogd en kan niet meer inloggen.');
    $beheerder = (string) Js::from($lid->name.' beheerder maken? Diegene krijgt volledige toegang tot /beheer.');

    expect($html)->toContain('onsubmit="return confirm('.$pionier.');"')
        ->and($html)->toContain('onsubmit="return confirm('.$blokkeer.');"')
        ->and($html)->toContain('onsubmit="return confirm('.$beheerder.');"')
        ->and($html)->not->toContain("confirm('Pionier-badge naar")
        ->and($html)->not->toContain("confirm('Account blokkeren?");

    // Geen rauwe aanhalingstekens in de JS-string: die zouden het attribuut of de string afbreken.
    foreach ([$pionier, $blokkeer, $beheerder] as $js) {
        expect(substr($js, 1, -1))->not->toContain("'")->not->toContain('"');
    }
});

test('gast en gewoon lid krijgen hetzelfde antwoord voor bestaand en niet-bestaand lid-ID', function () {
    $bestaand = User::factory()->create();
    $lid = User::factory()->create();
    $missend = 999999;

    $routes = [
        ['get', 'beheer.gebruikers.show'],
        ['post', 'beheer.users.pionier'],
        ['post', 'beheer.gebruikers.actief'],
        ['post', 'beheer.gebruikers.beheerder'],
        ['post', 'beheer.gebruikers.reset-naam'],
        ['get', 'beheer.users.destroy.confirm'],
        ['post', 'beheer.users.destroy'],
    ];

    foreach ([null, $lid] as $wie) {
        foreach ($routes as [$method, $name]) {
            auth()->logout();
            if ($wie) {
                $this->actingAs($wie);
            }
            $a = $this->{$method}(route($name, $bestaand));
            auth()->logout();
            if ($wie) {
                $this->actingAs($wie);
            }
            $b = $this->{$method}(route($name, $missend));

            $a->assertRedirect(route('beheer.login'));
            $b->assertRedirect(route('beheer.login'));
            expect($b->getStatusCode())->toBe($a->getStatusCode())
                ->and($b->headers->get('Location'))->toBe($a->headers->get('Location'));
        }
    }

    expect(User::find($bestaand->id))->not->toBeNull();
});

test('beheerder krijgt nog steeds 404 voor een niet-bestaand lid-ID', function () {
    $admin = User::factory()->create(['email' => 'iemand-anders@example.test']);

    $this->actingAs($admin)->get(route('beheer.gebruikers.show', 999999))->assertNotFound();
});
