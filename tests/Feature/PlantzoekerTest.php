<?php

declare(strict_types=1);

use App\Services\Plantensoorten;

test('vrouwentong, gatenplant en monstra leiden naar de juiste plantpagina', function (string $query, string $via) {
    $planten = app(Plantensoorten::class);
    $expected = $via === 'exact'
        ? $planten->resolveExact($query)
        : ($planten->suggestClose($query, 1, onlyWithSlug: true)[0] ?? null);

    expect($expected)->not->toBeNull()
        ->and($expected['slug'] ?? null)->not->toBeNull();

    $this->get(route('stektips.index', ['q' => $query]))
        ->assertRedirect(route('stektips.show', ['slug' => $expected['slug']]));
})->with([
    'vrouwentong' => ['vrouwentong', 'exact'],
    'gatenplant' => ['gatenplant', 'exact'],
    'monstra' => ['monstra', 'fuzzy'],
]);

test('exact één plantmatch stuurt door naar die stektip', function () {
    $planten = app(Plantensoorten::class);
    $munt = $planten->resolveExact('Munt');
    expect($munt)->not->toBeNull()->and($munt['slug'])->toBe('munt');

    $this->get(route('stektips.index', ['q' => 'Munt']))
        ->assertRedirect(route('stektips.show', ['slug' => 'munt']));
});

test('0 resultaten toont Bedoel je met dichtstbijzijnde suggestie', function () {
    // monstrex zit buiten de match-limiet (geen redirect) maar binnen suggestie-afstand 3.
    $this->get(route('stektips.index', ['q' => 'monstrex']))
        ->assertOk()
        ->assertSee('Geen stektips gevonden voor «monstrex»', false)
        ->assertSee('Bedoel je', false)
        ->assertSee('Monstera', false)
        ->assertSee(route('stektips.show', ['slug' => 'monstera']), false);
});

test('/stektips toont de plantzoeker direct onder de titel', function () {
    $html = $this->get(route('stektips.index'))->assertOk()->getContent();

    expect($html)->toContain('Welke plant wil je vermeerderen?')
        ->and($html)->toContain('name="q"')
        ->and($html)->toContain('class="stektips-zoek"')
        ->and($html)->not->toContain('Welke plant wil je stekken?');

    $titelPos = strpos($html, 'class="stektips-titel"');
    $zoekPos = strpos($html, 'Welke plant wil je vermeerderen?');
    $introPos = strpos($html, 'class="section-intro"');

    expect($titelPos)->toBeInt()
        ->and($zoekPos)->toBeInt()
        ->and($introPos)->toBeInt()
        ->and($titelPos)->toBeLessThan($zoekPos)
        ->and($zoekPos)->toBeLessThan($introPos);
});

test('/vermeerderen toont de plantzoeker direct onder de titel', function () {
    $html = $this->get(route('vermeerderen'))->assertOk()->getContent();

    expect($html)->toContain('Welke plant wil je vermeerderen?')
        ->and($html)->toContain('id="vermeerderen-q"')
        ->and($html)->toContain('action="'.route('stektips.index').'"');

    $titelPos = strpos($html, 'class="page-title"');
    $zoekPos = strpos($html, 'Welke plant wil je vermeerderen?');

    expect($titelPos)->toBeInt()
        ->and($zoekPos)->toBeInt()
        ->and($titelPos)->toBeLessThan($zoekPos);
});

test('homepage heeft tekstlink naar de plantzoeker onder het postcodeveld', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('Zoek je hoe je een plant vermeerdert?')
        ->and($html)->toContain('href="'.route('vermeerderen').'"');

    $postcodePos = strpos($html, 'class="hero-zoek"');
    $linkPos = strpos($html, 'Zoek je hoe je een plant vermeerdert?');
    $accountPos = strpos($html, 'class="home-hero-account"');

    expect($postcodePos)->toBeInt()
        ->and($linkPos)->toBeInt()
        ->and($accountPos)->toBeInt()
        ->and($postcodePos)->toBeLessThan($linkPos)
        ->and($linkPos)->toBeLessThan($accountPos);
});

test('bestaande q-param en filters blijven werken naast synoniemzoeken', function () {
    // Meerdere treffers via gewone tekstzoek → geen redirect, wel resultaten.
    $html = $this->get(route('stektips.index', ['q' => 'stek']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('resultaat')
        ->and($html)->toContain('name="q"')
        ->and($html)->toContain('value="stek"');

    $this->get(route('stektips.index', ['categorie' => 'uitlopers']))
        ->assertOk()
        ->assertSee(route('stektips.show', ['slug' => 'aardbei']), false);
});
