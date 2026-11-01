<?php

declare(strict_types=1);

use App\Services\InvasieveSoorten;
use App\Services\Stektips;
use App\Support\SeoTitle;

test('elke soortpagina laadt met 200 en SEO-titel', function () {
    $stektips = app(Stektips::class);
    $soorten = $stektips->all();

    expect($soorten)->toHaveCount(106);

    foreach ($soorten as $soort) {
        $slug = $soort['slug'];
        $naam = $soort['naam'];
        $seoNaam = trim((string) ($soort['seo_naam'] ?? '')) ?: $naam;
        $seoWerkwoord = trim((string) ($soort['seo_werkwoord'] ?? '')) ?: 'stekken';

        $expectedTitle = SeoTitle::document($seoNaam.' '.$seoWerkwoord.' en gratis stekjes');

        $this->get(route('stektips.show', ['slug' => $slug]))
            ->assertOk()
            ->assertSee($seoNaam.' '.$seoWerkwoord.': zo doe je het', false)
            ->assertSee('<title>'.$expectedTitle.'</title>', false)
            ->assertSee('Gratis '.$naam.'-stekjes in je buurt', false)
            ->assertSee((string) $soort['latijn'], false);
    }
});

test('nieuwe sier- en kamerplanten staan in overzicht en sitemap', function () {
    $nieuwe = [
        'monstera', 'pothos', 'scindapsus', 'sanseveria', 'graslelie', 'pilea',
        'echeveria', 'jadeplant', 'aloe-vera', 'begonia', 'tradescantia', 'philodendron',
        'zamioculcas', 'kerstcactus', 'pelargonium', 'fuchsia', 'hortensia', 'lavendel',
        'lepelplant', 'rubberplant', 'peperomia', 'calathea', 'sedum', 'huislook',
        'kalanchoe', 'kaaps-viooltje', 'syngonium', 'kettinghart', 'dieffenbachia',
        'anthurium', 'hosta', 'dahlia',
    ];

    expect($nieuwe)->toHaveCount(32);

    $stektips = app(Stektips::class);
    $index = $this->get(route('stektips.index'))->assertOk();
    $xml = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    foreach ($nieuwe as $slug) {
        $soort = $stektips->find($slug);
        expect($soort)->not->toBeNull();
        $index->assertSee((string) $soort['naam'], false);
        expect($xml)->toContain(route('stektips.show', ['slug' => $slug]));
    }
});

test('stektips bevatten geen EU-Unielijst-soort', function () {
    $invasieve = app(InvasieveSoorten::class);
    expect($invasieve->all())->toHaveCount(47);

    $stektips = app(Stektips::class)->all();

    foreach ($stektips as $soort) {
        // Alleen identificerende velden: waarschuwingsteksten mogen Unielijst-namen noemen.
        $haystack = implode(' ', array_filter([
            (string) ($soort['naam'] ?? ''),
            (string) ($soort['latijn'] ?? ''),
            str_replace('-', ' ', (string) ($soort['slug'] ?? '')),
        ], static fn ($v) => is_string($v) && $v !== ''));

        $matches = $invasieve->matches($haystack);
        expect($matches)->toBeEmpty('Unielijst-treffer in stektip '.$soort['slug'].': '.json_encode($matches));
    }

    // Expliciet: geen vlinderstruik / Buddleja in de set
    $namen = collect($stektips)->map(fn ($s) => mb_strtolower((string) ($s['naam'] ?? '').' '.($s['latijn'] ?? ''), 'UTF-8'))->implode(' ');
    expect($namen)->not->toContain('vlinderstruik');
    expect($namen)->not->toContain('buddleja');
});

test('jadeplant waarschuwt voor watercrassula op de Unielijst', function () {
    $this->get(route('stektips.show', ['slug' => 'jadeplant']))
        ->assertOk()
        ->assertSee('Crassula ovata', false)
        ->assertSee('Watercrassula', false)
        ->assertSee('Unielijst', false);

    // Waarschuwingstekst mag Unielijst-naam noemen; de soort zelf is Crassula ovata
    $invasieve = app(InvasieveSoorten::class);
    expect($invasieve->matches('Crassula ovata jadeplant'))->toBeEmpty();
    expect($invasieve->matches('Watercrassula Crassula helmsii'))->not->toBeEmpty();
});

test('soortpagina linkt naar verwante soorten', function () {
    $this->get(route('stektips.show', ['slug' => 'monstera']))
        ->assertOk()
        ->assertSee('Verwante soorten', false)
        ->assertSee('Philodendron', false)
        ->assertSee('Pothos', false)
        ->assertSee(route('stektips.show', ['slug' => 'philodendron']), false)
        ->assertSee(route('stektips.show', ['slug' => 'pothos']), false);
});

test('stektips-index: zoekveld eerst, meer filters ingeklapt, beginners en FAQ met FAQPage-schema', function () {
    $html = $this->get(route('stektips.index'))->assertOk()->getContent();

    expect(strpos($html, 'Welke plant wil je stekken?'))->toBeLessThan(strpos($html, 'stektips-pillar-list'))
        ->and($html)->toContain('<details class="stektips-meer-filters"')
        ->and($html)->toContain('Makkelijk om mee te beginnen')
        ->and($html)->toContain('id="veelgestelde-vragen"')
        ->and($html)->toContain('Wanneer is de beste tijd om te stekken?')
        ->and($html)->toContain('"@type":"FAQPage"')
        ->and($html)->toContain('"@type":"Question"')
        ->and($html)->not->toContain('…</p>');
});

test('stektips-index met filter: geen FAQ en geen beginners, filters open', function () {
    $html = $this->get(route('stektips.index', ['seizoen' => 'zomer']))->assertOk()->getContent();

    expect($html)->not->toContain('id="veelgestelde-vragen"')
        ->and($html)->not->toContain('Makkelijk om mee te beginnen')
        ->and($html)->toMatch('/stektips-meer-filters"\s+open/');
});

test('faq-data: elke link verwijst naar een bestaande pagina', function () {
    $data = json_decode((string) file_get_contents(base_path('data/stektips-faq.json')), true);
    expect($data['vragen'])->toHaveCount(10);
    foreach ($data['vragen'] as $v) {
        if (! empty($v['link']['pad'])) {
            $this->get($v['link']['pad'])->assertOk();
        }
    }
});
