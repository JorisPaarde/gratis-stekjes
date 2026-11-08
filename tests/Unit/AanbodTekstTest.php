<?php

declare(strict_types=1);

use App\Support\AanbodTekst;

test('normalize trimt unicode-whitespace', function () {
    expect(AanbodTekst::normalize("  munt  "))->toBe('munt')
        ->and(AanbodTekst::normalize("\u{00A0}munt\u{00A0}"))->toBe('munt')
        ->and(AanbodTekst::normalize("\t\n"))->toBe('')
        ->and(AanbodTekst::normalize("\u{00A0}\u{2003}"))->toBe('');
});

test('isUsable weigert leeg en whitespace', function () {
    expect(AanbodTekst::isUsable(''))->toBeFalse()
        ->and(AanbodTekst::isUsable('   '))->toBeFalse()
        ->and(AanbodTekst::isUsable("\u{00A0}"))->toBeFalse()
        ->and(AanbodTekst::isUsable('Tradescantia'))->toBeTrue();
});
