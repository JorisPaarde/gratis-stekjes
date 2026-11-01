<?php

declare(strict_types=1);

use App\Http\Controllers\Beheer\GebruikersController;
use App\Models\Aanbod;
use App\Models\MailLog;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use Illuminate\Support\Facades\Notification;

function beheerAdmin(): User
{
    config(['stekjes.admin_dashboard_emails' => ['beheer-admin@example.com']]);

    return User::factory()->create(['name' => 'Joris', 'email' => 'beheer-admin@example.com', 'email_verified_at' => now()]);
}

test('overzicht toont aandacht-nodig kaarten met links en tabs', function () {
    $admin = beheerAdmin();
    $oud = User::factory()->unverified()->create(['name' => 'Oud Onbevestigd']);
    $oud->forceFill(['created_at' => now()->subDays(5)])->save();
    $fout = User::factory()->create(['name' => 'Mailfout']);
    MailLog::query()->create(['user_id' => $fout->id, 'type' => 'NewMessageMail', 'status' => 'fail', 'error' => 'Connection could not be established', 'created_at' => now()]);

    $this->actingAs($admin)->get(route('beheer.index'))
        ->assertOk()
        ->assertSee('Aandacht nodig', false)
        ->assertSee('Onbevestigd, ouder dan 2 dagen', false)
        ->assertSee(route('beheer.gebruikers', ['filter' => 'onbevestigd']), false)
        ->assertSee('Mailserver niet bereikbaar', false)
        ->assertSee('class="beheer-tabs"', false);

    foreach (['beheer.gebruikers', 'beheer.verenigingen', 'beheer.aanmeldingen', 'beheer.agenda', 'beheer.kaartmarkers', 'beheer.meldingen', 'beheer.feedback', 'beheer.ervaringen', 'beheer.statistieken'] as $r) {
        $this->actingAs($admin)->get(route($r))->assertOk()->assertSee('class="beheer-tabs"', false);
    }
});

test('gebruikers: zoeken via sessie, filters, sorteren, paginatie', function () {
    $admin = beheerAdmin();
    $a = User::factory()->create(['name' => 'Annie', 'email' => 'annie.zoek@example.com', 'locatie_label' => '2011']);
    $b = User::factory()->unverified()->create(['name' => 'Bob']);
    Aanbod::query()->create(['user_id' => $a->id, 'type' => 'uitdelen', 'tekst' => 'Munt', 'visible' => true, 'expires_at' => now()->addDays(5)]);

    $this->actingAs($admin)->post(route('beheer.gebruikers.zoek'), ['zoek' => 'annie.zoek'])->assertRedirect(route('beheer.gebruikers'));
    $this->actingAs($admin)->get(route('beheer.gebruikers'))
        ->assertOk()->assertSee('>Annie<', false)->assertDontSee('>Bob<', false)->assertSee('annie.zoek@example.com', false);
    $this->actingAs($admin)->post(route('beheer.gebruikers.zoek'), ['wissen' => '1']);

    $this->actingAs($admin)->get(route('beheer.gebruikers', ['filter' => 'onbevestigd']))
        ->assertOk()->assertSee('>Bob<', false)->assertDontSee('>Annie<', false);
    $this->actingAs($admin)->get(route('beheer.gebruikers', ['filter' => 'met_aanbod']))
        ->assertOk()->assertSee('>Annie<', false)->assertDontSee('>Bob<', false);
    $html = $this->actingAs($admin)->get(route('beheer.gebruikers', ['sort' => 'naam']))->assertOk()->getContent();
    expect(strpos($html, '>Annie<'))->toBeLessThan(strpos($html, '>Bob<'));

    User::factory()->count(60)->create();
    $this->actingAs($admin)->get(route('beheer.gebruikers'))->assertOk()->assertSee('Pagina 1 van 2', false);
    $this->actingAs($admin)->get(route('beheer.gebruikers', ['page' => 2]))->assertOk()->assertSee('Pagina 2 van 2', false);
});

test('detailpagina: profiel, badges, mails in gewone taal, acties', function () {
    $admin = beheerAdmin();
    $u = User::factory()->create(['name' => 'Detail Dirk', 'email' => 'dirk@example.com', 'locatie_label' => '2011']);
    MailLog::query()->create(['user_id' => $u->id, 'type' => 'VerifyEmailDutch', 'status' => 'fail', 'error' => '550 mailbox unavailable', 'created_at' => now()]);

    $this->actingAs($admin)->get(route('beheer.gebruikers.show', $u))
        ->assertOk()
        ->assertSee('dirk@example.com', false)
        ->assertSee('Badges', false)
        ->assertSee('Bevestigingsmail', false)
        ->assertSee('Adres bestaat niet of weigert mail', false)
        ->assertSee('Blokkeren', false)
        ->assertSee(route('beheer.users.destroy.confirm', $u), false);

    $this->actingAs($admin)->from(route('beheer.gebruikers.show', $u))->post(route('beheer.gebruikers.actief', $u))->assertRedirect();
    expect($u->fresh()->active)->toBeFalse();
    $this->actingAs($admin)->from(route('beheer.gebruikers.show', $u))->post(route('beheer.gebruikers.actief', $u));
    expect($u->fresh()->active)->toBeTrue();

    $this->actingAs($admin)->post(route('beheer.gebruikers.actief', $admin))->assertSessionHasErrors('user');
    expect($admin->fresh()->active)->toBeTrue();
});

