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

test('CSP staat alleen youtube-nocookie als frame toe', function () {
    $csp = $this->get(route('stektips.artikel', ['slug' => 'stekken']))->headers->get('Content-Security-Policy');
    expect($csp)->toContain('frame-src https://www.youtube-nocookie.com')
        ->and($csp)->not->toContain('frame-src *');
});

test('privacy vermeldt video\'s', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('youtube-nocookie.com', false);
});
