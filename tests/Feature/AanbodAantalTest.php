<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function aantalUser(): User
{
    return User::factory()->create(['locatie_label' => '2011', 'lat' => 52.381, 'lon' => 4.637]);
}

function aantalAanbod(User $u, int $aantal = 1): Aanbod
{
    return Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => $aantal, 'visible' => true, 'expires_at' => now()->addDays(30)]);
}

test('aanbod plaatsen met aantal; zichtbaar in lijst en publieke API', function () {
    $u = aantalUser();
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '5', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasNoErrors();

    $a = Aanbod::query()->where('user_id', $u->id)->firstOrFail();
    expect($a->aantal)->toBe(5);
    $this->actingAs($u)->get(route('account.aanbod'))->assertSee('<strong>5</strong>', false)->assertSee('stuks', false);
    expect($this->getJson('/api/zoeken?recent=1')->json('items.0.aantal'))->toBe(5);
});

test('aantal standaard 1 als het veld ontbreekt', function () {
    $u = aantalUser();
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasNoErrors();

    expect(Aanbod::query()->where('user_id', $u->id)->value('aantal'))->toBe(1);
});

test('aanbiedformulier toont aantal standaard 1, verplicht', function () {
    $u = aantalUser();
    $html = $this->actingAs($u)->get(route('aanbieden'))->assertOk()->getContent();

    expect($html)->toContain('name="aantal"')
        ->and($html)->toMatch('/name="aantal"[^>]*\srequired|required[^>]*name="aantal"/')
        ->and($html)->toMatch('/name="aantal"[^>]*value="1"|value="1"[^>]*name="aantal"/')
        ->and($html)->not->toContain('(optioneel');
});

test('aantal valideert 1–99', function () {
    $u = aantalUser();
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '0', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasErrors('aantal');
    $this->actingAs($u)->post(route('account.aanbod.store'), [
        'type' => 'uitdelen', 'tekst' => 'Pompoen', 'aantal' => '100', 'gratis_akkoord' => '1', 'website' => '', 'form_started' => time() - 10,
    ])->assertSessionHasErrors('aantal');
});

test('− en + passen het aantal aan; bij 0 gaat het aanbod offline zonder 0 op te slaan', function () {
    $u = aantalUser();
    $a = aantalAanbod($u, 2);

    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => 1])->assertRedirect();
    expect($a->fresh()->aantal)->toBe(3);
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    expect($a->fresh()->aantal)->toBe(1)->and($a->fresh()->visible)->toBeTrue();
    $this->actingAs($u)->post(route('account.aanbod.aantal', $a), ['delta' => -1]);
    expect($a->fresh()->visible)->toBeFalse()
        ->and($a->fresh()->aantal)->toBe(1);

    $this->actingAs($u)->get(route('account.aanbod'))->assertSee('Weer online', false);
});

test('aantal aanpassen: alleen eigen aanbod en alleen −1/+1', function () {
    $a = aantalAanbod(aantalUser(), 3);
    $ander = aantalUser();
    $this->actingAs($ander)->post(route('account.aanbod.aantal', $a), ['delta' => 1])->assertForbidden();
    $this->actingAs($a->user)->post(route('account.aanbod.aantal', $a), ['delta' => 5])->assertStatus(422);
});

test('migratie backfill: NULL wordt 1 en kolom is not null default 1', function () {
    $u = aantalUser();
    $migration = require database_path('migrations/2026_11_04_190000_aanbod_aantal_not_null_default_1.php');

    // Terug naar nullable (zoals vóór 0.67), daarna NULL-rij, dan up() opnieuw.
    $migration->down();

    $id = DB::table('aanbod')->insertGetId([
        'user_id' => $u->id,
        'type' => 'uitdelen',
        'tekst' => 'Oud aanbod',
        'aantal' => null,
        'visible' => 1,
        'expires_at' => now()->addDays(30),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect(DB::table('aanbod')->where('id', $id)->value('aantal'))->toBeNull();

    $migration->up();

    expect((int) DB::table('aanbod')->where('id', $id)->value('aantal'))->toBe(1);

    // Raw insert zonder aantal-kolom gebruikt DB-default 1.
    $cols = ['user_id', 'type', 'tekst', 'visible', 'expires_at', 'created_at', 'updated_at'];
    $vals = [$u->id, 'uitdelen', 'Zonder aantal', 1, now(), now(), now()];
    DB::table('aanbod')->insert(array_combine($cols, $vals));
    expect((int) DB::table('aanbod')->where('tekst', 'Zonder aantal')->value('aantal'))->toBe(1)
        ->and(Schema::hasColumn('aanbod', 'aantal'))->toBeTrue();
});

test('detailpagina toont «3 stuks» bij 3 en niets bij 1', function () {
    $u = aantalUser();
    aantalAanbod($u, 3)->update(['tekst' => 'Pompoen drie']);
    aantalAanbod($u, 1)->update(['tekst' => 'Pompoen één']);

    $html = $this->get(route('aanbod', ['plant' => 'Pompoen']))->assertOk()->getContent();

    expect($html)->toContain('Pompoen drie')
        ->and($html)->toContain('3 stuks')
        ->and($html)->toContain('Pompoen één')
        ->and($html)->not->toMatch('/Pompoen één[\s\S]{0,120}1 stuks/');
});
