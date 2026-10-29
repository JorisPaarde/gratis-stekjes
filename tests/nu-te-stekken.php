<?php
/**
 * Tests voor de maandkalender "Nu te stekken".
 * Exit code 0 = alles groen, 1 = mislukt.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/lib/nu-te-stekken.php';

$failed = 0;
$passed = 0;

function assert_true(bool $cond, string $message): void
{
    global $failed, $passed;
    if ($cond) {
        echo "OK  {$message}\n";
        $passed++;
    } else {
        echo "FAIL {$message}\n";
        $failed++;
    }
}

function assert_eq($expected, $actual, string $message): void
{
    assert_true($expected === $actual, $message . ' (expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true) . ')');
}

$jsonPath = $root . '/data/nu-te-stekken.json';
$tz = stekjes_nu_te_stekken_timezone();

// --- JSON-structuur ---
$data = stekjes_nu_te_stekken_load($jsonPath);
assert_true(isset($data['maanden']) && is_array($data['maanden']), 'JSON heeft maanden');
assert_eq(STEKJES_NU_TE_STEKKEN_TZ, (string) ($data['meta']['timezone'] ?? ''), 'meta.timezone Europe/Amsterdam');

for ($m = 1; $m <= 12; $m++) {
    assert_true(isset($data['maanden'][(string) $m]), "maand {$m} aanwezig");
    $blok = stekjes_nu_te_stekken_voor_maand($m, $jsonPath);
    assert_eq($m, $blok['maand'], "voor_maand {$m} nummer");
    assert_eq(stekjes_nu_te_stekken_maandnaam($m), $blok['maand_naam'], "voor_maand {$m} naam");
    assert_true(count($blok['groepen']) >= 1, "maand {$m} heeft minstens één groep");
    foreach ($blok['groepen'] as $g) {
        assert_true(in_array($g['methode'], STEKJES_NU_TE_STEKKEN_METHODES, true), "methode geldig in maand {$m}");
        assert_true($g['tip'] !== '', "tip aanwezig in maand {$m}");
        assert_true(is_array($g['bron']) && ($g['bron']['url'] ?? '') !== '', "bron-url in maand {$m}");
        assert_true(count($g['planten']) >= 1, "planten in groep maand {$m}");
    }
}

// --- Maandselectie: vaste datums in Amsterdam ---
$jan = new DateTimeImmutable('2026-01-15 12:00:00', $tz);
$jun = new DateTimeImmutable('2026-06-15 12:00:00', $tz);
$nov = new DateTimeImmutable('2026-11-15 12:00:00', $tz);
assert_eq(1, stekjes_nu_te_stekken_maandnummer($jan), 'januari → 1');
assert_eq(6, stekjes_nu_te_stekken_maandnummer($jun), 'juni → 6');
assert_eq(11, stekjes_nu_te_stekken_maandnummer($nov), 'november → 11');

$nuJan = stekjes_nu_te_stekken_nu($jan, $jsonPath);
assert_eq(1, $nuJan['maand'], 'nu() januari');
assert_eq('januari', $nuJan['maand_naam'], 'nu() januari naam');
assert_true(count($nuJan['groepen']) >= 1, 'januari heeft groepen');

$nuJun = stekjes_nu_te_stekken_nu($jun, $jsonPath);
assert_eq(6, $nuJun['maand'], 'nu() juni');
assert_true($nuJan['groepen'] !== $nuJun['groepen'], 'januari ≠ juni inhoud');

// --- Jaargrens in Europe/Amsterdam ---
$oudjaar = new DateTimeImmutable('2025-12-31 23:30:00', $tz);
$nieuwjaar = new DateTimeImmutable('2026-01-01 00:30:00', $tz);
assert_eq(12, stekjes_nu_te_stekken_maandnummer($oudjaar), '31 dec 23:30 Amsterdam → 12');
assert_eq(1, stekjes_nu_te_stekken_maandnummer($nieuwjaar), '1 jan 00:30 Amsterdam → 1');

$decBlok = stekjes_nu_te_stekken_nu($oudjaar, $jsonPath);
$janBlok = stekjes_nu_te_stekken_nu($nieuwjaar, $jsonPath);
assert_eq(12, $decBlok['maand'], 'jaargrens: december-blok');
assert_eq(1, $janBlok['maand'], 'jaargrens: januari-blok');
assert_true($decBlok['groepen'] !== $janBlok['groepen'], 'jaargrens: andere groepen');

// --- Jaargrens via UTC die in Amsterdam over nieuwjaar valt ---
// 2025-12-31 23:30 UTC = 2026-01-01 00:30 Europe/Amsterdam
$utcNearBoundary = new DateTimeImmutable('2025-12-31 23:30:00', new DateTimeZone('UTC'));
assert_eq(1, stekjes_nu_te_stekken_maandnummer($utcNearBoundary), 'UTC 31 dec 23:30 → januari in Amsterdam');

// Net vóór middernacht Amsterdam, uitgedrukt in UTC:
// 2025-12-31 22:30 UTC = 2025-12-31 23:30 Europe/Amsterdam
$utcStillDec = new DateTimeImmutable('2025-12-31 22:30:00', new DateTimeZone('UTC'));
assert_eq(12, stekjes_nu_te_stekken_maandnummer($utcStillDec), 'UTC 31 dec 22:30 → december in Amsterdam');

// --- Stek-links naar bestaande pagina's ---
$novData = stekjes_nu_te_stekken_voor_maand(11, $jsonPath);
$foundZwarteBes = false;
foreach ($novData['groepen'] as $g) {
    foreach ($g['planten'] as $p) {
        if (($p['slug'] ?? null) === 'zwarte-bes') {
            $foundZwarteBes = true;
            assert_eq('/stektips/zwarte-bes/', $p['stek_url'], 'zwarte-bes heeft stek_url');
        }
        if (($p['slug'] ?? null) !== null) {
            assert_true($p['stek_url'] !== null, 'slug ' . $p['slug'] . ' heeft bestaande stekpagina');
        }
    }
}
assert_true($foundZwarteBes, 'november noemt zwarte bes');

// Plant zonder slug → geen stek_url
$apr = stekjes_nu_te_stekken_voor_maand(4, $jsonPath);
$zonderSlug = false;
foreach ($apr['groepen'] as $g) {
    foreach ($g['planten'] as $p) {
        if ($p['slug'] === null) {
            $zonderSlug = true;
            assert_eq(null, $p['stek_url'], 'geen slug → geen stek_url');
        }
    }
}
assert_true($zonderSlug, 'april heeft planten zonder eigen stekpagina');

echo "\n{$passed} geslaagd, {$failed} mislukt\n";
exit($failed > 0 ? 1 : 0);
