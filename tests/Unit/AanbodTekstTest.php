<?php

declare(strict_types=1);

use App\Support\AanbodTekst;

test('lege en whitespace-namen zijn niet bruikbaar; echte naam wel', function () {
    expect(AanbodTekst::isUsable(''))->toBeFalse()
        ->and(AanbodTekst::isUsable('   '))->toBeFalse()
        ->and(AanbodTekst::isUsable("\u{00A0}"))->toBeFalse()
        ->and(AanbodTekst::isUsable('Tradescantia'))->toBeTrue();
});

test('FORM-whitespace codepoints (incl. NEL/NBSP/FEFF) normaliseren tot lege string', function () {
    foreach (AanbodTekst::formWhitespaceCodepoints() as $cp) {
        expect(AanbodTekst::normalize($cp))->toBe('')
            ->and(AanbodTekst::isUsable($cp))->toBeFalse()
            ->and(AanbodTekst::isUsable($cp.$cp.$cp))->toBeFalse();
    }

    // Alleen NEL (U+0085) — de JS-\s mismatch-case.
    expect(AanbodTekst::isUsable("\u{0085}"))->toBeFalse()
        ->and(AanbodTekst::normalize("\u{0085}\u{0085}"))->toBe('');

    // FEFF + NBSP mix.
    expect(AanbodTekst::isUsable("\u{FEFF}\u{00A0}"))->toBeFalse();

    // Echte naam met spaties blijft bruikbaar.
    expect(AanbodTekst::normalize("  Munt  "))->toBe('Munt')
        ->and(AanbodTekst::isUsable("\u{00A0}Munt\u{0085}"))->toBeTrue();
});

test('JS FORM_WS class dekt dezelfde codepoints als PHP normalize', function () {
    $jsClass = AanbodTekst::FORM_WS_JS;
    // Smoke: class string bevat NEL/NBSP/FEFF escapes die de Blade-script gebruikt.
    expect($jsClass)->toContain('\u0085')
        ->and($jsClass)->toContain('\u00A0')
        ->and($jsClass)->toContain('\uFEFF')
        ->and($jsClass)->toContain('\u2000-\u200A');
});
