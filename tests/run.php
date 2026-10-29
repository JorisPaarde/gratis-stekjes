<?php
/**
 * PHP-tests voor de invasieve-soortenmodule (geen externe dependencies).
 * Exit code 0 = alles groen, 1 = mislukt.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/php/lib/invasive.php';

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

$jsonPath = dirname(__DIR__) . '/data/niet-aanbieden.json';

// --- JSON ---
$data = invasive_load_species($jsonPath);
assert_true(isset($data['soorten']) && is_array($data['soorten']), 'JSON heeft soorten-array');
assert_true(count($data['soorten']) >= 40, 'Minstens 40 geverifieerde plantensoorten');
assert_eq(47, count($data['soorten']), 'Precies 47 NVWA terrestrische + waterplanten');

foreach ($data['soorten'] as $i => $soort) {
    assert_true(
        !empty($soort['wetenschappelijke_naam'])
        && !empty($soort['nederlandse_naam'])
        && !empty($soort['bron_url'])
        && !empty($soort['geraadpleegd']),
        "Soort #{$i} heeft verplichte velden"
    );
}

// --- Normalisatie ---
assert_eq('reuzenberenklauw', invasive_normalize('  Reuzenberenklauw  '), 'normaliseer spaties/hoofdletters');
assert_eq('reynoutria x bohemica', invasive_normalize('Reynoutria × bohemica'), 'normaliseer × naar x');
assert_eq('sosnowskys berenklauw', invasive_normalize("Sosnowsky's berenklauw"), 'normaliseer apostrof');

// --- Matching: NL-naam ---
$r = invasive_check_text('Gratis stekje van reuzenberenklauw uit de tuin', $jsonPath);
assert_eq(1, count($r['matches']), 'vindt Reuzenberenklauw');
assert_eq('Reuzenberenklauw', $r['matches'][0]['nederlandse_naam'], 'NL-naam Reuzenberenklauw');
assert_true(is_string($r['warning']) && $r['warning'] !== '', 'waarschuwingstekst aanwezig');
assert_true(str_contains($r['warning'], 'nvwa.nl'), 'waarschuwing bevat bronlink');

// --- Matching: wetenschappelijke naam, hoofdletterongevoelig ---
$r = invasive_check_text('IMPATIENS GLANDULIFERA stekjes', $jsonPath);
assert_eq(1, count($r['matches']), 'vindt Impatiens glandulifera');
assert_eq('Reuzenbalsemien', $r['matches'][0]['nederlandse_naam'], 'sci → NL Reuzenbalsemien');

// --- Matching: synoniem ---
$r = invasive_check_text('Fallopia japonica wortels', $jsonPath);
assert_eq(1, count($r['matches']), 'vindt Fallopia japonica synoniem');
assert_eq('Japanse duizendknoop', $r['matches'][0]['nederlandse_naam'], 'synoniem → Japanse duizendknoop');

$r = invasive_check_text('Eichhornia crassipes waterplant', $jsonPath);
assert_eq(1, count($r['matches']), 'vindt Eichhornia crassipes synoniem');
assert_eq('Waterhyacint', $r['matches'][0]['nederlandse_naam'], 'synoniem → Waterhyacint');

// --- Geen false positive op onschuldige tekst ---
$r = invasive_check_text('Monstera deliciosa en Pothos stekjes te geef', $jsonPath);
assert_eq(0, count($r['matches']), 'geen match op gangbare kamerplanten');
assert_eq(null, $r['warning'], 'geen waarschuwing zonder match');

// --- Meerdere soorten in één tekst ---
$r = invasive_check_text('Waterwaaier (Cabomba caroliniana) en hemelboom', $jsonPath);
assert_eq(2, count($r['matches']), 'vindt twee soorten');
assert_true(is_string($r['warning']) && str_contains($r['warning'], 'Waterwaaier'), 'waarschuwing noemt Waterwaaier');
assert_true(str_contains((string) $r['warning'], 'Hemelboom'), 'waarschuwing noemt Hemelboom');

// --- Geen blokkade: functie retourneert alleen data ---
$r = invasive_check_text('zijdeplant Asclepias syriaca', $jsonPath);
assert_true(isset($r['matches'], $r['warning']), 'retourstructuur heeft matches + warning');
assert_true(!isset($r['blocked']) && !isset($r['block']), 'geen blokkade-veld');

echo "\n{$passed} geslaagd, {$failed} mislukt\n";
exit($failed > 0 ? 1 : 0);
