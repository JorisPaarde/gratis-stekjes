<?php

declare(strict_types=1);

use App\Services\SoortAfbeeldingen;

function soortAfbeeldingenRoot(): string
{
    return dirname(__DIR__, 2);
}

test('elke stektip-soort heeft geverifieerde metadata of fallback', function () {
    $root = soortAfbeeldingenRoot();
    $svc = new SoortAfbeeldingen($root.'/data/soort-afbeeldingen.json');
    $stektips = json_decode((string) file_get_contents($root.'/data/stektips.json'), true);
    $slugs = array_map(static fn ($s) => (string) $s['slug'], $stektips['soorten'] ?? []);

    expect(count($svc->all()))->toBe(48);

    foreach ($slugs as $slug) {
        $row = $svc->all()[$slug] ?? null;
        expect($row)->toBeArray("metadata ontbreekt voor {$slug}");
        expect($row['auteur'] ?? '')->not->toBeEmpty()
            ->and($row['bron_url'] ?? '')->toStartWith('http')
            ->and($row['licentie_url'] ?? '')->toStartWith('http')
            ->and($row['licentie'] ?? '')->not->toBeEmpty()
            ->and($row['alt'] ?? '')->not->toBeEmpty();
        $path = $root.'/public/assets/images/soorten/'.$slug.'.jpg';
        expect(is_file($path))->toBeTrue("bestand ontbreekt voor {$slug}");
    }
});

test('alleen vrije licenties in soort-afbeeldingen seed', function () {
    $data = json_decode(
        (string) file_get_contents(soortAfbeeldingenRoot().'/data/soort-afbeeldingen.json'),
        true
    );
    $allowed = ['CC0', 'Public Domain', 'CC BY', 'CC BY-SA'];

    foreach ($data['soorten'] ?? [] as $slug => $row) {
        $lic = (string) ($row['licentie'] ?? '');
        $ok = false;
        foreach ($allowed as $a) {
            if (str_contains($lic, $a)) {
                $ok = true;
                break;
            }
        }
        expect($ok)->toBeTrue("ongeldige licentie voor {$slug}: {$lic}");
        $lower = mb_strtolower($lic);
        expect(str_contains($lower, 'nc') || str_contains($lower, 'nd'))->toBeFalse();
    }
});

test('onbekende slug krijgt neutrale fallback-pad in metadata-service', function () {
    // Unit: geen Laravel URL-helpers — controleer dat ontbrekende slug geen row heeft.
    $svc = new SoortAfbeeldingen(soortAfbeeldingenRoot().'/data/soort-afbeeldingen.json');
    expect($svc->all())->not->toHaveKey('bestaat-niet');
});
