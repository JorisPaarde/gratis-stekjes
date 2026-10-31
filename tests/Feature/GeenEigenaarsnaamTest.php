<?php

/**
 * Naam van de eigenaar en zijn bedrijf staan alleen waar het wettelijk moet (privacy).
 */
test('geen eigenaarsnaam of bedrijfsnaam buiten de privacypagina', function () {
    $base = dirname(__DIR__, 2);
    $dirs = ['app', 'resources/views', 'resources/js', 'data', 'lang', 'config', 'routes'];
    $allowed = ['resources/views/pages/privacy.blade.php'];
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
    $plek = new \App\Models\Deelplek(['naam' => 'Testplek']);
    $mail = (new \App\Notifications\DeelplekAanmeldingBevestiging($plek))->toMail((object) []);
    expect((string) $mail->salutation)->not->toContain('Joris');
    expect((string) $mail->salutation)->toContain((string) config('site.name'));
});
