<?php

declare(strict_types=1);

test('stekken-artikel toont kaarten, kalender, stappen, vergelijking, soorten en video-facades', function () {
    $html = $this->get(route('stektips.artikel', ['slug' => 'stekken']))->assertOk()->getContent();

    expect($html)->toContain('class="kb-kaarten"')
        ->and($html)->toContain('class="kb-kalender"')
        ->and($html)->toContain('class="kb-stappen"')
        ->and($html)->toContain('class="kb-vergelijk"')
        ->and($html)->toContain('class="kb-soorten"')
        ->and($html)->toContain('data-video-id="1ve5eEls-cg"')
        ->and($html)->toContain('class="kb-inhoud"')
        ->and($html)->toContain('class="kb-kinderen"');

    // Geen iframe vooraf: YouTube laadt pas na klik.
    expect($html)->not->toContain('<iframe');
});

test('snoeien-artikel toont nieuw/oud hout, snoeikalender per maand met «nu» en video\'s', function () {
    $html = $this->get(route('stektips.artikel', ['slug' => 'snoeien']))->assertOk()->getContent();
    expect($html)->toContain('Bloeit op nieuw hout')
        ->and($html)->toContain('class="kb-maanden"')
        ->and(preg_match_all('/<li class="kb-maand( is-nu)?"/', $html))->toBe(12)
        ->and($html)->toContain('kb-nu-label')
        ->and($html)->toContain('data-video-id="DDDNKRT0mvY"');
});

test('stekken- en snoeien-artikelen hebben echte foto\'s, geen ytimg, en bestaande SVG-plekken', function () {
    $stekken = $this->get(route('stektips.artikel', ['slug' => 'stekken']))->assertOk()->getContent();
    $snoeien = $this->get(route('stektips.artikel', ['slug' => 'snoeien']))->assertOk()->getContent();

    expect($stekken)->toContain('assets/images/artikelen/s01-')
        ->and($stekken)->toContain('Vensterbank met een rij glazen potjes waarin stekjes wortelen')
        ->and($stekken)->toContain('assets/images/artikelen/v1-')
        ->and($stekken)->toContain('illu--winterhout')
        ->and($stekken)->toContain('illu--afleggen')
        ->and($stekken)->toContain('Foto’s: ')
        ->and($stekken)->toContain('/ Unsplash')
        ->and($stekken)->not->toContain('ytimg')
        ->and($stekken)->not->toContain('i.ytimg.com');

    expect($snoeien)->toContain('assets/images/artikelen/n01-')
        ->and($snoeien)->toContain('Snoeischaar knipt een groene scheut boven een knop')
        ->and($snoeien)->toContain('assets/images/artikelen/v5-')
        ->and($snoeien)->toContain('illu--snoeisnede')
        ->and($snoeien)->toContain('Foto’s: ')
        ->and($snoeien)->not->toContain('ytimg');

    // Alle img/source-URL’s van artikel-foto’s bestaan op schijf.
    preg_match_all('#(?:src|srcset)="([^"]*assets/images/artikelen/[^"\s]+)#', $stekken.$snoeien, $matches);
    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $url) {
        $path = public_path(ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/'));
        // srcset entries can be absolute asset URLs; strip to path after /assets/
        if (! is_file($path)) {
            if (preg_match('#(assets/images/artikelen/[^"\s]+)#', $url, $m)) {
                $path = public_path($m[1]);
            }
        }
        expect(is_file($path))->toBeTrue("Ontbreekt: {$url}");
    }
});

test('CSP staat alleen youtube-nocookie als frame toe', function () {
    $csp = $this->get(route('stektips.artikel', ['slug' => 'stekken']))->headers->get('Content-Security-Policy');
    expect($csp)->toContain('frame-src https://www.youtube-nocookie.com')
        ->and($csp)->not->toContain('frame-src *')
        ->and($csp)->not->toContain('ytimg');
});

test('privacy vermeldt video\'s', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('youtube-nocookie.com', false);
});
