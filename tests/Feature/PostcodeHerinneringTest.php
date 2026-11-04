<?php

declare(strict_types=1);

use App\Http\Controllers\Account\PostcodeHerinneringController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function herinneringUser(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'name' => 'Pia',
        'locatie_label' => '',
        'postcode_gebied' => null,
        'woonplaats' => null,
        'lat' => null,
        'lon' => null,
        'country' => 'NL',
        'active' => true,
    ], $extra));
}

test('hasValidPostcode: leeg, ongeldig formaat, geen plaats, geldig NL/BE', function () {
    $leeg = herinneringUser();
    expect($leeg->hasValidPostcode())->toBeFalse()
        ->and($leeg->postcodeValidityReason())->toBe('leeg');

    $ongeldig = herinneringUser([
        'postcode_gebied' => '12',
        'locatie_label' => '12',
        'lat' => 52.1,
        'lon' => 4.3,
    ]);
    expect($ongeldig->postcodeValidityReason())->toBe('ongeldig_formaat');

    $geenPlaats = herinneringUser([
        'postcode_gebied' => '2037',
        'locatie_label' => '',
        'lat' => null,
        'lon' => null,
    ]);
    expect($geenPlaats->postcodeValidityReason())->toBe('geen_plaats');

    $nl = herinneringUser([
        'postcode_gebied' => '2037',
        'woonplaats' => 'Haarlem',
        'locatie_label' => '2037 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);
    expect($nl->hasValidPostcode())->toBeTrue()
        ->and($nl->postcodeValidityReason())->toBeNull();

    $be = herinneringUser([
        'country' => 'BE',
        'postcode_gebied' => '9000',
        'woonplaats' => 'Gent',
        'locatie_label' => '9000 Gent',
        'lat' => 51.05,
        'lon' => 3.72,
    ]);
    expect($be->hasValidPostcode())->toBeTrue();

    $plaatsAlleen = herinneringUser([
        'locatie_label' => 'Haarlem',
        'woonplaats' => 'Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
        'postcode_gebied' => null,
    ]);
    expect($plaatsAlleen->hasValidPostcode())->toBeFalse()
        ->and($plaatsAlleen->postcodeValidityReason())->toBe('leeg');
});

test('banner toont na login zonder geldige postcode, niet met geldige postcode', function () {
    $zonder = herinneringUser(['email' => 'zonder-pc@example.com']);
    $this->actingAs($zonder)->get(route('aanbod'))->assertOk()
        ->assertSee('class="postcode-herinnering"', false)
        ->assertSee('Vul je postcode in,', false)
        ->assertSee('dan zie je wat er bij jou in de buurt is', false)
        ->assertSee(route('account.profiel'), false);

    $met = herinneringUser([
        'email' => 'met-pc@example.com',
        'postcode_gebied' => '1326',
        'woonplaats' => 'Almere',
        'locatie_label' => '1326 Almere',
        'lat' => 52.35,
        'lon' => 5.26,
    ]);
    $this->actingAs($met)->get(route('aanbod'))->assertOk()
        ->assertDontSee('class="postcode-herinnering"', false);
});

test('banner niet op profielformulier; knop wijst naar account.profiel', function () {
    $u = herinneringUser(['email' => 'profiel-pc@example.com']);
    $this->actingAs($u)->get(route('account.profiel'))->assertOk()
        ->assertDontSee('class="postcode-herinnering"', false);

    $html = $this->actingAs($u)->get(route('account.index'))->assertOk()->getContent();
    expect($html)->toContain('class="postcode-herinnering"')
        ->and($html)->toContain('href="'.route('account.profiel').'"');
});

test('banner wegklikken is sessie-only; komt terug na nieuwe login', function () {
    $u = herinneringUser(['email' => 'dismiss-pc@example.com']);

    $this->actingAs($u)->get(route('account.index'))
        ->assertSee('class="postcode-herinnering"', false);

    $this->actingAs($u)->from(route('account.index'))
        ->post(route('account.postcode-herinnering.weg'), [
            'next' => route('account.index'),
        ])
        ->assertRedirect(route('account.index'));

    expect(session(PostcodeHerinneringController::SESSION_KEY))->toBeTrue();

    $this->actingAs($u)->get(route('account.index'))
        ->assertDontSee('class="postcode-herinnering"', false);

    // Nieuwe sessie (= opnieuw inloggen): banner terug.
    $this->flushSession();
    $this->actingAs($u)->get(route('account.index'))
        ->assertSee('class="postcode-herinnering"', false);
});

test('gp:postcode-herinnering-lijst somt alleen actieve bevestigde leden zonder geldige postcode', function () {
    $leeg = herinneringUser(['email' => 'lijst-leeg@example.com']);
    $ongeldig = herinneringUser([
        'email' => 'lijst-ongeldig@example.com',
        'postcode_gebied' => '99',
        'locatie_label' => '99',
    ]);
    $geenPlaats = herinneringUser([
        'email' => 'lijst-geenplaats@example.com',
        'postcode_gebied' => '2037',
        'locatie_label' => '',
        'lat' => null,
        'lon' => null,
    ]);
    $ok = herinneringUser([
        'email' => 'lijst-ok@example.com',
        'postcode_gebied' => '2037',
        'woonplaats' => 'Haarlem',
        'locatie_label' => '2037 Haarlem',
        'lat' => 52.38,
        'lon' => 4.64,
    ]);
    $geblokkeerd = herinneringUser([
        'email' => 'lijst-blok@example.com',
        'active' => false,
    ]);
    $onbevestigd = herinneringUser([
        'email' => 'lijst-unverified@example.com',
        'email_verified_at' => null,
    ]);
    $stats = User::factory()->statsViewer()->create([
        'email' => 'lijst-stats@example.com',
        'locatie_label' => '',
        'postcode_gebied' => null,
    ]);

    Artisan::call('gp:postcode-herinnering-lijst');
    $out = Artisan::output();

    expect($out)->toContain('Totaal: 3')
        ->and($out)->toContain('leeg: 1')
        ->and($out)->toContain('ongeldig_formaat: 1')
        ->and($out)->toContain('geen_plaats: 1')
        ->and($out)->toContain((string) $leeg->id)
        ->and($out)->toContain((string) $ongeldig->id)
        ->and($out)->toContain((string) $geenPlaats->id)
        ->and($out)->not->toContain($leeg->email)
        ->and($out)->not->toContain($leeg->name)
        ->and($out)->not->toContain((string) $ok->id)
        ->and($out)->not->toContain((string) $geblokkeerd->id)
        ->and($out)->not->toContain((string) $onbevestigd->id)
        ->and($out)->not->toContain((string) $stats->id);
});

test('deploy postcode-herinnering-lijst endpoint vereist token en heeft geen apply', function () {
    config(['stekjes.deploy_token' => 'test-token', 'stekjes.deploy_endpoint_enabled' => true]);
    herinneringUser(['email' => 'deploy-pc@example.com']);

    $this->postJson('/deploy/postcode-herinnering-lijst', [], [
        'Authorization' => 'Bearer test-token',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['output', 'at'])
        ->assertJsonMissingPath('apply');

    $this->postJson('/deploy/postcode-herinnering-lijst')->assertForbidden();
});
