<?php

declare(strict_types=1);

/**
 * Lightweight CSS cascade checks: last matching declaration wins.
 * Guards the regression where .cta-panel got a light --card background
 * while headings/body stayed white.
 */
function cssLastDecl(string $css, string $selector, string $property): ?string
{
    $pattern = '/'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/s';
    if (! preg_match_all($pattern, $css, $blocks)) {
        return null;
    }
    $value = null;
    foreach ($blocks[1] as $body) {
        if (preg_match('/'.preg_quote($property, '/').'\s*:\s*([^;}]+)/i', $body, $m)) {
            $value = trim($m[1]);
        }
    }

    return $value;
}

function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $channels = array_map(
        static function (string $part): float {
            $c = hexdec($part) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        },
        str_split($hex, 2)
    );

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrastRatio(string $a, string $b): float
{
    $l1 = relativeLuminance($a);
    $l2 = relativeLuminance($b);
    $light = max($l1, $l2);
    $dark = min($l1, $l2);

    return ($light + 0.05) / ($dark + 0.05);
}

test('cta-panel houdt donkere achtergrond met lichte tekst (geen witte-op-licht regressie)', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));
    expect($css)->not->toBeFalse();

    $bg = cssLastDecl($css, '.cta-panel', 'background');
    expect($bg)->not->toBeNull();
    expect($bg)->toContain('#2f6b45');
    expect($bg)->not->toContain('var(--card)');

    $color = cssLastDecl($css, '.cta-panel', 'color');
    expect($color)->toBe('#f5faf3');

    $h2 = cssLastDecl($css, '.cta-panel h2', 'color');
    expect($h2)->toBe('#fff');

    // Panel body text on leaf green ≥ AA
    expect(contrastRatio('#e8f2e4', '#2f6b45'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#ffffff', '#2f6b45'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#ffe2b8', '#2f6b45'))->toBeGreaterThanOrEqual(4.5);
});

test('homepage-tekstkleuren halen WCAG AA op lichte achtergrond', function () {
    // Tokens uit :root — hard gecheckt zodat een lichte accent-kleur niet terugkruipt als bodytekst.
    expect(contrastRatio('#1c2e22', '#eef4ea'))->toBeGreaterThanOrEqual(4.5); // ink
    expect(contrastRatio('#3d5345', '#eef4ea'))->toBeGreaterThanOrEqual(4.5); // ink-soft
    expect(contrastRatio('#1f4d32', '#eef4ea'))->toBeGreaterThanOrEqual(4.5); // leaf-deep
    expect(contrastRatio('#8a4a1a', '#eef4ea'))->toBeGreaterThanOrEqual(4.5); // accent-text
    expect(contrastRatio('#7a3b12', '#fff4e6'))->toBeGreaterThanOrEqual(4.5); // warn on warn-card
});

test('rise-animatie alleen bij no-preference; reduced-motion forceert zichtbaarheid', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));

    expect($css)->toContain('@media (prefers-reduced-motion: no-preference)');
    expect($css)->toContain('@media (prefers-reduced-motion: reduce)');
    expect($css)->toMatch('/\.cta-panel\s*\{[^}]*opacity:\s*1/s');
    expect($css)->toMatch('/prefers-reduced-motion:\s*reduce[\s\S]*?\.cta-panel[\s\S]*?opacity:\s*1\s*!important/');
});
