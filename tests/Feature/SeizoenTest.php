<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\User;
use App\Services\Seizoen;

test('alle 12 maanden hebben een kop, tekst, wist-je-dat en drie acties met werkende links', function () {
    $s = app(Seizoen::class);
    foreach (range(1, 12) as $maand) {
        $blok = $s->blok($maand);
        expect($blok)->not->toBeNull("maand {$maand}")
            ->and($blok['kop'])->not->toBeEmpty()
            ->and($blok['tekst'])->not->toBeEmpty()
            ->and($blok['wist_je_dat'])->not->toBeEmpty()
            ->and($blok['seizoen'])->toBeIn(['lente', 'zomer', 'herfst', 'winter'])
            ->and($blok['acties'])->toHaveCount(3);
        foreach ($blok['acties'] as $actie) {
            $path = parse_url($actie['url'], PHP_URL_PATH);
            $this->get($path)->assertOk();
        }
    }
});

test('homepage toont het seizoensblok met twee stappen, en ?maand= toont een andere maand', function () {
    $html = $this->get('/?maand=3')->assertOk()->getContent();
    expect($html)->toContain('id="zelf-meer-maken"')
        ->and($html)->toContain('seizoen--lente')
        ->and($html)->toContain('Snoeischaar erbij, stekjes eruit')
        ->and($html)->toContain('Eerst voor jezelf')
        ->and($html)->toContain('Dan voor een ander')
        ->and($html)->toContain('href="'.route('vermeerderen').'"');

    expect($this->get('/?maand=11')->getContent())->toContain('seizoen--herfst')->toContain('Kale takken? Gratis struiken!');
    // Ongeldige maand valt terug op de huidige.
    $this->get('/?maand=99')->assertOk()->assertSee('id="zelf-meer-maken"', false);
});

test('seizoensblok staat na «Hier kun je nu al terecht» en voor «Waarom»', function () {
    $html = $this->get('/')->getContent();
    $pos = strpos($html, 'id="zelf-meer-maken"');
    expect($pos)->toBeGreaterThan(0)
        ->and($pos)->toBeLessThan(strpos($html, 'id="waarom"'))
        ->and($pos)->toBeGreaterThan(strpos($html, 'id="hoe-werkt-het"'));
});

test('teller verschijnt pas vanaf vijf keer gedeeld deze maand', function () {
    expect(app(Seizoen::class)->blok(1)['gedeeld'])->toBeNull();
    $u = User::factory()->create();
    foreach (range(1, Seizoen::TELLER_VANAF) as $i) {
        Aanbod::query()->create(['user_id' => $u->id, 'type' => 'uitdelen', 'tekst' => 'Munt '.$i, 'visible' => true, 'expires_at' => now()->addDays(30)]);
    }
    expect(app(Seizoen::class)->blok(1)['gedeeld'])->toBe(Seizoen::TELLER_VANAF);
});
