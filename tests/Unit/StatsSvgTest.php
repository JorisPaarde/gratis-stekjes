<?php

declare(strict_types=1);

use App\Support\StatsSvg;

test('dual line chart keeps last x-label and drops overlapping previous', function () {
    $rows = [];
    for ($i = 0; $i < 30; $i++) {
        $day = now()->subDays(29 - $i);
        $rows[] = [
            'label' => $day->format('d-m'),
            'a' => $i % 3,
            'b' => $i % 5,
        ];
    }

    $svg = StatsSvg::dualLineChart(
        $rows,
        'Test overzicht',
        'Tekstfallback',
        'A',
        'B',
    );

    $last = $rows[29]['label'];
    $prev = $rows[28]['label'];

    expect($svg)->toContain('>'.$last.'</text>');

    // With 30 points and min-gap, the penultimate date must not both appear as labels.
    preg_match_all('/>(\d{2}-\d{2})<\/text>/', $svg, $matches);
    $labels = $matches[1] ?? [];

    expect($labels)->toContain($last);
    if (in_array($prev, $labels, true)) {
        // If both somehow remain, they must not be consecutive candidates with tiny gap —
        // for this fixture they should not both be present.
        expect($labels)->not->toContain($prev);
    }
});

test('admin.css prevents KPI neighbour stretch on open details', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/admin.css');
    expect($css)->toContain('align-items: start')
        ->and($css)->toContain('li:has(> .stats-kpi-card[open])')
        ->and($css)->toContain('grid-column: 1 / -1');
});
