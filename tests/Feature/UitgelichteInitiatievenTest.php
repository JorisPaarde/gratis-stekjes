<?php

declare(strict_types=1);

use App\Services\UitgelichteInitiatieven;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

test('homepage toont «Hier kun je ook aan meedoen» onder «Doe mee» met kaartjes', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('Hier kun je ook aan meedoen')
        ->and($html)->toContain('href="'.route('stekchallenge').'"')
        ->and($html)->toContain('href="'.route('partners').'#struikroven"')
        ->and($html)->toContain('stekchallenge-groep')
        ->and($html)->toContain('Foto: Carpe Vitam')
        ->and(strpos($html, 'id="ook-meedoen"'))->toBeGreaterThan(strpos($html, 'id="doe-mee"'));
});

test('/initiatieven toont STEKchallenge en Struikroven bovenaan met «Doe mee»', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 12:00', 'Europe/Amsterdam'));
    $html = $this->get(route('partners'))->assertOk()->getContent();
    expect($html)->toContain('id="stekchallenge"')
        ->and($html)->toContain('id="struikroven"')
        ->and($html)->toContain('Doe mee')
        ->and($html)->toContain('href="'.route('stekchallenge').'"')
        ->and($html)->toContain('https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/')
        ->and($html)->toContain('https://carpevitam.net/stekchallenge/')
        ->and($html)->toContain('https://www.struikroven.nu')
        ->and($html)->toContain('stekchallenge-groep')
        ->and(strpos($html, 'id="stekchallenge"'))->toBeLessThan(strpos($html, 'id="agenda"'));
});

test('workshop van 8 november verdwijnt daarna vanzelf', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-09 09:00', 'Europe/Amsterdam'));
    $stek = collect(app(UitgelichteInitiatieven::class)->all())->firstWhere('anker', 'stekchallenge');
    expect(collect($stek['acties'])->pluck('url')->implode(' '))->not->toContain('8-november')
        ->and($stek['acties'])->toHaveCount(2);
});
