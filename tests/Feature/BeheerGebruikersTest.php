<?php

declare(strict_types=1);

use App\Http\Controllers\Beheer\GebruikersController;
use App\Models\Aanbod;
use App\Models\User;

test('gast wordt naar de beheer-login gestuurd', function () {
    $this->get('/beheer/gebruikers')->assertRedirect(route('beheer.login'));
    $this->post('/beheer/gebruikers/zoeken', ['zoek' => 'x'])->assertRedirect(route('beheer.login'));
});

test('gewoon lid krijgt geen gebruikersoverzicht', function () {
    $lid = User::factory()->create(['email' => 'gewoon-lid@example.test']);

    $this->actingAs($lid)->get('/beheer/gebruikers')->assertRedirect(route('beheer.login'));
});

test('admin ziet naam, e-mail, aanmelddatum, aanbod en badges; noindex en no-store', function () {
    $u = User::factory()->create([
        'name' => 'Sabientje',
        'email' => 'sabientje@example.test',
    ]);
    $u->forceFill(['locatie_label' => 'Haarlem', 'created_at' => '2026-10-20 12:00:00'])->save();
    foreach ([1, 2] as $i) {
        Aanbod::query()->create([
            'user_id' => $u->id,
            'type' => 'uitdelen',
            'tekst' => 'Stekje '.$i,
            'visible' => true,
            'expires_at' => now()->addDays(7),
        ]);
    }

    $res = $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers'));

    $res->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Sabientje')
        ->assertSee('sabientje@example.test')
        ->assertSee('20-10-2026')
        ->assertSee('Geworteld')
        ->assertSee('Eerste stekje');
    expect((string) $res->headers->get('Cache-Control'))->toContain('no-store');
    $res->assertSeeInOrder(['sabientje@example.test', '20-10-2026', '<td>2</td>'], false);
});

test('admin zoekt op naam of e-mail zonder zoekterm in de URL', function () {
    User::factory()->create(['name' => 'Annemarie', 'email' => 'annemarie@example.test']);
    User::factory()->create(['name' => 'Kees', 'email' => 'kees.tuin@voorbeeld.test']);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.gebruikers.zoek'), ['zoek' => 'voorbeeld.test'])
        ->assertRedirect(route('beheer.gebruikers'));

    $this->get(route('beheer.gebruikers'))
        ->assertOk()
        ->assertSee('Kees')
        ->assertDontSee('Annemarie');

    $this->post(route('beheer.gebruikers.zoek'), ['zoek' => 'ANNEM'])->assertRedirect(route('beheer.gebruikers'));
    $this->get(route('beheer.gebruikers'))->assertSee('Annemarie')->assertDontSee('kees.tuin@voorbeeld.test');

    $this->post(route('beheer.gebruikers.zoek'), ['zoek' => 'x', 'wissen' => '1']);
    $this->get(route('beheer.gebruikers'))->assertSee('Annemarie')->assertSee('Kees');
});

test('overzicht is gepagineerd', function () {
    User::factory()->count(GebruikersController::PER_PAGE + 3)->create();

    $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers'))
        ->assertOk()->assertSee('Pagina 1 van 2');
    $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers').'?page=2')
        ->assertOk()->assertSee('Pagina 2 van 2');
});

test('beheermenu linkt naar gebruikers', function () {
    $this->withSession(['admin_ok' => true])->get(route('beheer.index'))
        ->assertOk()->assertSee(route('beheer.gebruikers'), false);
});
