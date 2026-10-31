<?php

declare(strict_types=1);

use App\Models\Evenement;
use App\Services\Evenementen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00', 'Europe/Amsterdam'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeEvenement(array $overrides = []): Evenement
{
    return Evenement::query()->create(array_merge([
        'titel' => 'Demo stekjesmarkt',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-05-10',
        'eind_datum' => null,
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
        'organisator' => 'Demo orga',
        'url' => 'https://example.org/markt',
        'bron' => 'https://example.org/bron',
        'gecontroleerd_op' => '2026-04-01',
        'visible' => true,
    ], $overrides));
}

test('initiatievenpagina toont SEO-title, H1, lege agenda en vaste initiatieven', function () {
    $this->get('/initiatieven')
        ->assertOk()
        ->assertSee('Stekjesmarkt, plantenruilbeurs en ruilinitiatieven', false)
        ->assertSee('Plantenruilbeurzen, stekjesmarkten en initiatieven', false)
        ->assertSee('Nog geen data voor 2026 bekend', false)
        ->assertSee('Meld het', false)
        ->assertSee('Vaste ruilinitiatieven', false)
        ->assertSee('Voedselbosbouw.org', false)
        ->assertSee('Stekjesroute', false);
});

test('komende evenementen chronologisch per maand en verborgen blijven weg', function () {
    makeEvenement([
        'titel' => 'Mei-markt',
        'start_datum' => '2026-05-20',
        'plaats' => 'Amersfoort',
        'provincie' => 'Utrecht',
    ]);
    makeEvenement([
        'titel' => 'Juni-beurs',
        'type' => 'plantenruilbeurs',
        'start_datum' => '2026-06-05',
        'eind_datum' => '2026-06-06',
        'plaats' => 'Arnhem',
        'provincie' => 'Gelderland',
        'organisator' => 'Ruil Arnhem',
    ]);
    makeEvenement([
        'titel' => 'Verborgen markt',
        'start_datum' => '2026-05-01',
        'visible' => false,
    ]);

    $html = $this->get('/initiatieven')
        ->assertOk()
        ->assertSee('Mei-markt', false)
        ->assertSee('Juni-beurs', false)
        ->assertSee('mei 2026', false)
        ->assertSee('juni 2026', false)
        ->assertDontSee('Verborgen markt', false)
        ->assertDontSee('Nog geen data voor 2026 bekend', false)
        ->getContent();

    expect(strpos($html, 'Mei-markt'))->toBeLessThan(strpos($html, 'Juni-beurs'));
    expect($html)->toContain('"@type":"Event"')
        ->and($html)->toContain('"startDate":"2026-05-20"')
        ->and($html)->toContain('"startDate":"2026-06-05"')
        ->and($html)->toContain('"endDate":"2026-06-06"');
});

test('verlopen evenementen staan in inklapbaar Geweest', function () {
    makeEvenement([
        'titel' => 'Oude ruildag',
        'type' => 'ruildag',
        'start_datum' => '2026-03-01',
        'plaats' => 'Haarlem',
        'provincie' => 'Noord-Holland',
    ]);
    makeEvenement([
        'titel' => 'Komende markt',
        'start_datum' => '2026-09-01',
        'plaats' => 'Leiden',
        'provincie' => 'Zuid-Holland',
    ]);

    $this->get('/initiatieven')
        ->assertOk()
        ->assertSee('Komende markt', false)
        ->assertSee('Geweest', false)
        ->assertSee('Oude ruildag', false)
        ->assertSee('<details', false);
});

