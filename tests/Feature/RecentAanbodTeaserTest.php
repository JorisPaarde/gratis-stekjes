<?php

declare(strict_types=1);

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use App\Models\User;
use App\Support\RecentAanbod;
use Database\Seeders\DeelplekkenSeeder;

/**
 * Teaser-blokken met recent/actueel aanbod blijven verborgen tot er genoeg
 * actieve items mét foto in de scope zijn (default 8 = volle fotogrid).
 */

function teaserLid(string $email, string $name, float $lat, float $lon, string $label): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => $label,
        'lat' => $lat,
        'lon' => $lon,
        'active' => true,
        'email_verified_at' => now(),
    ]);
}

function teaserFoto(Aanbod $aanbod, string $slug): AanbodFoto
{
    return AanbodFoto::query()->create([
        'aanbod_id' => $aanbod->id,
        'disk' => 'aanbod_fotos',
        'path' => 'test/'.$slug.'.jpg',
        'thumb_path' => 'test/'.$slug.'-t.jpg',
        'mime' => 'image/jpeg',
        'width' => 400,
        'height' => 300,
        'bytes' => 1200,
        'sort_order' => 0,
        'alt' => '',
    ]);
}

/** @return list<Aanbod> */
function teaserAanbodMetFoto(User $user, int $count, string $prefix = 'Teaserstek'): array
{
    $rows = [];
    foreach (range(1, $count) as $i) {
        $a = Aanbod::query()->create([
            'user_id' => $user->id,
            'type' => 'uitdelen',
            'tekst' => $prefix.' '.$i,
            'visible' => true,
            'expires_at' => now()->addDays(30),
        ]);
        teaserFoto($a, strtolower($prefix).'-'.$i);
        $rows[] = $a;
    }

    return $rows;
}

test('RecentAanbod helper: drempel 8, env 1 = oud gedrag', function () {
    expect(RecentAanbod::min())->toBe(8)
        ->and(RecentAanbod::shouldShow(7))->toBeFalse()
        ->and(RecentAanbod::shouldShow(8))->toBeTrue();

    config(['gratisplanten.recent_aanbod_min' => 1]);
    expect(RecentAanbod::shouldShow(1))->toBeTrue()
        ->and(RecentAanbod::shouldShow(0))->toBeFalse();
});

test('home: Net aangeboden verborgen bij 7 met foto, zichtbaar bij 8', function () {
    $this->seed(DeelplekkenSeeder::class);
    $user = teaserLid('home-teaser@example.com', 'Sam', 52.381, 4.637, 'Haarlem');

    teaserAanbodMetFoto($user, 7, 'HomePlant');
    $html7 = $this->get('/')->assertOk()->getContent();
    expect($html7)->not->toContain('Net aangeboden')
        ->and($html7)->not->toContain('HomePlant')
        ->and($html7)->toContain('Hier kun je nu al terecht')
        ->and($html7)->toContain('Zo werkt het')
        ->and($html7)->toContain('Doe mee');

    teaserAanbodMetFoto($user, 1, 'HomeExtra');
    $html8 = $this->get('/')->assertOk()->getContent();
    expect($html8)->toContain('Net aangeboden')
        ->and(substr_count($html8, 'class="aanbod-kaart"'))->toBe(6);
});

test('home: aanbod zonder foto en verlopen tellen niet mee', function () {
    $user = teaserLid('home-exp@example.com', 'Eva', 52.381, 4.637, 'Haarlem');
    teaserAanbodMetFoto($user, 7, 'Actief');

    // 8e actief maar zonder foto → drempel niet gehaald.
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Zonder foto',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    // Verlopen mét foto telt ook niet.
    $verlopen = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'Verlopen stekje',
        'visible' => true,
        'expires_at' => now()->subDay(),
    ]);
    teaserFoto($verlopen, 'verlopen');

    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->not->toContain('Net aangeboden')
        ->and($html)->not->toContain('Verlopen stekje')
        ->and($html)->not->toContain('Zonder foto');
});

test('stadspagina: Actueel-kop verborgen bij 7 met foto, zichtbaar bij 8; CTAs en kaart blijven', function () {
    $user = teaserLid('stad-teaser@example.com', 'Sam', 52.387, 4.646, 'Haarlem');

    teaserAanbodMetFoto($user, 7, 'StadPlant');
    $html7 = $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->getContent();

    expect($html7)->not->toContain('id="aanbod-titel"')
        ->and($html7)->not->toContain('gp-aanbod-lijst')
        ->and($html7)->not->toContain('Er is nog geen aanbod')
        ->and($html7)->toContain('Zelf iets aanbieden')
        ->and($html7)->toContain('Zoek verder rond Haarlem')
        ->and($html7)->toContain('id="stad-kaart"')
        ->and($html7)->toContain('Veelgestelde vragen');

    teaserAanbodMetFoto($user, 1, 'StadExtra');
    $html8 = $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->getContent();

    expect($html8)->toContain('id="aanbod-titel"')
        ->and($html8)->toContain('Actueel aanbod')
        ->and($html8)->toContain('gp-aanbod-lijst')
        ->and($html8)->toContain('StadPlant')
        ->and($html8)->toContain('Zelf iets aanbieden');
});

test('stadspagina: verlopen of zonder foto halen drempel niet', function () {
    $user = teaserLid('stad-exp@example.com', 'Eva', 52.387, 4.646, 'Haarlem');
    teaserAanbodMetFoto($user, 7, 'StadActief');
    Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'StadZonderFoto',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    $verlopen = Aanbod::query()->create([
        'user_id' => $user->id,
        'type' => 'uitdelen',
        'tekst' => 'StadVerlopen',
        'visible' => true,
        'expires_at' => now()->subDay(),
    ]);
    teaserFoto($verlopen, 'stad-verlopen');

    $html = $this->get(route('gratis-planten.show', ['stad' => 'haarlem']))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('id="aanbod-titel"')
        ->and($html)->toContain('Zelf iets aanbieden');
});
