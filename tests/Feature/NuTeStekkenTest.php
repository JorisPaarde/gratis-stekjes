<?php

declare(strict_types=1);

use App\Services\NuTeStekken;
use Illuminate\Support\Carbon;

test('JSON heeft alle maanden met groepen tip en bron', function () {
    $calendar = app(NuTeStekken::class);
    $data = $calendar->load();

    expect($data['meta']['timezone'] ?? null)->toBe(NuTeStekken::TIMEZONE);

    for ($m = 1; $m <= 12; $m++) {
        $blok = $calendar->voorMaand($m);
        expect($blok['maand'])->toBe($m);
        expect($blok['maand_naam'])->toBe($calendar->maandnaam($m));
        expect($blok['groepen'])->not->toBeEmpty();
        foreach ($blok['groepen'] as $g) {
            expect(NuTeStekken::METHODES)->toContain($g['methode']);
            expect($g['tip'])->not->toBe('');
            expect($g['bron']['url'] ?? '')->not->toBe('');
            expect($g['planten'])->not->toBeEmpty();
        }
    }
});

test('maandselectie volgt Europe/Amsterdam inclusief jaargrens', function () {
    $calendar = app(NuTeStekken::class);

    expect($calendar->maandnummer(Carbon::parse('2026-01-15 12:00:00', NuTeStekken::TIMEZONE)))->toBe(1);
    expect($calendar->maandnummer(Carbon::parse('2026-06-15 12:00:00', NuTeStekken::TIMEZONE)))->toBe(6);
    expect($calendar->maandnummer(Carbon::parse('2025-12-31 23:30:00', NuTeStekken::TIMEZONE)))->toBe(12);
    expect($calendar->maandnummer(Carbon::parse('2026-01-01 00:30:00', NuTeStekken::TIMEZONE)))->toBe(1);

    $jan = $calendar->nu(Carbon::parse('2026-01-15 12:00:00', NuTeStekken::TIMEZONE));
    $jun = $calendar->nu(Carbon::parse('2026-06-15 12:00:00', NuTeStekken::TIMEZONE));
    expect($jan['maand'])->toBe(1);
    expect($jun['maand'])->toBe(6);
    expect($jan['groepen'])->not->toEqual($jun['groepen']);
});

test('API nu-te-stekken is ok met groepen', function () {
    $this->getJson(route('api.nu-te-stekken'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure([
            'maand',
            'maand_naam',
            'timezone',
            'intro',
            'groepen',
        ]);
});

test('homepage en aanbod tonen Nu te stekken-blok', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Nu te stekken', false);

    $this->get(route('aanbod'))
        ->assertOk()
        ->assertSee('Nu te stekken', false);
});