test('filter op provincie en type', function () {
    makeEvenement([
        'titel' => 'Utrecht markt',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-05-01',
        'provincie' => 'Utrecht',
        'plaats' => 'Utrecht',
    ]);
    makeEvenement([
        'titel' => 'Gelderland beurs',
        'type' => 'plantenruilbeurs',
        'start_datum' => '2026-05-02',
        'provincie' => 'Gelderland',
        'plaats' => 'Nijmegen',
    ]);

    $this->get('/initiatieven?provincie=Utrecht')
        ->assertOk()
        ->assertSee('Utrecht markt', false)
        ->assertDontSee('Gelderland beurs', false);

    $this->get('/initiatieven?type=plantenruilbeurs')
        ->assertOk()
        ->assertSee('Gelderland beurs', false)
        ->assertDontSee('Utrecht markt', false);

    $this->get('/initiatieven?type=onzin')
        ->assertOk()
        ->assertSee('Utrecht markt', false)
        ->assertSee('Gelderland beurs', false);
});

test('admin kan evenement toevoegen, bewerken en verbergen', function () {
    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.evenementen.save'), [
            'titel' => 'Admin markt',
            'type' => 'stekjesmarkt',
            'start_datum' => '2026-07-12',
            'eind_datum' => '',
            'plaats' => 'Zwolle',
            'provincie' => 'Overijssel',
            'organisator' => 'Zwolle groen',
            'url' => 'https://example.org/zwolle',
            'bron' => 'handmatig',
            'gecontroleerd_op' => '2026-04-10',
            'visible' => '1',
        ])
        ->assertRedirect(route('beheer.index'));

    $row = Evenement::query()->where('titel', 'Admin markt')->first();
    expect($row)->not->toBeNull()
        ->and($row->visible)->toBeTrue()
        ->and($row->provincie)->toBe('Overijssel');

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.evenementen.save'), [
            'id' => $row->id,
            'titel' => 'Admin markt bijgewerkt',
            'type' => 'ruildag',
            'start_datum' => '2026-07-12',
            'eind_datum' => '2026-07-13',
            'plaats' => 'Zwolle',
            'provincie' => 'Overijssel',
            'organisator' => 'Zwolle groen',
            'url' => 'https://example.org/zwolle',
            'bron' => 'handmatig',
            'gecontroleerd_op' => '2026-04-10',
            'visible' => '1',
        ])
        ->assertRedirect(route('beheer.index'));

    $row->refresh();
    expect($row->titel)->toBe('Admin markt bijgewerkt')
        ->and($row->type)->toBe('ruildag')
        ->and($row->eind_datum?->format('Y-m-d'))->toBe('2026-07-13');

    $this->withSession(['admin_ok' => true])
        ->post(route('beheer.evenementen.hide'), ['id' => $row->id])
        ->assertRedirect(route('beheer.index'));

    $row->refresh();
    expect($row->visible)->toBeFalse();

    $this->get('/initiatieven')->assertDontSee('Admin markt bijgewerkt', false);
});

test('sitemap blijft /initiatieven bevatten', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/initiatieven');
});

test('evenementen-service weigert ongeldige data', function () {
    $service = app(Evenementen::class);

    expect($service->save(null, [
        'titel' => '',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-05-01',
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
    ])['ok'])->toBeFalse();

    expect($service->save(null, [
        'titel' => 'Test',
        'type' => 'onzin',
        'start_datum' => '2026-05-01',
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
    ])['ok'])->toBeFalse();

    expect($service->save(null, [
        'titel' => 'Test',
        'type' => 'stekjesmarkt',
        'start_datum' => '2026-05-10',
        'eind_datum' => '2026-05-01',
        'plaats' => 'Utrecht',
        'provincie' => 'Utrecht',
    ])['ok'])->toBeFalse();
});

test('migratie evenementen is omkeerbaar', function () {
    // Rol specifiek deze migratie terug (niet --step=1: nieuwere migraties
    // zoals import_runs kunnen erna staan).
    $this->artisan('migrate:rollback', [
        '--path' => 'database/migrations/2026_10_31_120000_create_evenementen_table.php',
    ])->assertSuccessful();
    expect(Schema::hasTable('evenementen'))->toBeFalse();

    $this->artisan('migrate')->assertSuccessful();
    expect(Schema::hasTable('evenementen'))->toBeTrue()
        ->and(Schema::hasTable('import_runs'))->toBeTrue();
});
