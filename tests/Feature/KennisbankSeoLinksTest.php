<?php

declare(strict_types=1);

use App\Services\Kennisbank;

test('elke snoeipagina linkt naar stekpagina van dezelfde plant', function () {
    $kb = app(Kennisbank::class);
    $snoei = collect($kb->all())->filter(
        fn ($a) => ($a['seo']['rol'] ?? null) === 'snoeien'
    );
    expect($snoei->count())->toBeGreaterThanOrEqual(6);

    foreach ($snoei as $artikel) {
        $slug = $artikel['slug'];
        $seo = $artikel['seo'];
        $stekType = $seo['stek_type'];
        $stekSlug = $seo['stek_slug'];
        $stekUrl = $stekType === 'soort'
            ? route('stektips.show', ['slug' => $stekSlug])
            : route('stektips.artikel', ['slug' => $stekSlug]);

        $html = $this->get(route('stektips.artikel', ['slug' => $slug]))
            ->assertOk()
            ->assertDontSee('Volgt binnenkort', false)
            ->getContent();

        expect($html)->toContain($stekUrl)
            ->and($html)->toContain('Gooi je snoeisel niet weg');
        expect($this->get($stekUrl)->getStatusCode())->toBeLessThan(500);
    }
});

test('elke stekpagina (artikel of soort-hub) linkt naar wilgenwater en aanbod plant', function () {
    $kb = app(Kennisbank::class);

    $stekArtikelen = collect($kb->all())->filter(
        fn ($a) => ($a['seo']['rol'] ?? null) === 'stekken'
    );
    expect($stekArtikelen->count())->toBeGreaterThanOrEqual(3);

    foreach ($stekArtikelen as $artikel) {
        $plant = (string) ($artikel['seo']['aanbod_plant'] ?? $artikel['seo']['plant'] ?? '');
        $html = $this->get(route('stektips.artikel', ['slug' => $artikel['slug']]))
            ->assertOk()
            ->getContent();
        expect($html)->toContain(route('stektips.artikel', ['slug' => 'wilgenwater']))
            ->and($html)->toContain(route('aanbod', ['plant' => $plant]));
    }

    foreach (['hortensia', 'lavendel', 'monstera', 'vijg'] as $soortSlug) {
        $hub = $kb->soortHub($soortSlug);
        expect($hub)->not->toBeNull();
        $plant = (string) ($hub['aanbod_plant'] ?? $soortSlug);
        $html = $this->get(route('stektips.show', ['slug' => $soortSlug]))
            ->assertOk()
            ->getContent();
        expect($html)->toContain(route('stektips.artikel', ['slug' => 'wilgenwater']))
            ->and($html)->toContain(route('aanbod', ['plant' => $plant]))
            ->and($html)->toContain('Gratis');
    }
});

test('aanbod plant-filter werkt zonder JS en api accepteert plant', function () {
    $this->get(route('aanbod', ['plant' => 'hortensia']))
        ->assertOk()
        ->assertSee('hortensia', false)
        ->assertSee('name="plant"', false);

    // API zonder plaats blijft 400; met plaats + plant is ok-structuur
    $this->getJson(route('api.zoeken', ['q' => 'Haarlem', 'plant' => 'hortensia']))
        ->assertOk()
        ->assertJsonStructure(['meta', 'items']);
});

test('stektips-overzicht toont alle vijf pillarkaarten', function () {
    $kb = app(Kennisbank::class);
    $meta = $kb->pillarMeta();
    expect($meta)->toHaveCount(5);

    $html = $this->get(route('stektips.index'))
        ->assertOk()
        ->getContent();

    foreach ($meta as $pillarSlug => $pillar) {
        $hubUrl = route('stektips.artikel', ['slug' => $pillar['hub_slug']]);
        expect($html)->toContain($hubUrl)
            ->and($html)->toContain($pillar['naam']);
        expect($kb->find($pillar['hub_slug']))->not->toBeNull()
            ->and($kb->find($pillar['hub_slug'])['is_pillar'] ?? false)->toBeTrue()
            ->and($kb->find($pillar['hub_slug'])['pillar'] ?? '')->toBe($pillarSlug);
    }

    expect($html)->toContain(route('stektips.artikel', ['slug' => 'permacultuur-gilde-voedselbos']))
        ->and($html)->toContain('Permacultuur');
});

test('pijlers en top-seo-paginas laden onder 500 met bronnen', function () {
    $slugs = [
        'stekken',
        'snoeien',
        'natuurlijk-bewortelen',
        'vermeerderen',
        'permacultuur-gilde-voedselbos',
        'hortensia-snoeien',
        'vlinderstruik-snoeien',
        'vlinderstruik-stekken',
        'lavendel-snoeien',
        'olijfboom-snoeien',
        'olijfboom-stekken',
        'rozen-snoeien',
        'rozen-stekken',
        'vijg-snoeien',
        'monstera-stekken-in-water',
        'stekpoeder',
        'wilgenwater',
    ];
    foreach ($slugs as $slug) {
        $this->get(route('stektips.artikel', ['slug' => $slug]))
            ->assertOk()
            ->assertSee('Bronnen', false)
            ->assertSee('https://', false)
            ->assertDontSee('Volgt binnenkort', false);
    }

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    expect($xml)->toContain(route('stektips.artikel', ['slug' => 'hortensia-snoeien']))
        ->and($xml)->toContain(route('stektips.artikel', ['slug' => 'snoeien']))
        ->and($xml)->toContain(route('stektips.artikel', ['slug' => 'permacultuur-gilde-voedselbos']));
});

test('snoeikalender op pijler Snoeien heeft twaalf maandregels', function () {
    $html = $this->get(route('stektips.artikel', ['slug' => 'snoeien']))
        ->assertOk()
        ->assertSee('Snoeikalender', false)
        ->getContent();

    foreach ([
        'Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
        'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December',
    ] as $maand) {
        expect($html)->toContain($maand);
    }
});
