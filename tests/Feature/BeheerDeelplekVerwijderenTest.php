<?php

declare(strict_types=1);

use App\Models\Deelplek;
use App\Models\DeelplekTombstone;
use App\Models\User;
use App\Services\StadCatalogus;
use Database\Seeders\DeelplekkenSeeder;
use Illuminate\Support\Facades\Cache;

function beheerDeelplekAdmin(): User
{
    config(['stekjes.admin_dashboard_emails' => ['deelplek-admin@example.com']]);

    return User::factory()->create([
        'name' => 'Deelplek Admin',
        'email' => 'deelplek-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function maakDeelplekAttrs(string $slug, bool $visible, string $naam = 'Test Tuin'): array
{
    return [
        'slug' => $slug,
        'naam' => $naam,
        'type' => 'tuinvereniging',
        'plaats' => 'Testdorp',
        'provincie' => 'Utrecht',
        'country' => 'NL',
        'adres' => '1234 AB Testdorp',
        'lat' => 52.1,
        'lng' => 5.1,
        'bron_url' => 'https://example.test/tuin',
        'geraadpleegd' => '2026-11-01',
        'visible' => $visible,
        'source' => Deelplek::SOURCE_SEED,
        'status' => Deelplek::STATUS_APPROVED,
    ];
}

test('admin ziet verwijderknop alleen bij verborgen deelplekken', function () {
    $admin = beheerDeelplekAdmin();
    $verborgen = Deelplek::query()->create(maakDeelplekAttrs('test-verborgen-tuin', false, 'Verborgen Tuin'));
    $zichtbaar = Deelplek::query()->create(maakDeelplekAttrs('test-zichtbare-tuin', true, 'Zichtbare Tuin'));

    $html = $this->actingAs($admin)
        ->get(route('beheer.deelplekken.index', ['zichtbaar' => 'alle']))
        ->assertOk()
        ->assertSee('Alleen verborgen', false)
        ->assertSee('Selectie verwijderen', false)
        ->getContent();

    expect($html)->toContain(route('beheer.deelplekken.destroy.confirm', $verborgen))
        ->and($html)->not->toContain(route('beheer.deelplekken.destroy.confirm', $zichtbaar));

    $this->actingAs($admin)
        ->get(route('beheer.deelplekken.show', $zichtbaar))
        ->assertOk()
        ->assertDontSee('Verwijderen…', false)
        ->assertSee('Zichtbare deelplekken kun je hier niet verwijderen', false);

    $this->actingAs($admin)
        ->get(route('beheer.deelplekken.show', $verborgen))
        ->assertOk()
        ->assertSee('Verwijderen…', false);
});

test('admin kan verborgen deelplek verwijderen via bevestigingspagina', function () {
    $admin = beheerDeelplekAdmin();
    $plek = Deelplek::query()->create(maakDeelplekAttrs('test-weg-tuin', false, 'Weg Tuin'));
    $id = (int) $plek->id;

    Cache::put(StadCatalogus::CACHE_KEY, ['by_slug' => ['x' => []]], 60);

    $this->actingAs($admin)
        ->get(route('beheer.deelplekken.destroy.confirm', $plek))
        ->assertOk()
        ->assertSee('Weet je het zeker?', false)
        ->assertSee('Weg Tuin', false)
        ->assertSee('Testdorp', false)
        ->assertDontSee('onsubmit=', false);

    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.destroy', $plek), ['confirm' => '1'])
        ->assertRedirect(route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']))
        ->assertSessionHas('status');

    expect(Deelplek::query()->whereKey($id)->exists())->toBeFalse()
        ->and(DeelplekTombstone::existsForSlug('test-weg-tuin'))->toBeTrue()
        ->and(Cache::has(StadCatalogus::CACHE_KEY))->toBeFalse();

    $this->getJson(route('api.deelplekken'))
        ->assertOk()
        ->assertJsonMissing(['slug' => 'test-weg-tuin']);
});

test('zichtbare deelplek wordt geweigerd bij verwijderen', function () {
    $admin = beheerDeelplekAdmin();
    $plek = Deelplek::query()->create(maakDeelplekAttrs('test-blijft-zichtbaar', true, 'Blijft Zichtbaar'));

    $this->actingAs($admin)
        ->get(route('beheer.deelplekken.destroy.confirm', $plek))
        ->assertRedirect(route('beheer.deelplekken.show', $plek))
        ->assertSessionHasErrors('deelplek');

    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.destroy', $plek), ['confirm' => '1'])
        ->assertRedirect(route('beheer.deelplekken.show', $plek))
        ->assertSessionHasErrors('deelplek');

    expect(Deelplek::query()->whereKey($plek->id)->exists())->toBeTrue()
        ->and(DeelplekTombstone::existsForSlug('test-blijft-zichtbaar'))->toBeFalse();
});

test('niet-admin mag deelplekken niet verwijderen', function () {
    $lid = User::factory()->create([
        'email' => 'gewoon-lid@example.test',
        'email_verified_at' => now(),
    ]);
    $plek = Deelplek::query()->create(maakDeelplekAttrs('test-verboden-delete', false, 'Verboden'));

    // Admin-middleware stuurt niet-admins naar login (geen silent delete).
    $this->actingAs($lid)
        ->post(route('beheer.deelplekken.destroy', $plek), ['confirm' => '1'])
        ->assertRedirect(route('beheer.login'));

    $this->actingAs($lid)
        ->get(route('beheer.deelplekken.index'))
        ->assertRedirect(route('beheer.login'));

    expect(Deelplek::query()->whereKey($plek->id)->exists())->toBeTrue();
});

test('bulk-verwijderen toont bevestiging met namen en wist selectie', function () {
    $admin = beheerDeelplekAdmin();
    $a = Deelplek::query()->create(maakDeelplekAttrs('bulk-a', false, 'Bulk A'));
    $b = Deelplek::query()->create(maakDeelplekAttrs('bulk-b', false, 'Bulk B'));
    $zichtbaar = Deelplek::query()->create(maakDeelplekAttrs('bulk-zichtbaar', true, 'Bulk Zichtbaar'));

    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.bulk-destroy.confirm'), [
            'ids' => [$a->id, $b->id],
        ])
        ->assertOk()
        ->assertSee('Weet je het zeker?', false)
        ->assertSee('Bulk A', false)
        ->assertSee('Bulk B', false)
        ->assertSee('2', false);

    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.bulk-destroy'), [
            'confirm' => '1',
            'ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']))
        ->assertSessionHas('status');

    expect(Deelplek::query()->whereKey($a->id)->exists())->toBeFalse()
        ->and(Deelplek::query()->whereKey($b->id)->exists())->toBeFalse()
        ->and(Deelplek::query()->whereKey($zichtbaar->id)->exists())->toBeTrue()
        ->and(DeelplekTombstone::existsForSlug('bulk-a'))->toBeTrue()
        ->and(DeelplekTombstone::existsForSlug('bulk-b'))->toBeTrue();

    // Selectie met zichtbare plek weigeren.
    $nog = Deelplek::query()->create(maakDeelplekAttrs('bulk-nog', false, 'Bulk Nog'));
    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.bulk-destroy.confirm'), [
            'ids' => [$nog->id, $zichtbaar->id],
        ])
        ->assertRedirect(route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']))
        ->assertSessionHasErrors('deelplek');

    expect(Deelplek::query()->whereKey($nog->id)->exists())->toBeTrue();
});

