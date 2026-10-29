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

            if (str_contains($contents, 'Volgt binnenkort')) {
                $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }

    expect($hits)->toBeEmpty(
        'Nog “Volgt binnenkort” in: '.implode(', ', $hits)
    );
});

test('privacy, over en initiatieven tonen geen Volgt binnenkort en geen we/wij/ons/onze', function () {
    $pages = [
        'privacy' => $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Concept – nog niet definitief', false)
            ->assertSee('info@jpwebcreation.nl', false)
            ->assertSee('[in te vullen door Joris]', false)
            ->assertDontSee('Volgt binnenkort', false),
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
