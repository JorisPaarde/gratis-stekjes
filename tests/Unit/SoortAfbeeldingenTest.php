<?php

declare(strict_types=1);

use App\Services\SoortAfbeeldingen;

function soortAfbeeldingenRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array{soorten: array<string, array<string, mixed>>, missing?: list<string>}
 */
function soortAfbeeldingenMeta(): array
{
    $data = json_decode(
        (string) file_get_contents(soortAfbeeldingenRoot().'/data/soort-afbeeldingen.json'),
        true
    );
    expect(is_array($data))->toBeTrue();

    return $data;
}

test('elke stektip-soort heeft geverifieerde metadata of fallback', function () {
    $root = soortAfbeeldingenRoot();
    $svc = new SoortAfbeeldingen($root.'/data/soort-afbeeldingen.json');
    $stektips = json_decode((string) file_get_contents($root.'/data/stektips.json'), true);
    $slugs = array_map(static fn ($s) => (string) $s['slug'], $stektips['soorten'] ?? []);
    $meta = soortAfbeeldingenMeta();
    $missing = $meta['missing'] ?? [];

    expect(count($svc->all()))->toBe(146)
        ->and(count($slugs))->toBe(146)
        ->and(count($missing))->toBe(0);

    foreach ($slugs as $slug) {
        $row = $svc->all()[$slug] ?? null;
        if ($row === null) {
            expect($missing)->toContain($slug);

            continue;
        }
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
    $data = soortAfbeeldingenMeta();
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

test('foto met status te_bekijken wordt niet getoond', function () {
    $path = tempnam(sys_get_temp_dir(), 'sa');
    file_put_contents($path, json_encode(['soorten' => [
        'goed' => ['slug' => 'goed', 'status' => 'goedgekeurd', 'bestand' => 'x.jpg'],
        'nieuw' => ['slug' => 'nieuw', 'status' => 'te_bekijken', 'bestand' => 'y.jpg'],
        'oud' => ['slug' => 'oud', 'bestand' => 'z.jpg'],
    ]]));
    $all = (new SoortAfbeeldingen($path))->all();
    @unlink($path);

    expect($all)->toHaveKeys(['goed', 'oud'])
        ->and($all)->not->toHaveKey('nieuw');
});

test('mapping-key slug en bestandsnaam horen bij elkaar; bestand bestaat', function () {
    $root = soortAfbeeldingenRoot();
    $data = soortAfbeeldingenMeta();

    foreach ($data['soorten'] ?? [] as $key => $row) {
        expect(is_array($row))->toBeTrue();
        $slug = (string) ($row['slug'] ?? '');
        $bestand = (string) ($row['bestand'] ?? '');
        expect($slug)->toBe($key, "JSON-key «{$key}» wijkt af van slug «{$slug}»")
            ->and($bestand)->toBe("assets/images/soorten/{$slug}.jpg", "bestand voor {$slug} wijst niet naar eigen slug-pad");

        $path = $root.'/public/'.$bestand;
        expect(is_file($path))->toBeTrue("ontbrekend bestand voor {$slug}: {$bestand}");
    }
});

test('geen dubbele soortfoto-bestanden (zelfde sha256) tussen soorten', function () {
    $root = soortAfbeeldingenRoot();
    $data = soortAfbeeldingenMeta();
    $byHash = [];

    foreach ($data['soorten'] ?? [] as $slug => $row) {
        $path = $root.'/public/'.(string) ($row['bestand'] ?? '');
        expect(is_file($path))->toBeTrue("ontbrekend bestand voor {$slug}");
        $hash = hash_file('sha256', $path);
        expect($hash)->toBeString()->not->toBeEmpty();
        if (isset($byHash[$hash])) {
            expect(false)->toBeTrue(
                "dubbele foto: {$byHash[$hash]} en {$slug} delen sha256 {$hash}"
            );
        }
        $byHash[$hash] = $slug;
    }
});

test('sha256-allowlist komt overeen met de JPEG op schijf', function () {
    $root = soortAfbeeldingenRoot();
    $data = soortAfbeeldingenMeta();

    foreach ($data['soorten'] ?? [] as $slug => $row) {
        $expected = strtolower((string) ($row['sha256'] ?? ''));
        expect($expected)->toMatch(
            '/^[a-f0-9]{64}$/',
            "ontbrekende of ongeldige sha256-allowlist voor {$slug}"
        );
        $path = $root.'/public/'.(string) ($row['bestand'] ?? '');
        expect(is_file($path))->toBeTrue("ontbrekend bestand voor {$slug}");
        $actual = hash_file('sha256', $path);
        expect($actual)->toBe(
            $expected,
            "stille vervanging van soortfoto {$slug}: allowlist {$expected}, schijf {$actual}"
        );
        $bytes = (int) ($row['bytes'] ?? 0);
        if ($bytes > 0) {
            expect(filesize($path))->toBe($bytes, "bytes-veld klopt niet voor {$slug}");
        }
    }
});

test('bronmetadata past bij de soort (genus of bekende uitzondering)', function () {
    $data = soortAfbeeldingenMeta();
    // Botanische synoniemen / verzamelnaam waar de Commons-titel de actuele genus niet noemt.
    $exceptions = [
        'kaaps-viooltje' => ['saintpaulia'], // Streptocarpus ionanthus / Saintpaulia
        'rozemarijn' => ['rosmarinus'], // Salvia rosmarinus / Rosmarinus officinalis
        'siergras' => ['miscanthus', 'calamagrostis', 'pennisetum'],
        'kers' => ['prunus', 'kirsch', 'cherry'],
    ];

    foreach ($data['soorten'] ?? [] as $slug => $row) {
        $latijn = trim((string) ($row['latijn'] ?? ''));
        $blob = mb_strtolower(implode(' ', [
            (string) ($row['bron_titel'] ?? ''),
            (string) ($row['notitie'] ?? ''),
            (string) ($row['bron_url'] ?? ''),
            (string) ($row['alt'] ?? ''),
        ]), 'UTF-8');

        // Nooit opnieuw een schelp/cassis-cornuta voor een plantensoort.
        expect(preg_match('/cassis\s*cornuta|giant\s*helmet|\bseashell\b|\bzeeschelp\b/u', $blob))
            ->toBe(0, "niet-plantbron voor {$slug}: {$blob}");

        if ($latijn === '' || str_starts_with(mb_strtolower($latijn, 'UTF-8'), 'diverse')) {
            continue;
        }
        $genus = mb_strtolower((string) preg_replace('/[^a-zA-Z\-].*/', '', $latijn), 'UTF-8');
        if ($genus === '' || strlen($genus) < 3) {
            continue;
        }

        $ok = str_contains($blob, $genus);
        foreach ($exceptions[$slug] ?? [] as $alias) {
            if (str_contains($blob, $alias)) {
                $ok = true;
                break;
            }
        }
        expect($ok)->toBeTrue(
            "bron voor {$slug} noemt genus «{$genus}» niet (voorkomt o.a. Cassis i.p.v. Ribes): {$blob}"
        );
    }
});

test('zwarte-bes wijst naar Ribes nigrum-foto, niet naar Cassis-schelp', function () {
    $data = soortAfbeeldingenMeta();
    $row = $data['soorten']['zwarte-bes'] ?? null;
    expect($row)->toBeArray()
        ->and($row['latijn'] ?? '')->toBe('Ribes nigrum')
        ->and($row['bestand'] ?? '')->toBe('assets/images/soorten/zwarte-bes.jpg')
        ->and((string) ($row['bron_url'] ?? ''))->toContain('Ribes_nigrum')
        ->and((string) ($row['bron_titel'] ?? ''))->toContain('Ribes nigrum')
        ->and((string) ($row['bron_url'] ?? ''))->not->toContain('Cassis')
        ->and((string) ($row['notitie'] ?? ''))->not->toContain('Giant Helmet')
        ->and((string) ($row['sha256'] ?? ''))->toBe(
            '7e3dfe151039b1c6b07db8d6b89414f4b183689eea976d4c2967deaa438828d2'
        );
});
