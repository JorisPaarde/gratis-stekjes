<?php

declare(strict_types=1);

/**
 * Tests voor scripts/resolve-old-staging-app-dir.sh (lokale fixtures, geen SSH).
 */

beforeEach(function () {
    $this->script = dirname(__DIR__, 2).'/scripts/resolve-old-staging-app-dir.sh';
    expect(is_file($this->script))->toBeTrue();
    $this->tmp = sys_get_temp_dir().'/resolve-app-'.getmypid().'-'.bin2hex(random_bytes(3));
    mkdir($this->tmp, 0700, true);
});

afterEach(function () {
    if (! empty($this->tmp) && is_dir($this->tmp)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
    }
});

function makeLaravelApp(string $dir): void
{
    mkdir($dir.'/bootstrap', 0755, true);
    mkdir($dir.'/vendor', 0755, true);
    file_put_contents($dir.'/artisan', "#!/usr/bin/env php\n");
    file_put_contents($dir.'/bootstrap/app.php', "<?php\nreturn new stdClass;\n");
    file_put_contents($dir.'/vendor/autoload.php', "<?php\n");
}

function runResolve(string $script, string $docroot, ?string $override = null, ?string $home = null): array
{
    $errFile = '/tmp/resolve-stderr-'.getmypid().'.txt';
    $cmd = '';
    if ($home !== null) {
        $cmd .= 'HOME='.escapeshellarg($home).' ';
    }
    $cmd .= 'bash '.escapeshellarg($script).' --docroot '.escapeshellarg($docroot);
    if ($override !== null) {
        $cmd .= ' --override '.escapeshellarg($override);
    }
    $cmd .= ' 2>'.escapeshellarg($errFile);
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    $err = (string) @file_get_contents($errFile);
    @unlink($errFile);

    return [
        'code' => $code,
        'stdout' => implode("\n", $out),
        'stderr' => $err,
    ];
}

test('override: accepteert pad met artisan', function () {
    $doc = $this->tmp.'/docroot';
    $app = $this->tmp.'/old-app';
    mkdir($doc, 0755, true);
    makeLaravelApp($app);

    $r = runResolve($this->script, $doc, $app);
    expect($r['code'])->toBe(0)
        ->and($r['stdout'])->toBe(realpath($app) ?: $app)
        ->and($r['stderr'])->toContain('override');
});

test('override: weigert app-staging/app-prod', function () {
    $doc = $this->tmp.'/docroot';
    $app = $this->tmp.'/app-staging';
    mkdir($doc, 0755, true);
    makeLaravelApp($app);

    $r = runResolve($this->script, $doc, $app);
    expect($r['code'])->toBe(1)
        ->and($r['stderr'])->toContain('app-prod/app-staging');
});

test('override: faalt zonder artisan', function () {
    $doc = $this->tmp.'/docroot';
    $app = $this->tmp.'/no-artisan';
    mkdir($doc, 0755, true);
    mkdir($app, 0755, true);

    $r = runResolve($this->script, $doc, $app);
    expect($r['code'])->toBe(1)
        ->and($r['stderr'])->toContain('mist artisan');
});

test('autodetect via absolute require (hosting: gratisplanten-staging-app)', function () {
    // Exact layout: account jpwebcreation — absolute paths + usePublicPath(__DIR__).
    $doc = $this->tmp.'/staging.stekjes.jpwebcreation.nl';
    $app = $this->tmp.'/gratisplanten-staging-app';
    mkdir($doc, 0755, true);
    makeLaravelApp($app);
    $appReal = realpath($app) ?: $app;
    file_put_contents($doc.'/index.php', "<?php\n"
        ."define('LARAVEL_START', microtime(true));\n"
        ."require '{$appReal}/vendor/autoload.php';\n"
        ."\$app = require_once '{$appReal}/bootstrap/app.php';\n"
        ."\$app->usePublicPath(__DIR__);\n");

    // HOME elders zodat sibling-fallback niet “helpt” — index.php moet winnen.
    $r = runResolve($this->script, $doc, null, $this->tmp.'/empty-home');
    mkdir($this->tmp.'/empty-home', 0700, true);

    $r = runResolve($this->script, $doc, null, $this->tmp.'/empty-home');
    expect($r['code'])->toBe(0)
        ->and($r['stdout'])->toBe($appReal)
        ->and($r['stderr'])->toContain('index.php');
});

test('autodetect via __DIR__ . /../app relatief require', function () {
    $home = $this->tmp;
    $doc = $home.'/public_html';
    $app = $home.'/gratisplanten-staging-app';
    mkdir($doc, 0755, true);
    makeLaravelApp($app);
    // Zoals cpanel-deploy: __DIR__ . '/../app/vendor/...' → string begint met /../
    file_put_contents($doc.'/index.php', "<?php\n"
        ."require __DIR__ . '/../gratisplanten-staging-app/vendor/autoload.php';\n"
        ."\$app = require_once __DIR__ . '/../gratisplanten-staging-app/bootstrap/app.php';\n"
        ."\$app->usePublicPath(__DIR__);\n");

    $r = runResolve($this->script, $doc, null, $home.'/isolated-home');
    mkdir($home.'/isolated-home', 0700, true);
    $r = runResolve($this->script, $doc, null, $home.'/isolated-home');

    expect($r['code'])->toBe(0)
        ->and($r['stdout'])->toBe(realpath($app) ?: $app)
        ->and($r['stderr'])->toContain('index.php');
});

test('autodetect via unieke sibling, negeert app-staging', function () {
    $home = $this->tmp;
    $doc = $home.'/site';
    $old = $home.'/legacy-laravel';
    $new = $home.'/app-staging';
    mkdir($doc, 0755, true);
    file_put_contents($doc.'/index.php', "<?php\necho 'no require';\n");
    makeLaravelApp($old);
    makeLaravelApp($new);

    $r = runResolve($this->script, $doc, null, $home);
    expect($r['code'])->toBe(0)
        ->and($r['stdout'])->toBe(realpath($old) ?: $old)
        ->and($r['stderr'])->toContain('unieke kandidaat');
});

test('autodetect faalt bij meerdere kandidaten met lijst', function () {
    $home = $this->tmp;
    $doc = $home.'/www';
    mkdir($doc, 0755, true);
    file_put_contents($doc.'/index.php', "<?php\n");
    makeLaravelApp($home.'/app-a');
    makeLaravelApp($home.'/app-b');

    $r = runResolve($this->script, $doc, null, $home);
    expect($r['code'])->toBe(1)
        ->and($r['stderr'])->toContain('Meerdere kandidaten')
        ->and($r['stderr'])->toContain('app-a')
        ->and($r['stderr'])->toContain('app-b');
});

test('migrate-workflow heeft old_app_dir input en resolve-script', function () {
    $yml = file_get_contents(dirname(__DIR__, 2).'/.github/workflows/migrate-stekjes-to-prod.yml');
    expect($yml)->toContain('old_app_dir:')
        ->and($yml)->toContain('resolve-old-staging-app-dir.sh')
        ->and($yml)->toContain('Oude staging APP_DIR=')
        ->and($yml)->not->toContain('stekjes-staging-app');
});
