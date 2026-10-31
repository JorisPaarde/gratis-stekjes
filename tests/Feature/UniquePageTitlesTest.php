<?php

declare(strict_types=1);

use App\Services\InvasieveSoorten;
use App\Services\Steden;
use App\Services\Stektips;
use App\Support\SeoTitle;

/**
 * SEO: unieke <title>-strings op kernroutes (doelgroep-titles-keywords §2–3).
 * Merk-suffix " · Gratis planten" alleen als het geheel ≤ 60 tekens blijft.
 */
function pageTitleFromHtml(string $html): string
{
    expect($html)->toMatch('/<title>(.*?)<\/title>/s');
    preg_match('/<title>(.*?)<\/title>/s', $html, $m);

    return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

test('SeoTitle voegt merk alleen toe onder 60 tekens', function () {
    expect(SeoTitle::document('Gratis planten en stekjes delen in je buurt'))
        ->toBe('Gratis planten en stekjes delen in je buurt · Gratis planten')
        ->and(mb_strlen(SeoTitle::document('Gratis planten en stekjes delen in je buurt')))->toBe(60);

    $long = 'Plantenasiel, plantenbieb en ruilkastje in je buurt';
    expect(SeoTitle::document($long))->toBe($long)
        ->and(SeoTitle::fitsWithBrand($long))->toBeFalse();
});

test('kernroutes hebben unieke title-tags volgens keyword-onderzoek', function () {
    $invasiefAantal = count(app(InvasieveSoorten::class)->all());

    $routes = [
        [
            'get' => route('home'),
            'expect' => 'Gratis planten en stekjes delen in je buurt · Gratis planten',
            'h1' => 'Gratis planten en stekjes in je buurt',
        ],
        [
            'get' => route('aanbod'),
            'expect' => 'Aanbod: gratis planten en stekjes ophalen · Gratis planten',
            'h1' => 'Gratis planten en stekjes ophalen',
        ],
        [
            'get' => route('aanbod', ['plant' => 'Monstera']),
            'expect' => 'Gratis Monstera-stekjes · Gratis planten',
            'h1' => 'Gratis Monstera-stekjes',
        ],
        [
            'get' => route('stektips.index'),
            'contains' => 'stekken',
        ],
        [
            'get' => route('stektips.show', ['slug' => 'monstera']),
            'expect' => 'Monstera stekken en gratis stekjes · Gratis planten',
            'h1' => 'Monstera stekken: zo doe je het',
        ],
        [
            'get' => route('stektips.show', ['slug' => 'pelargonium']),
            'expect' => 'Geranium stekken en gratis stekjes · Gratis planten',
            'h1' => 'Geranium stekken: zo doe je het',
        ],
        [
            'get' => route('stektips.show', ['slug' => 'pilea']),
            'expect' => 'Pannenkoekplant stekken en gratis stekjes · Gratis planten',
            'h1' => 'Pannenkoekplant stekken: zo doe je het',
        ],
        [
            'get' => route('gratis-planten.index'),
            'expect' => 'Gratis planten ophalen in jouw stad · Gratis planten',
            'h1' => 'Gratis planten ophalen in jouw stad',
        ],
        [
            'get' => route('gratis-planten.show', ['stad' => 'amsterdam']),
            'expect' => 'Gratis planten en stekjes in Amsterdam · Gratis planten',
            'h1' => 'Gratis planten en stekjes in Amsterdam',
        ],
        [
            'get' => route('gratis-planten.show', ['stad' => 'utrecht']),
            'expect' => 'Gratis planten en stekjes in Utrecht · Gratis planten',
            'h1' => 'Gratis planten en stekjes in Utrecht',
        ],
        [
            'get' => route('deelplekken'),
            'expect' => 'Plantenasiel, plantenbieb en ruilkastje in je buurt',
            'h1' => 'Plantenasiels, plantenbiebs en ruilkastjes in Nederland',
        ],
        [
            'get' => route('invasieve-soorten'),
            'expect' => 'Invasieve exoten: '.$invasiefAantal.' planten die je niet deelt',
            'h1' => 'Invasieve exoten: deze planten deel je niet',
        ],
        [
            'get' => route('partners'),
            'expect' => 'Stekjesmarkt, plantenruilbeurs en ruilinitiatieven',
            'h1' => 'Plantenruilbeurzen, stekjesmarkten en initiatieven',
        ],
        [
            'get' => route('stektips.artikel', ['slug' => 'stekken']),
            'expect' => 'Stekken: zo maak je gratis nieuwe planten · Gratis planten',
            'h1' => 'Stekken: zo maak je gratis nieuwe planten',
        ],
        [
            'get' => route('stektips.artikel', ['slug' => 'snoeien']),
            'expect' => 'Snoeien: wanneer en hoe per plant · Gratis planten',
            'h1' => 'Snoeien: wanneer en hoe per plant',
        ],
        [
            'get' => route('stektips.artikel', ['slug' => 'stekpoeder']),
            'expect' => 'Stekpoeder: wanneer het helpt en duurzame opties',
        ],
        [
            'get' => route('stektips.artikel', ['slug' => 'permacultuur-gilde-voedselbos']),
            'expect' => 'Permacultuur en voedselbos: planten die elkaar helpen',
            'h1' => 'Permacultuur, gilde en voedselbos',
        ],
        [
            'get' => route('stektips.artikel', ['slug' => 'wilgenwater']),
            'contains' => 'Wilgenwater',
        ],
    ];

    $titles = [];

    foreach ($routes as $route) {
        $html = $this->get($route['get'])->assertOk()->getContent();
        $title = pageTitleFromHtml($html);

        if (isset($route['expect'])) {
            expect($title)->toBe($route['expect']);
        }
        if (isset($route['contains'])) {
            expect(mb_strtolower($title))->toContain(mb_strtolower($route['contains']));
        }
        if (isset($route['h1'])) {
            expect($html)->toContain($route['h1'].'</h1>');
        }

        expect(mb_strlen($title))->toBeLessThanOrEqual(60);
        $titles[] = $title;
    }

    expect($titles)->toHaveCount(count(array_unique($titles)));
});

test('soortpagina’s en steden hebben onderling unieke titles', function () {
    $titles = [];

    foreach (app(Stektips::class)->all() as $soort) {
        $html = $this->get(route('stektips.show', ['slug' => $soort['slug']]))
            ->assertOk()
            ->getContent();
        $title = pageTitleFromHtml($html);
        $expectedPage = $soort['naam'].' stekken en gratis stekjes';
        expect($title)->toBe(SeoTitle::document($expectedPage))
            ->and($html)->toContain('>'.$soort['naam'].' stekken: zo doe je het</h1>')
            ->and($titles)->not->toContain($title);
        $titles[] = $title;
    }

    foreach (app(Steden::class)->all() as $stad) {
        $html = $this->get(route('gratis-planten.show', ['stad' => $stad['slug']]))
            ->assertOk()
            ->getContent();
        $title = pageTitleFromHtml($html);
        $expectedPage = 'Gratis planten en stekjes in '.$stad['naam'];
        expect($title)->toBe(SeoTitle::document($expectedPage))
            ->and($titles)->not->toContain($title);
        $titles[] = $title;
    }
});

test('stadspagina toont plantenasiel-blok alleen bij echte plantenasiels', function () {
    // Utrecht: plantenasiel verborgen (niet-gratis); wel andere deelplekken (zadenbieb).
    $html = $this->get(route('gratis-planten.show', ['stad' => 'utrecht']))
        ->assertOk()
        ->assertDontSee('Plantenasiel in Utrecht', false)
        ->assertSee('Zadenbibliotheek Utrecht', false)
        ->getContent();

    expect($html)->toContain('/deelplekken?')
        ->and($html)->toContain('plaats=Utrecht');

    // Amsterdam: geen openbaar plantenasiel-adres in de data → geen verzonnen blok.
    $amsHtml = $this->get(route('gratis-planten.show', ['stad' => 'amsterdam']))
        ->assertOk()
        ->getContent();
    expect($amsHtml)->not->toContain('Plantenasiel in Amsterdam');
});
