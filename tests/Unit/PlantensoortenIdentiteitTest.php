<?php

declare(strict_types=1);

use App\Services\Plantensoorten;

/**
 * Bestaande catalogus-ids mogen geen andere Latijnse soort krijgen (leden-aanbod
 * hangt aan id). Pure toevoegingen zijn ok; vergelijk met master 0.68.2 snapshot.
 */
test('bestaande plantensoorten-ids houden latijn van master 0.68.2', function () {
    $masterPath = dirname(__DIR__).'/fixtures/plantensoorten-master-0.68.2-latijn.json';
    expect(is_file($masterPath))->toBeTrue();

    $masterLatijn = json_decode((string) file_get_contents($masterPath), true, 512, JSON_THROW_ON_ERROR);
    expect($masterLatijn)->toBeArray()->not->toBeEmpty();

    $planten = new Plantensoorten(dirname(__DIR__, 2).'/data/plantensoorten.json');
    $byId = [];
    foreach ($planten->all(includeHidden: true) as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $afwijkingen = [];
    foreach ($masterLatijn as $id => $latijn) {
        $id = (int) $id;
        if (! isset($byId[$id])) {
            $afwijkingen[] = "id {$id} ontbreekt";
            continue;
        }
        $huidig = (string) ($byId[$id]['latijn'] ?? '');
        if ($huidig !== (string) $latijn) {
            $afwijkingen[] = "id {$id} ({$byId[$id]['naam']}): '{$huidig}' ≠ master '{$latijn}'";
        }
    }

    expect($afwijkingen)->toBeEmpty(implode('; ', $afwijkingen));
});

test('herstelde catalogusnamen en plantzoeker-aliassen blijven vindbaar', function () {
    $planten = new Plantensoorten(dirname(__DIR__, 2).'/data/plantensoorten.json');

    expect($planten->resolveExact('Patagonisch ijzerhard')['latijn'] ?? null)->toBe('Verbena bonariensis')
        ->and($planten->resolveExact('Patagonisch ijzerhard')['id'] ?? null)->toBe(180)
        ->and($planten->resolveExact('Echt ijzerhard')['latijn'] ?? null)->toBe('Verbena officinalis')
        ->and($planten->resolveExact('Echt ijzerhard')['slug'] ?? null)->toBe('echt-ijzerhard')
        ->and($planten->resolveExact('Grote margriet')['latijn'] ?? null)->toBe('Leucanthemum × superbum')
        ->and($planten->resolveExact('Grote margriet')['id'] ?? null)->toBe(187)
        ->and($planten->resolveExact('Gewone margriet')['slug'] ?? null)->toBe('gewone-margriet')
        ->and($planten->resolveExact('Kattestaart')['latijn'] ?? null)->toBe('Lythrum salicaria')
        ->and($planten->resolveExact('Kattestaart')['id'] ?? null)->toBe(208)
        ->and($planten->resolveExact('Klokjesbloem')['latijn'] ?? null)->toBe('Campanula')
        ->and($planten->resolveExact('Longkruid')['latijn'] ?? null)->toBe('Pulmonaria')
        ->and($planten->resolveExact('Sleutelbloem')['latijn'] ?? null)->toBe('Primula')
        ->and($planten->resolveExact('Artisjok')['latijn'] ?? null)->toBe('Cynara cardunculus');

    foreach (['ijzerhard', 'verbena', 'margriet', 'kattenstaart', 'klokjesbloem', 'longkruid', 'sleutelbloem'] as $q) {
        expect($planten->search($q, 10))->not->toBeEmpty("zoek '{$q}' gaf niets");
    }
});