test('verwijderde seed-deelplek komt niet terug na opnieuw seeden', function () {
    $this->seed(DeelplekkenSeeder::class);

    $slug = 'delft-mekelpark-ruilkast';
    $plek = Deelplek::query()->where('slug', $slug)->first();
    expect($plek)->not->toBeNull()
        ->and($plek->visible)->toBeFalse();

    $admin = beheerDeelplekAdmin();
    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.destroy', $plek), ['confirm' => '1'])
        ->assertRedirect();

    expect(Deelplek::query()->where('slug', $slug)->exists())->toBeFalse()
        ->and(DeelplekTombstone::existsForSlug($slug))->toBeTrue();

    $this->seed(DeelplekkenSeeder::class);
    $this->seed(DeelplekkenSeeder::class);

    expect(Deelplek::query()->where('slug', $slug)->exists())->toBeFalse()
        ->and(Deelplek::query()->where('slug', 'haarlem-plantenasiel')->exists())->toBeTrue();
});

test('oude slug-URL van verwijderde deelplek geeft 410', function () {
    $plek = Deelplek::query()->create(maakDeelplekAttrs('oude-slug-tuin', false, 'Oude Slug'));
    $admin = beheerDeelplekAdmin();

    $this->actingAs($admin)
        ->post(route('beheer.deelplekken.destroy', $plek), ['confirm' => '1'])
        ->assertRedirect();

    $this->get(route('deelplekken.legacy', ['slug' => 'oude-slug-tuin']))
        ->assertStatus(410)
        ->assertSee('Deze deelplek is weg', false)
        ->assertSee(route('deelplekken'), false);
});

test('bestaande zichtbare slug-URL redirect 301 naar deelplekkenlijst', function () {
    Deelplek::query()->create(maakDeelplekAttrs('live-slug-tuin', true, 'Live Slug'));

    $this->get(route('deelplekken.legacy', ['slug' => 'live-slug-tuin']))
        ->assertRedirect(route('deelplekken'))
        ->assertStatus(301);
});

test('onbekende slug-URL geeft 404', function () {
    $this->get(route('deelplekken.legacy', ['slug' => 'bestaat-niet-xyz']))
        ->assertNotFound();
});

test('verwijderen zonder bevestiging weigert', function () {
    $admin = beheerDeelplekAdmin();
    $plek = Deelplek::query()->create(maakDeelplekAttrs('geen-confirm', false, 'Geen Confirm'));

    $this->actingAs($admin)
        ->from(route('beheer.deelplekken.destroy.confirm', $plek))
        ->post(route('beheer.deelplekken.destroy', $plek), [])
        ->assertRedirect(route('beheer.deelplekken.destroy.confirm', $plek))
        ->assertSessionHasErrors('confirm');

    expect(Deelplek::query()->whereKey($plek->id)->exists())->toBeTrue();
});
