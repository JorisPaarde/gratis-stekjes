<?php

declare(strict_types=1);

/**
 * Placeholder “Volgt binnenkort” mag niet meer staan in content-views/data/soorten
 * (inclusief stektips-data).
 */
test('Volgt binnenkort komt niet voor in views, data of soorten', function () {
    $roots = [
        resource_path('views'),
        base_path('data'),
        base_path('soorten'),
    ];

    $extensions = ['php', 'blade.php', 'json', 'md', 'txt', 'html', 'js', 'css'];
    $hits = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $name = $file->getFilename();
            $okExt = false;
            foreach ($extensions as $ext) {
                if (str_ends_with($name, '.'.$ext) || $name === $ext) {
                    $okExt = true;
                    break;
                }
            }
            if (! $okExt) {
                continue;
            }

            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            if (str_contains($contents, 'Volgt binnenkort')) {
                $hits[] = $rel.' (Volgt binnenkort)';
            }
            if (str_contains($contents, '[in te vullen')) {
                $hits[] = $rel.' ([in te vullen)';
            }
        }
    }

    expect($hits)->toBeEmpty(
        'Nog placeholders in: '.implode(', ', $hits)
    );
});

test('privacy, over en initiatieven tonen geen Volgt binnenkort en geen we/wij/ons/onze', function () {
    $pages = [
        'privacy' => $this->get(route('privacy'))
            ->assertOk()
            ->assertDontSee('Concept – nog niet definitief', false)
            ->assertSee('JP Web Creation', false)
            ->assertSee('https://jpwebcreation.nl', false)
            ->assertSee('90397320', false)
            ->assertSee('NL004813310B95', false)
            ->assertSee('info@gratisplanten.nl', false)
            ->assertDontSee('Bernadottelaan', false)
            ->assertDontSee('06 21 22 88 84', false)
            ->assertDontSee('tel:+31621228884', false)
            ->assertDontSee('info@jpwebcreation.nl', false)
            ->assertDontSee('[in te vullen', false)
            ->assertDontSee('Volgt binnenkort', false),
        'contact' => $this->get(route('contact'))
            ->assertOk()
            ->assertSee('info@gratisplanten.nl', false)
            ->assertDontSee('info@jpwebcreation.nl', false),
        'over' => $this->get(route('over'))
            ->assertOk()
            ->assertSee('Joris Paardekooper', false)
            ->assertSee('zonder reclame', false)
            ->assertDontSee('Volgt binnenkort', false),
        'initiatieven' => $this->get(route('partners'))
            ->assertOk()
            ->assertSee('Stekjesroute', false)
            ->assertSee('plantenruilkasten', false)
            ->assertSee('Zadenbibliotheek Nederland', false)
            ->assertDontSee('Volgt binnenkort', false),
    ];

    foreach ($pages as $label => $response) {
        $text = html_entity_decode(strip_tags($response->getContent()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        expect(preg_match('/\b(we|wij|ons|onze)\b/iu', $text))->toBe(
            0,
            "Pagina {$label} bevat nog we/wij/ons/onze in de tekstinhoud."
        );
    }
});

test('views bevatten geen info@jpwebcreation.nl als sitecontact', function () {
    $hits = [];
    $root = resource_path('views');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $contents = @file_get_contents($file->getPathname());
        if ($contents !== false && str_contains($contents, 'info@jpwebcreation.nl')) {
            $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($hits)->toBeEmpty(
        'Nog info@jpwebcreation.nl in views: '.implode(', ', $hits)
    );
});

test('kennisbank-artikelen en soort-JSON bevatten geen we/wij/ons/onze', function () {
    $files = array_merge(
        glob(base_path('data/kennisbank/*.json')) ?: [],
        glob(base_path('soorten/*.json')) ?: [],
        [base_path('data/stektips.json')]
    );

    $skip = ['categories.json', 'soort-hubs.json'];
    $hits = [];

    foreach ($files as $path) {
        $name = basename((string) $path);
        if (in_array($name, $skip, true)) {
            continue;
        }
        if (! is_readable($path)) {
            continue;
        }
        $contents = (string) file_get_contents($path);
        if (preg_match('/\b(we|wij|ons|onze)\b/iu', $contents) === 1) {
            $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', (string) $path);
        }
    }

    expect($hits)->toBeEmpty(
        'Nog we/wij/ons/onze in artikel-/soort-JSON: '.implode(', ', $hits)
    );
});

/**
 * Zichtbare sitetekst in Blade-views: geen “We hebben …” / “wij”.
 * Alleen zichtbare tekst (Blade/HTML eruit) om false positives in
 * attributen, directives en scripts te vermijden.
 */
test('views bevatten geen We hebben of wij in zichtbare tekst', function () {
    $root = resource_path('views');
    $hits = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if (! str_ends_with($name, '.blade.php') && ! str_ends_with($name, '.php')) {
            continue;
        }

        $contents = @file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        // Blade- en HTML-commentaren weg.
        $text = preg_replace('/\{\{--.*?--\}\}/s', ' ', $contents) ?? $contents;
        $text = preg_replace('/<!--.*?-->/s', ' ', $text) ?? $text;
        // Scripts/styles (niet zichtbaar) weg.
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $text) ?? $text;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
        // Blade-echo’s en -directives: geen zichtbare proza.
        $text = preg_replace('/\{\{\{.*?\}\}\}/s', ' ', $text) ?? $text;
        $text = preg_replace('/\{\{.*?\}\}/s', ' ', $text) ?? $text;
        $text = preg_replace('/\{!!.*?!!\}/s', ' ', $text) ?? $text;
        $text = preg_replace('/@[a-zA-Z]+(\s*\([^;]*\))?;?/s', ' ', $text) ?? $text;
        // HTML-tags weg; houd tekstinhoud.
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (preg_match('/\bWe hebben\b/u', $text) === 1) {
            $hits[] = $rel.' (We hebben)';
        }
        if (preg_match('/\bwij\b/iu', $text) === 1) {
            $hits[] = $rel.' (wij)';
        }
    }

    expect($hits)->toBeEmpty(
        'Nog We hebben / wij in zichtbare view-tekst: '.implode(', ', $hits)
    );
});

test('bevestiging-nodig is ik-vorm', function () {
    $this->get(route('account.bevestiging-nodig'))
        ->assertOk()
        ->assertSee('Ik heb je een bevestigingslink gestuurd', false)
        ->assertDontSee('We hebben je', false);
});
