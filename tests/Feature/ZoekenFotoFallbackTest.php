<?php

declare(strict_types=1);

test('zoeken.js: broken foto via capture listener, geen markup/quotes in onerror-attribuut', function () {
    $js = (string) file_get_contents(base_path('resources/js/zoeken.js'));

    expect($js)->toContain('addEventListener(')
        ->and($js)->toContain('"error"')
        ->and($js)->toContain('data-aanbod-foto')
        ->and($js)->toContain('replaceBrokenAanbodFoto')
        ->and($js)->toContain('aanbodFotoLeegHtml')
        ->and($js)->not->toContain('JSON.stringify(aanbodFotoLeegHtml')
        ->and($js)->not->toMatch('/onerror\s*=\s*"/')
        ->and($js)->not->toContain("onerror=\"this.onerror");
});
