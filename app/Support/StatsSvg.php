<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-rendered inline SVG helpers for beheer-statistieken.
 * No external libs; colours from the site palette.
 */
final class StatsSvg
{
    public const COLOR_LEAF = '#1f4d2b';

    public const COLOR_LEAF_DEEP = '#163820';

    public const COLOR_ORANGE = '#e8833a';

    public const COLOR_SAGE = '#5a5b42';

    public const COLOR_MINT = '#e6f2e1';

    public const COLOR_INK_SOFT = '#3d4a3f';

    public const COLOR_LINE = 'rgba(26, 18, 16, 0.12)';

    /**
     * Tiny sparkline (polyline) for a KPI card.
     *
     * @param  list<int|float>  $values
     */
    public static function sparkline(
        array $values,
        string $ariaLabel,
        int $width = 72,
        int $height = 24,
        string $stroke = self::COLOR_LEAF,
    ): string {
        if ($values === []) {
            return '';
        }

        $max = max(1.0, (float) max($values));
        $n = count($values);
        $padY = 2.0;
        $usableH = max(1.0, $height - ($padY * 2));
        $points = [];
        foreach ($values as $i => $v) {
            $x = $n === 1 ? $width / 2 : ($i / ($n - 1)) * $width;
            $y = $height - $padY - (((float) $v / $max) * $usableH);
            $points[] = round($x, 2).','.round($y, 2);
        }
        $pointsAttr = htmlspecialchars(implode(' ', $points), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg class="stats-sparkline" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false">'
            .'<title>'.$label.'</title>'
            .'<polyline fill="none" stroke="'.$stroke.'" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" points="'.$pointsAttr.'" />'
            .'</svg>';
    }

    /**
     * Vertical bar chart for a daily series.
     *
     * @param  list<array{label: string, value: int|float, title?: string}>  $rows
     */
    public static function barChart(
        array $rows,
        string $ariaLabel,
        string $textFallback,
        int $width = 640,
        int $height = 220,
        string $fill = self::COLOR_LEAF,
    ): string {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n = count($rows);
        if ($n === 0) {
            return self::emptyChart($ariaLabel, $textFallback, $width, $height);
        }

        $max = max(1.0, (float) max(array_map(fn (array $r) => (float) $r['value'], $rows)));
        $padL = 28;
        $padR = 8;
        $padT = 16;
        $padB = 36;
        $plotW = max(1, $width - $padL - $padR);
        $plotH = max(1, $height - $padT - $padB);
        $gap = $n > 20 ? 1.5 : 2.5;
        $barW = max(2.0, ($plotW / $n) - $gap);

        $bars = '';
        $labels = '';
        $showEvery = $n > 16 ? (int) ceil($n / 10) : 1;
        foreach ($rows as $i => $row) {
            $v = (float) $row['value'];
            $h = ($v / $max) * $plotH;
            $x = $padL + ($i * ($plotW / $n)) + ($gap / 2);
            $y = $padT + $plotH - $h;
            $title = htmlspecialchars((string) ($row['title'] ?? ($row['label'].': '.$row['value'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $bars .= '<rect x="'.round($x, 2).'" y="'.round($y, 2).'" width="'.round($barW, 2).'" height="'.round(max(0, $h), 2).'" rx="1.5" fill="'.$fill.'">'
                .'<title>'.$title.'</title></rect>';
            if ($i % $showEvery === 0 || $i === $n - 1) {
                $lx = $x + ($barW / 2);
                $labels .= '<text x="'.round($lx, 2).'" y="'.($height - 10).'" text-anchor="middle" font-size="9" fill="'.self::COLOR_INK_SOFT.'">'
                    .htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .'</text>';
            }
        }

        $yMaxLabel = htmlspecialchars((string) (int) round($max), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg class="stats-svg-chart" width="100%" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false" preserveAspectRatio="xMidYMid meet">'
            .'<title>'.$label.'</title>'
            .'<desc>'.$fallback.'</desc>'
            .'<line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.($padT + $plotH).'" stroke="'.self::COLOR_LINE.'" stroke-width="1" />'
            .'<line x1="'.$padL.'" y1="'.($padT + $plotH).'" x2="'.($width - $padR).'" y2="'.($padT + $plotH).'" stroke="'.self::COLOR_LINE.'" stroke-width="1" />'
            .'<text x="'.($padL - 4).'" y="'.($padT + 4).'" text-anchor="end" font-size="9" fill="'.self::COLOR_INK_SOFT.'">'.$yMaxLabel.'</text>'
            .'<text x="'.($padL - 4).'" y="'.($padT + $plotH).'" text-anchor="end" font-size="9" fill="'.self::COLOR_INK_SOFT.'">0</text>'
            .$bars
            .$labels
            .'</svg>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>';
    }

    /**
     * Dual-series line chart (e.g. signups + visitors).
     *
     * @param  list<array{label: string, a: int|float, b: int|float}>  $rows
     */
    public static function dualLineChart(
        array $rows,
        string $ariaLabel,
        string $textFallback,
        string $labelA,
        string $labelB,
        int $width = 640,
        int $height = 240,
        string $strokeA = self::COLOR_LEAF,
        string $strokeB = self::COLOR_ORANGE,
    ): string {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $n = count($rows);
        if ($n === 0) {
            return self::emptyChart($ariaLabel, $textFallback, $width, $height);
        }

        $max = 1.0;
        foreach ($rows as $row) {
            $max = max($max, (float) $row['a'], (float) $row['b']);
        }

        $padL = 28;
        $padR = 12;
        $padT = 20;
        $padB = 44;
        $plotW = max(1, $width - $padL - $padR);
        $plotH = max(1, $height - $padT - $padB);

        $ptsA = [];
        $ptsB = [];
        $xs = [];
        $showEvery = $n > 16 ? (int) ceil($n / 8) : max(1, (int) floor($n / 6));
        foreach ($rows as $i => $row) {
            $x = $n === 1 ? $padL + ($plotW / 2) : $padL + ($i / ($n - 1)) * $plotW;
            $yA = $padT + $plotH - (((float) $row['a'] / $max) * $plotH);
            $yB = $padT + $plotH - (((float) $row['b'] / $max) * $plotH);
            $ptsA[] = round($x, 2).','.round($yA, 2);
            $ptsB[] = round($x, 2).','.round($yB, 2);
            $xs[$i] = $x;
        }

        // Prefer spaced labels; always keep the last date and drop the previous if too close.
        $minLabelGap = 36.0;
        $labelIndexes = [];
        foreach ($rows as $i => $row) {
            if ($i % $showEvery === 0 || $i === $n - 1) {
                $labelIndexes[] = $i;
            }
        }
        $kept = [];
        foreach ($labelIndexes as $i) {
            $x = $xs[$i];
            if ($i === $n - 1) {
                if ($kept !== [] && abs($x - $xs[end($kept)]) < $minLabelGap) {
                    array_pop($kept);
                }
                $kept[] = $i;
                continue;
            }
            if ($kept === [] || abs($x - $xs[end($kept)]) >= $minLabelGap) {
                $kept[] = $i;
            }
        }

        $xLabels = '';
        foreach ($kept as $i) {
            $xLabels .= '<text x="'.round($xs[$i], 2).'" y="'.($height - 22).'" text-anchor="middle" font-size="9" fill="'.self::COLOR_INK_SOFT.'">'
                .htmlspecialchars((string) $rows[$i]['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</text>';
        }

        $la = htmlspecialchars($labelA, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lb = htmlspecialchars($labelB, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg class="stats-svg-chart" width="100%" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false" preserveAspectRatio="xMidYMid meet">'
            .'<title>'.$label.'</title>'
            .'<desc>'.$fallback.'</desc>'
            .'<line x1="'.$padL.'" y1="'.$padT.'" x2="'.$padL.'" y2="'.($padT + $plotH).'" stroke="'.self::COLOR_LINE.'" stroke-width="1" />'
            .'<line x1="'.$padL.'" y1="'.($padT + $plotH).'" x2="'.($width - $padR).'" y2="'.($padT + $plotH).'" stroke="'.self::COLOR_LINE.'" stroke-width="1" />'
            .'<polyline fill="none" stroke="'.$strokeA.'" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" points="'.htmlspecialchars(implode(' ', $ptsA), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" />'
            .'<polyline fill="none" stroke="'.$strokeB.'" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" points="'.htmlspecialchars(implode(' ', $ptsB), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" />'
            .$xLabels
            .'<g aria-hidden="true">'
            .'<rect x="'.($padL).'" y="'.($height - 14).'" width="10" height="3" fill="'.$strokeA.'" />'
            .'<text x="'.($padL + 14).'" y="'.($height - 5).'" font-size="10" fill="'.self::COLOR_INK_SOFT.'">'.$la.'</text>'
            .'<rect x="'.($padL + 110).'" y="'.($height - 14).'" width="10" height="3" fill="'.$strokeB.'" />'
            .'<text x="'.($padL + 124).'" y="'.($height - 5).'" font-size="10" fill="'.self::COLOR_INK_SOFT.'">'.$lb.'</text>'
            .'</g>'
            .'</svg>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>';
    }

    /**
     * Donut for two complementary parts (e.g. confirmed vs unconfirmed).
     */
    public static function donut(
        int $partA,
        int $partB,
        string $labelA,
        string $labelB,
        string $ariaLabel,
        string $textFallback,
        string $colorA = self::COLOR_LEAF,
        string $colorB = self::COLOR_ORANGE,
        int $size = 180,
    ): string {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $total = max(0, $partA + $partB);
        $cx = $size / 2;
        $cy = $size / 2;
        $r = ($size / 2) - 18;
        $stroke = 22.0;
        $c = 2 * M_PI * $r;

        if ($total === 0) {
            $arcs = '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.self::COLOR_MINT.'" stroke-width="'.$stroke.'" />';
        } else {
            $lenA = ($partA / $total) * $c;
            $lenB = ($partB / $total) * $c;
            $arcs = '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$colorB.'" stroke-width="'.$stroke.'" '
                .'stroke-dasharray="'.round($lenB, 2).' '.round($c - $lenB, 2).'" stroke-dashoffset="0" transform="rotate(-90 '.$cx.' '.$cy.')" />'
                .'<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.$colorA.'" stroke-width="'.$stroke.'" '
                .'stroke-dasharray="'.round($lenA, 2).' '.round($c - $lenA, 2).'" stroke-dashoffset="'.round(-$lenB, 2).'" transform="rotate(-90 '.$cx.' '.$cy.')" />';
        }

        $la = htmlspecialchars($labelA, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lb = htmlspecialchars($labelB, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $center = htmlspecialchars((string) $total, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="stats-donut-wrap">'
            .'<svg class="stats-svg-chart stats-donut" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" role="img" aria-label="'.$label.'" focusable="false">'
            .'<title>'.$label.'</title>'
            .'<desc>'.$fallback.'</desc>'
            .$arcs
            .'<text x="'.$cx.'" y="'.($cy - 2).'" text-anchor="middle" font-size="22" font-weight="700" fill="'.self::COLOR_LEAF_DEEP.'">'.$center.'</text>'
            .'<text x="'.$cx.'" y="'.($cy + 16).'" text-anchor="middle" font-size="11" fill="'.self::COLOR_INK_SOFT.'">totaal</text>'
            .'</svg>'
            .'<ul class="stats-legend" aria-hidden="false">'
            .'<li><span class="stats-swatch" style="background:'.$colorA.'"></span> '.$la.': <strong>'.$partA.'</strong></li>'
            .'<li><span class="stats-swatch" style="background:'.$colorB.'"></span> '.$lb.': <strong>'.$partB.'</strong></li>'
            .'</ul>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>'
            .'</div>';
    }

    /**
     * Horizontal bars for top pages (or similar ranked lists).
     *
     * @param  list<array{label: string, value: int}>  $rows
     */
    public static function horizontalBars(
        array $rows,
        string $ariaLabel,
        string $textFallback,
        int $width = 640,
        string $fill = self::COLOR_LEAF,
    ): string {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($rows === []) {
            return '<p class="muted">'.$fallback.'</p>';
        }

        $max = max(1, max(array_map(fn (array $r) => (int) $r['value'], $rows)));
        $rowH = 28;
        $padL = 8;
        $labelW = 200;
        $valueW = 40;
        $barMaxW = max(40, $width - $padL - $labelW - $valueW - 16);
        $height = (count($rows) * $rowH) + 8;

        $items = '';
        foreach ($rows as $i => $row) {
            $y = 4 + ($i * $rowH);
            $barW = ((int) $row['value'] / $max) * $barMaxW;
            $pathLabel = self::shortPath((string) $row['label'], 28);
            $full = htmlspecialchars((string) $row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $items .= '<text x="'.$padL.'" y="'.($y + 14).'" font-size="11" fill="'.self::COLOR_LEAF_DEEP.'">'
                .'<title>'.$full.'</title>'
                .htmlspecialchars($pathLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                .'</text>'
                .'<rect x="'.($padL + $labelW).'" y="'.($y + 4).'" width="'.round($barW, 2).'" height="14" rx="3" fill="'.$fill.'">'
                .'<title>'.$full.': '.(int) $row['value'].'</title></rect>'
                .'<text x="'.($padL + $labelW + $barW + 6).'" y="'.($y + 15).'" font-size="11" fill="'.self::COLOR_INK_SOFT.'">'
                .(int) $row['value']
                .'</text>';
        }

        return '<svg class="stats-svg-chart stats-hbar" width="100%" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false" preserveAspectRatio="xMinYMin meet">'
            .'<title>'.$label.'</title>'
            .'<desc>'.$fallback.'</desc>'
            .$items
            .'</svg>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>';
    }

    /**
     * Progress / share bar: "X van Y".
     */
    public static function progressShare(
        int $part,
        int $total,
        string $ariaLabel,
        string $textFallback,
        int $width = 320,
        int $height = 48,
        string $fill = self::COLOR_LEAF,
        string $track = self::COLOR_MINT,
    ): string {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pct = $total > 0 ? ($part / $total) : 0.0;
        $barW = max(0, (int) round(($width - 16) * $pct));
        $pctLabel = $total > 0 ? (int) round($pct * 100).'%' : '0%';

        return '<svg class="stats-svg-chart stats-progress" width="100%" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false" preserveAspectRatio="xMidYMid meet">'
            .'<title>'.$label.'</title>'
            .'<desc>'.$fallback.'</desc>'
            .'<rect x="8" y="16" width="'.($width - 16).'" height="16" rx="8" fill="'.$track.'" />'
            .'<rect x="8" y="16" width="'.$barW.'" height="16" rx="8" fill="'.$fill.'" />'
            .'<text x="'.($width / 2).'" y="12" text-anchor="middle" font-size="11" fill="'.self::COLOR_INK_SOFT.'">'
            .htmlspecialchars($part.' van '.$total.' ('.$pctLabel.')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'</text>'
            .'</svg>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>';
    }

    private static function emptyChart(string $ariaLabel, string $textFallback, int $width, int $height): string
    {
        $label = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fallback = htmlspecialchars($textFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg class="stats-svg-chart" width="100%" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.$label.'" focusable="false">'
            .'<title>'.$label.'</title>'
            .'<text x="'.($width / 2).'" y="'.($height / 2).'" text-anchor="middle" font-size="13" fill="'.self::COLOR_INK_SOFT.'">Geen data</text>'
            .'</svg>'
            .'<p class="stats-chart-text visually-hidden">'.$fallback.'</p>';
    }

    private static function shortPath(string $path, int $max): string
    {
        if (mb_strlen($path) <= $max) {
            return $path;
        }

        return '…'.mb_substr($path, -($max - 1));
    }
}
