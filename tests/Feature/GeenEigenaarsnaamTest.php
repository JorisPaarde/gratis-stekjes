<?php

use App\Models\Deelplek;
use App\Notifications\DeelplekAanmeldingBevestiging;

/**
 * Naam van de eigenaar en zijn bedrijf staan alleen waar het wettelijk moet (privacy).
 * Uitzondering (keuze eigenaar, nov 2026): alleen de voornaam staat in config/site.php («maker»)
 * en wordt getoond op /over («Wie zit erachter») en in het korte blok op /help-mee. Ook de naam van
 * zijn vader staat daar (config «maker_vader»). Bedrijfsnaam, adres en telefoon staan nergens in de
 * lopende tekst.
 */
test('geen eigenaarsnaam of bedrijfsnaam buiten de privacypagina', function () {
    $base = dirname(__DIR__, 2);
    $dirs = ['app', 'resources/views', 'resources/js', 'data', 'lang', 'config', 'routes'];
    $allowed = ['resources/views/pages/privacy.blade.php', 'config/site.php'];
    $hits = [];
    foreach ($dirs as $dir) {
        $path = $base.'/'.$dir;
        if (! is_dir($path)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace($base, '', $file->getPathname()), '/');
            if (in_array($rel, $allowed, true)) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match('/\bJoris\b|JP ?Web ?Creation|jpwebcreation|Paardekooper/i', $contents)) {
                $hits[] = $rel;
            }
        }
    }
    expect($hits)->toBeEmpty('Eigenaarsnaam in: '.implode(', ', $hits));
});

test('mails ondertekenen zonder eigenaarsnaam', function () {
    $plek = new Deelplek(['naam' => 'Testplek']);
    $mail = (new DeelplekAanmeldingBevestiging($plek))->toMail((object) []);
    expect((string) $mail->salutation)->not->toContain('Joris');
    expect((string) $mail->salutation)->toContain((string) config('site.name'));
});

test('over-pagina: voornaam en foto, maar geen bedrijf, adres, telefoon of volkstuinnaam', function () {
    $html = $this->get(route('over'))->assertOk()->getContent();
    expect($html)->toContain('Hoi, ik ben '.config('site.maker'))
        ->and($html)->toContain('/assets/images/maker/maker-240.jpg')
        ->and($html)->toContain('MensenrechtenMens')
        ->and($html)->toContain('mensenrechten.nl/actueel/nieuws/2018')
        ->and($html)->not->toMatch('/jpwebcreation|JP ?Web ?Creation|Bernadottelaan|06[ -]?21|Ons Buiten/i');
});
