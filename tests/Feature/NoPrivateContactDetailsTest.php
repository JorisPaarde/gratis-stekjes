<?php

declare(strict_types=1);

/**
 * Privéadres en telefoon van Joris mogen nergens in views/content/config staan
 * die op de site terechtkomt.
 */
test('views, content-JSON en config bevatten geen Bernadottelaan of privételefoon', function () {
    $roots = [
        resource_path('views'),
        base_path('data'),
        base_path('config'),
        base_path('soorten'),
    ];

    $extraFiles = [
        base_path('build-config.json'),
    ];

    $extensions = ['php', 'blade.php', 'json', 'md', 'txt', 'html', 'js', 'css'];
    $hits = [];

    // Bernadottelaan, of telefoon als 06 21… / 0621… / +31 6 21… / +31621…
    $phoneOrAddress = '/Bernadottelaan|06\s*21|\+31\s*6\s*21|\+31621|0621/iu';

    $scanFile = function (string $path) use (&$hits, $phoneOrAddress): void {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return;
        }
        if (preg_match($phoneOrAddress, $contents) === 1) {
            $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        }
    };

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

            $scanFile($file->getPathname());
        }
    }

    foreach ($extraFiles as $path) {
        if (is_readable($path)) {
            $scanFile($path);
        }
    }

    expect($hits)->toBeEmpty(
        'Nog privéadres/telefoon in: '.implode(', ', $hits)
    );
});

test('privacy en homepage tonen geen privéadres/telefoon maar wel KvK en btw', function () {
    foreach (['/', '/privacy'] as $path) {
        $response = $this->get($path)->assertOk();
        $html = $response->getContent();

        expect($html)->not->toContain('Bernadottelaan');
        expect($html)->not->toMatch('/06\s*21/');
        expect($html)->not->toMatch('/\+31\s*6\s*21/');
        expect($html)->not->toMatch('/\+31621/');
        expect($html)->not->toContain('0621228884');
        expect($html)->not->toContain('tel:+316');

        $response
            ->assertSee('90397320', false)
            ->assertSee('NL004813310B95', false);
    }
});
