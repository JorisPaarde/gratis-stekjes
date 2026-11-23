<?php

declare(strict_types=1);

use App\Support\BronLabel;

test('overzichts-URL met /locaties/ heet Bron, geen Website', function () {
    $url = 'https://zadenbibliotheeknederland.nl/locaties/';
    expect(BronLabel::isOverviewUrl($url))->toBeTrue();
    expect(BronLabel::for($url, 'Zadenbibliotheek Aalten'))->toBe(BronLabel::BRON);
});

test('gedeelde URL (count > 1) heet Bron', function () {
    $url = 'https://example.com/gedeeld';
    $counts = [$url => 3];
    expect(BronLabel::isOverviewUrl($url, $counts))->toBeTrue();
    expect(BronLabel::for($url, 'Iets', false, $counts))->toBe(BronLabel::BRON);
});

test('eigen site met passende host heet Website', function () {
    $url = 'https://www.plantenasielzeist.nl/';
    expect(BronLabel::for($url, 'Plantenasiel Zeist — De Boswerf'))->toBe(BronLabel::WEBSITE);
});

test('unieke URL zonder naam-match heet Meer informatie', function () {
    $url = 'https://www.volledig-andere-host.nl/pagina';
    expect(BronLabel::for($url, 'Zadenbibliotheek Foo'))->toBe(BronLabel::MEER_INFO);
});

test('evenementen krijgen altijd Bron', function () {
    expect(BronLabel::for('https://www.plantenasielzeist.nl/', 'X', true))->toBe(BronLabel::BRON);
});

test('strenge similar_text: korte toevallige overlap is geen Website', function () {
    // Oude drempel (>= 8) kon te ruim zijn; overlap moet ≥10 én ≥65%.
    $url = 'https://www.abcdefghij.nl/';
    expect(BronLabel::looksLikeOwnSite($url, 'Zadenbibliotheek Abc'))->toBeFalse();
});
