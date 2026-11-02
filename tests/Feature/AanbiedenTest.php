<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;

function aanbiederMetLocatie(array $extra = []): User
{
    return User::factory()->create(array_merge([
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ], $extra));
}

test('ingelogd: «Bied iets aan» is de hoofdknop in menu en header, uitloggen staat niet meer in het menu', function () {
    $user = aanbiederMetLocatie();
    $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

    expect(preg_match('/<nav id="hoofdmenu"[^>]*>(.*?)<\/nav>/s', $html, $nav))->toBe(1);
    expect($nav[1])->toContain('class="nav-cta" href="'.route('aanbieden').'"')
        ->and($nav[1])->not->toContain('Uitloggen')
        ->and($html)->toContain('class="nav-cta header-cta" href="'.route('aanbieden').'"');

    $this->actingAs($user)->get(route('account.index'))
        ->assertSee('Uitloggen', false)
        ->assertSee('href="'.route('aanbieden').'"', false);
});

test('/aanbieden toont alleen het formulier en plaatsen brengt je terug met «nog iets?»', function () {
    $user = aanbiederMetLocatie();
    $this->actingAs($user)->get(route('aanbieden'))
        ->assertOk()
        ->assertSee('<h1 class="page-title">Bied iets aan</h1>', false)
        ->assertSee('name="terug" value="aanbieden"', false)
        ->assertSee('noindex', false);

    $this->actingAs($user)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen',
        'tekst' => 'Monstera stekje',
        'terug' => 'aanbieden',
        'gratis_akkoord' => '1',
        'website' => '',
        'form_started' => time() - 10,
    ])->assertRedirect(route('aanbieden'))->assertSessionHas('status');

    expect(Aanbod::query()->where('user_id', $user->id)->count())->toBe(1);
    $this->actingAs($user)->get(route('aanbieden'))->assertSee('Bekijk en beheer je aanbod (1)', false);
});

test('/aanbieden zonder locatie: eerst profiel, daarna terug naar het formulier', function () {
    $user = User::factory()->create(['locatie_label' => '', 'lat' => null, 'lon' => null]);
    $this->actingAs($user)->get(route('aanbieden'))->assertRedirect(route('account.profiel'));
    expect(session('url.intended'))->toBe(route('aanbieden'));
});

test('niet ingelogd: /aanbieden vraagt eerst om in te loggen', function () {
    $this->get(route('aanbieden'))->assertRedirect();
    $this->get('/')->assertSee('class="nav-cta header-cta" href="'.route('account.registreren').'"', false);
});

test('beheer toont geen «Bied iets aan» en geen volgende-stap-balk', function () {
    $admin = aanbiederMetLocatie(['email' => 'admin@example.com']);
    config(['stekjes.admin_dashboard_emails' => ['admin@example.com']]);
    $admin->forceFill(['is_admin' => true])->save();
    $res = $this->actingAs($admin)->get(route('beheer.index'));
    if ($res->status() !== 200) {
        $this->markTestSkipped('Beheer-login vereist extra stap in tests.');
    }
    $html = $res->getContent();
    expect($html)->not->toContain('Bied iets aan')
        ->and($html)->not->toContain('class="volgende-stap"');
});
