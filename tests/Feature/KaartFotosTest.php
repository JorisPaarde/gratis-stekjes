<?php

declare(strict_types=1);

use App\Services\Kennisbank;
use App\Support\ArtikelFotos;

/**
 * «Meer over dit onderwerp»: elk kaartje heeft een eigen, unieke foto uit het artikel-fotomanifest.
 * Geen lege kaartjes, geen dubbele foto's in een blok en geen terugval op soortfoto's.
 */
test('elk kaartartikel heeft precies één eigen kaartfoto die op schijf bestaat', function () {
    $kennisbank = app(Kennisbank::class);
    $fotos = ArtikelFotos::manifest()['fotos'] ?? [];

    $perSlug = [];
    foreach ($fotos as $id => $foto) {
        $voor = $foto['kaart_voor'] ?? null;
        if (is_string($voor) && $voor !== '') {
            expect($perSlug)->not->toHaveKey($voor, "Twee kaartfoto's voor {$voor}");
            $perSlug[$voor] = (string) $id;
        }
    }

    $kaartIds = [];
    foreach ($kennisbank->all() as $artikel) {
        $kinderen = $kennisbank->childrenOf($artikel);
        if ($kinderen === []) {
            continue;
        }
        $ouder = (string) $artikel['slug'];
        $eigen = array_keys(array_filter($fotos, fn ($f) => ($f['artikel'] ?? null) === $ouder));
        $inBlok = [];
        foreach ($kinderen as $kind) {
            $slug = (string) $kind['slug'];
            $id = ArtikelFotos::kaartFotoId($slug);
            expect($id)->not->toBeNull("Kaartje {$slug} op {$ouder} heeft geen kaartfoto");
            expect($inBlok)->not->toContain($id, "Dubbele kaartfoto {$id} op {$ouder}");
            expect($eigen)->not->toContain($id, "Kaartfoto {$id} staat al op {$ouder} zelf");
            $inBlok[] = $id;
            $kaartIds[$id] = true;

            $foto = ArtikelFotos::get($id);
            expect($foto['alt'] ?? '')->not->toBeEmpty("{$id} mist alt-tekst")
                ->and($foto['maker'] ?? '')->not->toBeEmpty("{$id} mist maker")
                ->and($foto['variants'] ?? [])->not->toBeEmpty();
            foreach ($foto['variants'] as $v) {
                foreach (['avif', 'webp', 'jpg'] as $fmt) {
                    expect(is_file(public_path($v[$fmt])))->toBeTrue("Ontbreekt: {$v[$fmt]}");
                }
            }
        }
    }

    // Nieuwe kaartfoto's (k-*) zijn nergens anders voor gebruikt en hebben een Unsplash-bron.
    foreach ($fotos as $id => $foto) {
        if (str_starts_with((string) $id, 'k-')) {
            expect($foto['artikel'] ?? null)->toBeNull()
                ->and((string) ($foto['bron_url'] ?? ''))->toStartWith('https://unsplash.com/photos/');
        }
    }
    expect(count($kaartIds))->toBeGreaterThan(20);
});

test('kaartjesblok rendert per kaartje een eigen <picture>, zonder dubbele of lege kaartjes', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();
    expect(preg_match('#<ul class="kb-kinderen">(.*?)</ul>#s', $html, $m))->toBe(1);
    $blok = $m[1];

    $items = substr_count($blok, '<li>');
    preg_match_all('#<img\s[^>]*src="([^"]+)"#', $blok, $imgs);
    expect($items)->toBeGreaterThan(0)
        ->and(substr_count($blok, '<picture'))->toBe($items)
        ->and(count($imgs[1]))->toBe($items)
        ->and(count(array_unique($imgs[1])))->toBe($items)
        ->and($blok)->toContain('type="image/avif"')
        ->and($blok)->toContain('loading="lazy"')
        ->and($blok)->not->toContain('alt=""')
        ->and($blok)->not->toContain('soort-fallback');
    foreach ($imgs[1] as $src) {
        expect($src)->toContain('assets/images/artikelen/');
    }

    // Credits noemen ook de makers van de kaartfoto's.
    expect($html)->toContain('Foto’s: ');
})->with([
    'snoeien' => fn () => route('stektips.artikel', ['slug' => 'snoeien']),
    'stekken' => fn () => route('stektips.artikel', ['slug' => 'stekken']),
    'natuurlijk-bewortelen' => fn () => route('stektips.artikel', ['slug' => 'natuurlijk-bewortelen']),
    'afleggen' => fn () => route('stektips.artikel', ['slug' => 'afleggen']),
    'vermeerderen' => fn () => route('vermeerderen'),
]);
