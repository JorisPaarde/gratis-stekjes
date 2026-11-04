<?php

declare(strict_types=1);

use App\Http\Controllers\StektipsController;
use App\Services\Kennisbank;
use App\Services\Plantensoorten;
use App\Services\Stektips;

test('oude /stektips/sanseveria geeft 301 naar sansevieria', function () {
    $this->get('/stektips/sanseveria')
        ->assertStatus(301)
        ->assertRedirect('/stektips/sansevieria');

    $this->get('/stektips/sansevieria')
        ->assertOk()
        ->assertSee('Sansevieria', false)
        ->assertDontSee('Sanseveria', false);
});

test('gerenderde stektips- en artikelpaginas bevatten geen Sanseveria', function () {
    $html = $this->get(route('stektips.show', ['slug' => 'sansevieria']))->assertOk()->getContent();
    expect($html)->not->toContain('Sanseveria')
        ->and($html)->toContain('Sansevieria');

    foreach (['bladstek', 'stekken', 'scheuren', 'uitlopers'] as $slug) {
        $page = $this->get(route('stektips.artikel', ['slug' => $slug]))->assertOk()->getContent();
        expect($page)->not->toContain('Sanseveria');
    }

    $index = $this->get(route('stektips.index'))->assertOk()->getContent();
    expect($index)->not->toContain('Sanseveria')
        ->and($index)->toContain('Sansevieria');
});

test('oude spelling sanseveria blijft vindbaar als synoniem', function () {
    $planten = app(Plantensoorten::class);
    $viaOud = $planten->resolveExact('Sanseveria');
    expect($viaOud)->not->toBeNull()
        ->and($viaOud['naam'])->toBe('Sansevieria')
        ->and($viaOud['slug'])->toBe('sansevieria');

    $search = $planten->search('sanseveria', 5);
    expect(collect($search)->pluck('naam')->all())->toContain('Sansevieria');

    expect(collect($viaOud['synoniemen']))->toContain('Vrouwentong')
        ->and(collect($viaOud['synoniemen']))->toContain('Slangenplant')
        ->and(collect($viaOud['synoniemen']))->toContain('Sanseveria');
});

test('elke plantensoort met stektips-pagina heeft minstens één synoniem', function () {
    $planten = app(Plantensoorten::class);
    $soortSlugs = collect(app(Stektips::class)->all())->pluck('slug');

    foreach ($planten->all(includeHidden: true) as $soort) {
        $slug = $soort['slug'] ?? null;
        if (! is_string($slug) || $slug === '' || ! $soortSlugs->contains($slug)) {
            continue;
        }
        expect($soort['synoniemen'])->not->toBeEmpty(
            "Plantpagina {$slug} ({$soort['naam']}) mist synoniemen"
        );
    }
});

test('sitemap bevat elk kennisbank-artikel precies één keer op canonieke URL', function () {
    $artikelen = app(Kennisbank::class)->all();
    expect($artikelen)->toHaveCount(36);

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    $canonicals = [];
    foreach ($artikelen as $artikel) {
        $slug = (string) ($artikel['slug'] ?? '');
        expect($slug)->not->toBeEmpty();
        $loc = $slug === StektipsController::HUB_SLUG
            ? route('vermeerderen')
            : route('stektips.artikel', ['slug' => $slug]);
        $canonicals[] = $loc;
        expect($xml)->toContain('<loc>'.$loc.'</loc>');
    }

    expect($canonicals)->toHaveCount(36)
        ->and(count(array_unique($canonicals)))->toBe(36);

    // Hub staat niet als oude artikel-URL in de sitemap.
    expect($xml)->not->toContain('/stektips/artikel/vermeerderen<');

    // Telling: aantal canonieke artikel-locs in de sitemap = kennisbank.
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
    $artikelLocs = array_values(array_filter(
        $m[1],
        static function (string $url) use ($canonicals): bool {
            return in_array($url, $canonicals, true);
        }
    ));
    expect($artikelLocs)->toHaveCount(count($artikelen));
});