test('naam = e-mail: filter en terugzetten naar Lid', function () {
    $admin = beheerAdmin();
    $u = User::factory()->create(['name' => 'kees@gmail.com']);
    $this->actingAs($admin)->get(route('beheer.gebruikers', ['filter' => 'naam_email']))->assertOk()->assertSee('kees@gmail.com', false);
    $this->actingAs($admin)->get(route('beheer.gebruikers.show', $u))->assertOk()->assertSee('Naam terugzetten', false);
    $this->actingAs($admin)->post(route('beheer.gebruikers.reset-naam', $u))->assertRedirect();
    expect($u->fresh()->name)->toBe('Lid');
});

test('herinnering naar onbevestigde leden ouder dan 2 dagen, één keer per uur', function () {
    Notification::fake();
    $admin = beheerAdmin();
    $oud = User::factory()->unverified()->create();
    $oud->forceFill(['created_at' => now()->subDays(3)])->save();
    $nieuw = User::factory()->unverified()->create();

    $this->actingAs($admin)->post(route('beheer.gebruikers.herinner'))->assertRedirect()->assertSessionHas('status');
    Notification::assertSentTo($oud, VerifyEmailDutch::class);
    Notification::assertNotSentTo($nieuw, VerifyEmailDutch::class);

    $this->actingAs($admin)->post(route('beheer.gebruikers.herinner'))->assertSessionHasErrors('user');
});

test('laatst actief wordt bijgewerkt, hooguit eens per uur', function () {
    $u = User::factory()->create();
    expect($u->fresh()->last_seen_at)->toBeNull();
    $this->actingAs($u)->get(route('account.index'))->assertOk();
    $eerste = $u->fresh()->last_seen_at;
    expect($eerste)->not->toBeNull();

    $this->travel(10)->minutes();
    $this->actingAs($u->fresh())->get(route('account.index'));
    expect($u->fresh()->last_seen_at->equalTo($eerste))->toBeTrue();
});

test('beheerpagina\'s niet bereikbaar voor gewone leden', function () {
    $lid = User::factory()->create();
    foreach (['beheer.gebruikers', 'beheer.verenigingen', 'beheer.meldingen'] as $r) {
        $this->actingAs($lid)->get(route($r))->assertRedirect(route('beheer.login'));
    }
    $this->actingAs($lid)->get(route('beheer.gebruikers.show', $lid))->assertRedirect(route('beheer.login'));
});

// ——— Uit 0.45.0 (#179), aangepast aan de nieuwe lijst ———

test('gast wordt naar de beheer-login gestuurd', function () {
    $this->get('/beheer/gebruikers')->assertRedirect(route('beheer.login'));
    $this->post('/beheer/gebruikers/zoeken', ['zoek' => 'x'])->assertRedirect(route('beheer.login'));
});

test('admin ziet naam, e-mail, aanmelddatum, aanbod en badges; noindex en no-store', function () {
    $u = User::factory()->create(['name' => 'Sabientje', 'email' => 'sabientje@example.test']);
    $u->forceFill(['locatie_label' => 'Haarlem', 'created_at' => '2026-10-20 12:00:00'])->save();
    foreach ([1, 2] as $i) {
        Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Stekje '.$i, 'visible' => true, 'expires_at' => now()->addDays(7)]);
    }

    $res = $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers'));
    $res->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Sabientje')
        ->assertSee('sabientje@example.test')
        ->assertSee('20 okt. 2026')
        ->assertSee('Badges: Een van de eerste 10, Geworteld, Eerste stekje', false)
        ->assertSeeInOrder(['Sabientje', 'sabientje@example.test', '20 okt. 2026', '2 <span class="muted">/ 2</span>'], false);
    expect((string) $res->headers->get('Cache-Control'))->toContain('no-store');

    $detail = $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers.show', $u));
    expect((string) $detail->headers->get('Cache-Control'))->toContain('no-store');
});

test('zoekterm staat nooit in de URL; wissen werkt', function () {
    User::factory()->create(['name' => 'Annemarie', 'email' => 'annemarie@example.test']);
    User::factory()->create(['name' => 'Kees', 'email' => 'kees.tuin@voorbeeld.test']);

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.gebruikers.zoek'), ['zoek' => 'voorbeeld.test'])
        ->assertRedirect(route('beheer.gebruikers'));
    $this->get(route('beheer.gebruikers'))->assertOk()->assertSee('Kees')->assertDontSee('Annemarie')
        ->assertDontSee('?q=', false)->assertDontSee('zoek=voorbeeld', false);

    $this->post(route('beheer.gebruikers.zoek'), ['zoek' => 'ANNEM'])->assertRedirect(route('beheer.gebruikers'));
    $this->get(route('beheer.gebruikers'))->assertSee('Annemarie')->assertDontSee('kees.tuin@voorbeeld.test');

    // LIKE-tekens worden letterlijk gezocht.
    $this->post(route('beheer.gebruikers.zoek'), ['zoek' => '%']);
    $this->get(route('beheer.gebruikers'))->assertSee('0 gebruikers', false);

    $this->post(route('beheer.gebruikers.zoek'), ['zoek' => 'x', 'wissen' => '1']);
    $this->get(route('beheer.gebruikers'))->assertSee('Annemarie')->assertSee('Kees');
});

test('overzicht is gepagineerd met PER_PAGE', function () {
    User::factory()->count(GebruikersController::PER_PAGE + 3)->create();
    $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers'))->assertOk()->assertSee('Pagina 1 van 2');
    $this->withSession(['admin_ok' => true])->get(route('beheer.gebruikers').'?page=2')->assertOk()->assertSee('Pagina 2 van 2');
});

test('beheermenu linkt naar gebruikers', function () {
    $this->withSession(['admin_ok' => true])->get(route('beheer.index'))
        ->assertOk()->assertSee(route('beheer.gebruikers'), false);
});
