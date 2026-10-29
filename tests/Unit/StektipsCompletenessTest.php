<?php

declare(strict_types=1);

use App\Services\Stektips;

function stektipsService(): Stektips
{
    return new Stektips(dirname(__DIR__, 2).'/data/stektips.json');
}

test('elke stektip heeft gevulde tipvelden zonder Volgt binnenkort', function () {
    // Tipvelden op de soortpagina (ervaringen blijven optioneel / verborgen als leeg).
    $verplicht = [
        'waarom',
        'methode',
        'wanneer',
        'welk_hout',
        'lengte_snit',
        'water_grond',
        'hulpmiddel',
        'kans',
        'klaar',
    ];

    $soorten = stektipsService()->all();
    expect($soorten)->toHaveCount(48);

    foreach ($soorten as $soort) {
        $slug = (string) ($soort['slug'] ?? '');
        expect($slug)->not->toBeEmpty();

        foreach ($verplicht as $veld) {
            $waarde = $soort[$veld] ?? null;
            expect($waarde)->toBeString("{$slug}.{$veld} moet een string zijn");
            $trim = trim($waarde);
            expect($trim)->not->toBeEmpty("{$slug}.{$veld} mag niet leeg zijn");
            expect($trim)->not->toContain(
                'Volgt binnenkort',
                "{$slug}.{$veld} mag geen placeholder ‘Volgt binnenkort’ bevatten"
            );
        }
    }
});

test('elke stektip heeft minstens één bron-URL', function () {
    $soorten = stektipsService()->all();

    foreach ($soorten as $soort) {
        $slug = (string) ($soort['slug'] ?? '');
        $bronnen = trim((string) ($soort['bronnen'] ?? ''));

        expect($bronnen)->not->toBeEmpty("{$slug}.bronnen mag niet leeg zijn");
        expect($bronnen)->not->toContain(
            'Volgt binnenkort',
            "{$slug}.bronnen mag geen placeholder bevatten"
        );
        expect($bronnen)->toMatch(
            '#https?://\S+#',
            "{$slug}.bronnen moet minstens één http(s)-URL bevatten"
        );
    }
});

test('soorten-json en stektips-json blijven synchroon', function () {
    $fromService = collect(stektipsService()->all())->keyBy('slug');
    $dir = dirname(__DIR__, 2).'/soorten';
    $files = glob($dir.'/*.json') ?: [];

    expect($files)->toHaveCount(48);

    foreach ($files as $path) {
        $row = json_decode((string) file_get_contents($path), true);
        expect($row)->toBeArray();
        $slug = (string) ($row['slug'] ?? '');
        expect($fromService->has($slug))->toBeTrue("slug {$slug} ontbreekt in data/stektips.json");
        expect($fromService->get($slug))->toEqual($row);
    }
});
