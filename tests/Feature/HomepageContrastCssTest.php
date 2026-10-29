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
        // Geen match op border-color bij property "color"
        if (preg_match('/(?<![\w-])'.preg_quote($property, '/').'\s*:\s*([^;}]+)/i', $body, $m)) {
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
    expect($bg)->toContain('#1f4d2b');
    expect($bg)->not->toContain('var(--card)');

    $color = cssLastDecl($css, '.cta-panel', 'color');
    expect($color)->toBe('#f5faf3');

    $h2 = cssLastDecl($css, '.cta-panel h2', 'color');
    expect($h2)->toBe('#fff');

    // Panel body text on forest ≥ AA
    expect(contrastRatio('#e8f2e4', '#1f4d2b'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#ffffff', '#1f4d2b'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#ffe2b8', '#1f4d2b'))->toBeGreaterThanOrEqual(4.5);
});

test('homepage-tekstkleuren halen WCAG AA op lichte achtergrond', function () {
    expect(contrastRatio('#1a1210', '#ffffff'))->toBeGreaterThanOrEqual(4.5); // ink
    expect(contrastRatio('#3d4a3f', '#ffffff'))->toBeGreaterThanOrEqual(4.5); // ink-soft
    expect(contrastRatio('#1f4d2b', '#ffffff'))->toBeGreaterThanOrEqual(4.5); // forest
    expect(contrastRatio('#8a4514', '#e6f2e1'))->toBeGreaterThanOrEqual(4.5); // accent-text op mint
    expect(contrastRatio('#1a1210', '#fff1e4'))->toBeGreaterThanOrEqual(4.5); // warn-card ink
    // Oranje knoppen: donkere tekst (wit op #e8833a faalt AA)
    expect(contrastRatio('#ffffff', '#e8833a'))->toBeLessThan(4.5);
    expect(contrastRatio('#1a1210', '#e8833a'))->toBeGreaterThanOrEqual(4.5);
});

test('page-title is wit op bosgroen (niet overschreven door .section h1)', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));

    $color = cssLastDecl($css, '.page-title', 'color');
    $bg = cssLastDecl($css, '.page-title', 'background');
    expect($color)->toBe('#fff');
    expect($bg)->toBe('var(--forest)');

    // Specificiteit-guard: gecombineerde selector aanwezig
    expect($css)->toMatch('/\.section\s+h1\.page-title/');
    expect(contrastRatio('#ffffff', '#1f4d2b'))->toBeGreaterThanOrEqual(4.5);
});

test('warn-card gebruikt nieuw palet (geen peach/rood-bruin)', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));
    $bg = cssLastDecl($css, '.warn-card', 'background');
    $color = cssLastDecl($css, '.warn-card', 'color');
    expect($bg)->toBe('var(--warn-bg)');
    expect($color)->toBe('var(--ink)');
    expect($css)->not->toContain('background: #fff4e6');
    expect($css)->toContain('--warn-bg: #fff1e4');
    expect(contrastRatio('#1a1210', '#fff1e4'))->toBeGreaterThanOrEqual(4.5);
});

test('header: nowrap + hamburger tot 1200px; iconen verborgen op desktop', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));
    expect($css)->toContain('@media (min-width: 1200px)');
    expect($css)->not->toContain('@media (min-width: 880px)');
    expect($css)->toMatch('/\.brand\s*\{[^}]*white-space:\s*nowrap/s');
    expect($css)->toMatch('/\.site-nav a\s*\{[^}]*white-space:\s*nowrap/s');
    expect($css)->toMatch('/\.nav-cta\s*\{[^}]*white-space:\s*nowrap/s');
    expect($css)->toMatch('/min-width:\s*1200px[\s\S]*?\.nav-toggle\s*\{\s*display:\s*none/');
    expect($css)->toMatch('/min-width:\s*1200px[\s\S]*?\.site-nav \.icon\s*\{\s*display:\s*none/');
});

test('hero- en mint-secties halen WCAG AA', function () {
    // Hero titelblok: wit op bosgroen
    expect(contrastRatio('#ffffff', '#1f4d2b'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#f5faf3', '#163820'))->toBeGreaterThanOrEqual(4.5); // lead op scrim
    expect(contrastRatio('#ffffff', '#163820'))->toBeGreaterThanOrEqual(4.5);

    // section--mint
    expect(contrastRatio('#1f4d2b', '#e6f2e1'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#1a1210', '#e6f2e1'))->toBeGreaterThanOrEqual(4.5);
    expect(contrastRatio('#3d4a3f', '#e6f2e1'))->toBeGreaterThanOrEqual(4.5);
});

test('rise-animatie alleen bij no-preference; reduced-motion forceert zichtbaarheid', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));

    expect($css)->toContain('@media (prefers-reduced-motion: no-preference)');
    expect($css)->toContain('@media (prefers-reduced-motion: reduce)');
    expect($css)->toMatch('/\.cta-panel\s*\{[^}]*opacity:\s*1/s');
    expect($css)->toMatch('/prefers-reduced-motion:\s*reduce[\s\S]*?\.cta-panel[\s\S]*?opacity:\s*1\s*!important/');
});

test('fonts zijn self-hosted (geen Google Fonts CDN in layout)', function () {
    $layout = file_get_contents(base_path('resources/views/layouts/site.blade.php'));
    expect($layout)->not->toContain('fonts.googleapis.com');
    expect($layout)->not->toContain('fonts.gstatic.com');

    $fontsCss = file_get_contents(base_path('resources/css/fonts.css'));
    expect($fontsCss)->toContain('Passion One');
    expect($fontsCss)->toContain('Titillium Web');
    expect($fontsCss)->toContain('/fonts/');

    expect(file_exists(public_path('fonts/titillium-web-latin-400-normal.woff2')))->toBeTrue();
    expect(file_exists(public_path('fonts/passion-one-latin-700-normal.woff2')))->toBeTrue();
});

test('palette-tokens volgen design-stijl (bosgroen/mint/oranje)', function () {
    $css = file_get_contents(base_path('resources/css/site.css'));
    expect($css)->toContain('--forest: #1f4d2b');
    expect($css)->toContain('--mint: #e6f2e1');
    expect($css)->toContain('--orange: #e8833a');
    expect($css)->toContain('--orange-text: #1a1210');
    expect($css)->not->toContain('--teal: #1f6b74');
    expect($css)->not->toContain('--peach: #f4cfc2');
    expect($css)->toContain('"Passion One"');
    expect($css)->toContain('"Titillium Web"');
});
