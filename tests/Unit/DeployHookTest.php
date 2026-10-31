<?php

declare(strict_types=1);

/**
 * Tests voor de standalone deploy-hook (geen Laravel-boot van de hook zelf).
 */

beforeEach(function () {
    $this->hookLib = dirname(__DIR__, 2).'/deploy/hook/lib.php';
    expect(is_file($this->hookLib))->toBeTrue();
    require_once $this->hookLib;

    $this->tmp = sys_get_temp_dir().'/deploy-hook-test-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($this->tmp.'/docroot', 0755, true);
    mkdir($this->tmp.'/.deploy', 0700, true);
    mkdir($this->tmp.'/app-staging', 0755, true);

    putenv('DEPLOY_HOOK_ALLOW_HTTP=1');
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_AUTHORIZATION'] = '';
});

afterEach(function () {
    putenv('DEPLOY_HOOK_ALLOW_HTTP');
    if (! empty($this->tmp) && is_dir($this->tmp)) {
        // Geen FOLLOW_SYMLINKS: storage-symlink niet inloopingen.
        deploy_hook_rm_tree($this->tmp);
    }
});

function deployHookWriteConfig(string $home, string $docroot, string $appDir, string $token): string
{
    $hash = password_hash($token, PASSWORD_DEFAULT);
    $cfg = "<?php\nreturn [\n"
        ."  'token_hash' => ".var_export($hash, true).",\n"
        ."  'app_dir' => ".var_export($appDir, true).",\n"
        ."  'docroot' => ".var_export($docroot, true).",\n"
        ."  'env' => 'staging',\n"
        ."];\n";
    $path = $home.'/.deploy/staging.php';
    file_put_contents($path, $cfg);
    chmod($path, 0600);

    return $hash;
}

function deployHookMakeZip(string $zipPath, array $files): void
{
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
}

test('deploy-hook.php en lib.php zijn syntax-OK', function () {
    $root = dirname(__DIR__, 2);
    exec('php -l '.escapeshellarg($root.'/deploy/hook/deploy-hook.php'), $out1, $c1);
    exec('php -l '.escapeshellarg($root.'/deploy/hook/lib.php'), $out2, $c2);
    expect($c1)->toBe(0);
    expect($c2)->toBe(0);
});

test('preserve_env_key behoudt BACKUP_ENCRYPTION_KEY bij lege upload', function () {
    $path = $this->tmp.'/existing.env';
    file_put_contents($path, "BACKUP_ENCRYPTION_KEY=\"server-only-key\"\nAPP_KEY=\"base64:x\"\n");
    $merged = deploy_hook_preserve_env_key("APP_KEY=\"base64:x\"\nBACKUP_ENCRYPTION_KEY=\n", $path, 'BACKUP_ENCRYPTION_KEY');
    expect($merged)->toContain('BACKUP_ENCRYPTION_KEY="server-only-key"');

    $fromUpload = deploy_hook_preserve_env_key("BACKUP_ENCRYPTION_KEY=\"new-from-ci\"\n", $path, 'BACKUP_ENCRYPTION_KEY');
    expect($fromUpload)->toContain('BACKUP_ENCRYPTION_KEY="new-from-ci"')
        ->and($fromUpload)->not->toContain('server-only-key');
});

test('resolve_app_key behoudt bestaande server-key ook bij andere upload', function () {
    $path = $this->tmp.'/existing-appkey.env';
    file_put_contents($path, "APP_KEY=\"base64:serverkeyserverkeyserverkeys\"\n");

    $merged = deploy_hook_resolve_app_key(
        "APP_NAME=test\nAPP_KEY=\"base64:uploadkeyuploadkeyuploadkeyu\"\n",
        $path
    );

    expect($merged)->toContain('APP_KEY="base64:serverkeyserverkeyserverkeys"')
        ->and($merged)->not->toContain('uploadkeyuploadkeyuploadkeyu');
});

test('resolve_app_key gebruikt upload alleen zonder bestaande key', function () {
    $path = $this->tmp.'/missing-appkey.env';
    file_put_contents($path, "APP_NAME=test\n");

    $merged = deploy_hook_resolve_app_key(
        "APP_KEY=\"base64:uploadkeyuploadkeyuploadkeyu\"\n",
        $path
    );

    expect($merged)->toContain('APP_KEY="base64:uploadkeyuploadkeyuploadkeyu"');
});

test('backup_env schrijft unieke timestamped .env.backup mode 0600 en pruneert', function () {
    $path = $this->tmp.'/shared.env';
    $dir = dirname($path);
    file_put_contents($path, "APP_KEY=\"base64:keep\"\nSITE_NAME=test\n");
    chmod($path, 0600);

    expect(deploy_hook_backup_env($path, 10))->toBeTrue();
    expect(deploy_hook_backup_env($path, 10))->toBeTrue();
    $backups = glob($dir.'/.env.backup-*') ?: [];
    expect($backups)->toHaveCount(2)
        ->and($backups[0])->not->toBe($backups[1])
        ->and(file_get_contents($backups[0]))->toBe("APP_KEY=\"base64:keep\"\nSITE_NAME=test\n")
        ->and(decoct(fileperms($backups[0]) & 0777))->toBe('600')
        ->and(decoct(fileperms($backups[1]) & 0777))->toBe('600');

    // Simuleer oudere backups (rsort op naam → nieuwste blijven).
    foreach (glob($dir.'/.env.backup-*') ?: [] as $old) {
        @unlink($old);
    }
    for ($i = 1; $i <= 12; $i++) {
        $stamp = sprintf('20260101T%06dZ-%d', $i, $i);
        file_put_contents($dir.'/.env.backup-'.$stamp, "n=$i\n");
    }
    expect(deploy_hook_backup_env($path, 3))->toBeTrue();
    $kept = glob($dir.'/.env.backup-*') ?: [];
    expect(count($kept))->toBe(3);
});

test('preserve_existing_always behoudt APP_PREVIOUS_KEYS ondanks upload', function () {
    $path = $this->tmp.'/prevkeys.env';
    file_put_contents($path, "APP_PREVIOUS_KEYS=\"base64:oldkeyoldkeyoldkeyoldkeyold\"\n");

    $merged = deploy_hook_preserve_existing_always(
        "APP_PREVIOUS_KEYS=\"base64:uploadkeyuploadkeyuploadkeyu\"\nAPP_ENV=production\n",
        $path,
        'APP_PREVIOUS_KEYS'
    );

    expect($merged)->toContain('APP_PREVIOUS_KEYS="base64:oldkeyoldkeyoldkeyoldkeyold"')
        ->and($merged)->not->toContain('uploadkeyuploadkeyuploadkeyu');
});

test('finish overschrijft bestaande APP_KEY niet bij andere upload-secret', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'tok-'.bin2hex(random_bytes(8));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);
    $config = deploy_hook_load_config($docroot);
    expect($config)->not->toBeNull();
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    mkdir($appDir.'/storage/logs', 0755, true);
    file_put_contents(
        $appDir.'/.env',
        "APP_KEY=\"base64:existingserverkeyexistingserver\"\nAPP_PREVIOUS_KEYS=\"base64:previouskeypreviouskeyprevi\"\nSITE_NAME=\"oud\"\n"
    );

    $zipPath = $this->tmp.'/artifact-key.zip';
    deployHookMakeZip($zipPath, [
        'artisan' => "#!/usr/bin/env php\n",
        'composer.json' => "{}\n",
        'vendor/autoload.php' => "<?php\n",
        'bootstrap/app.php' => "<?php\nreturn new stdClass;\n",
        'bootstrap/cache/packages.php' => "<?php\nreturn [];\n",
        'public/index.php' => "<?php\n// app\n",
        'public/build/manifest.json' => "{}\n",
        'storage/logs/.gitignore' => "*\n",
    ]);
    $raw = file_get_contents($zipPath);
    $sha = hash('sha256', $raw);

    $init = deploy_hook_handle($config, [
        'action' => 'init',
        'release' => 'keyfix1',
        'total_size' => strlen($raw),
        'total_sha256' => $sha,
        'chunk_count' => 1,
    ], [], 'POST');
    expect($init['status'])->toBe(200);
    $uploadId = $init['body']['upload_id'];

    $chunkPath = $this->tmp.'/c0.bin';
    file_put_contents($chunkPath, $raw);
    deploy_hook_handle($config, [
        'action' => 'chunk',
        'upload_id' => $uploadId,
        'chunk_index' => 0,
        'chunk_sha256' => hash('sha256', $raw),
        'chunk_path' => $chunkPath,
    ], [], 'POST');

    $finish = deploy_hook_handle($config, [
        'action' => 'finish',
        'upload_id' => $uploadId,
        'env' => "APP_NAME=test\nAPP_ENV=production\nAPP_KEY=\"base64:githubsecretkeygithubsecretke\"\nAPP_PREVIOUS_KEYS=\"base64:uploadprevuploadprevuploadp\"\nSITE_NAME=\"nieuw\"\n",
    ], [], 'POST');

    $backups = glob($appDir.'/.env.backup-*') ?: [];
    expect($finish['status'])->toBe(200)
        ->and(deploy_hook_env_get($appDir.'/.env', 'APP_KEY'))->toBe('base64:existingserverkeyexistingserver')
        ->and(deploy_hook_env_get($appDir.'/.env', 'APP_PREVIOUS_KEYS'))->toBe('base64:previouskeypreviouskeyprevi')
        ->and(deploy_hook_env_get($appDir.'/.env', 'SITE_NAME'))->toBe('nieuw')
        ->and($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toContain('existingserverkeyexistingserver');
});

test('auth weigert verkeerde token', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'correct-token-'.bin2hex(random_bytes(8));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);

    $config = deploy_hook_load_config($docroot);
    expect($config)->not->toBeNull();

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';
    $result = deploy_hook_handle($config, ['action' => 'init'], [], 'POST');
    expect($result['status'])->toBe(403)
        ->and($result['body']['error'])->toBe('forbidden');
});

test('auth accepteert juiste token (password_hash)', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'correct-token-'.bin2hex(random_bytes(8));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);

    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    $payload = random_bytes(64);
    $sha = hash('sha256', $payload);

    $result = deploy_hook_handle($config, [
        'action' => 'init',
        'release' => 'abc1234',
        'total_size' => strlen($payload),
        'total_sha256' => $sha,
        'chunk_count' => 1,
    ], [], 'POST');

    expect($result['status'])->toBe(200)
        ->and($result['body']['ok'])->toBeTrue()
        ->and($result['body']['upload_id'])->toMatch('/^[a-f0-9]{32}$/');
});

test('auth accepteert sha256 token_hash', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'sha-token-'.bin2hex(random_bytes(8));
    $hash = hash('sha256', $token);
    file_put_contents($this->tmp.'/.deploy/staging.php', "<?php\nreturn ["
        ."'token_hash'=>'{$hash}',"
        ."'app_dir'=>".var_export($appDir, true).","
        ."'docroot'=>".var_export($docroot, true).","
        ."'env'=>'staging'];\n");

    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    expect(deploy_hook_verify_token($hash, $token))->toBeTrue();
    expect(deploy_hook_verify_token($hash, 'nope'))->toBeFalse();
});

test('chunk assembly + checksum + storage behouden bij finish', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'tok-'.bin2hex(random_bytes(8));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);
    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    // Bestaande storage (logs) die behouden moet blijven
    $logDir = $appDir.'/storage/logs';
    mkdir($logDir, 0755, true);
    file_put_contents($logDir.'/keep.log', "keep-me\n");
    file_put_contents(
        $appDir.'/.env',
        "APP_KEY=\"base64:existingkeyexistingkeyexisti\"\nBACKUP_ENCRYPTION_KEY=\"keep-backup-key\"\nADMIN_DASHBOARD_EMAILS=\"owner@example.test\"\n"
    );

    // Mini Laravel-achtige zip
    $zipPath = $this->tmp.'/artifact.zip';
    deployHookMakeZip($zipPath, [
        'artisan' => "#!/usr/bin/env php\n",
        'composer.json' => "{}\n",
        'vendor/autoload.php' => "<?php\n",
        'bootstrap/app.php' => "<?php\nreturn new stdClass;\n",
        'bootstrap/cache/packages.php' => "<?php\nreturn [];\n",
        'public/index.php' => "<?php\n// old\n",
        'public/build/manifest.json' => "{}\n",
        'storage/logs/.gitignore' => "*\n",
    ]);

    $raw = file_get_contents($zipPath);
    $sha = hash('sha256', $raw);
    $size = strlen($raw);

    // Split in 2 chunks
    $mid = (int) floor($size / 2);
    $c0 = substr($raw, 0, $mid);
    $c1 = substr($raw, $mid);
    $p0 = $this->tmp.'/c0.bin';
    $p1 = $this->tmp.'/c1.bin';
    file_put_contents($p0, $c0);
    file_put_contents($p1, $c1);

    $init = deploy_hook_handle($config, [
        'action' => 'init',
        'release' => 'reltest1',
        'total_size' => $size,
        'total_sha256' => $sha,
        'chunk_count' => 2,
    ], [], 'POST');
    expect($init['status'])->toBe(200);
    $uploadId = $init['body']['upload_id'];

    $ch0 = deploy_hook_handle($config, [
        'action' => 'chunk',
        'upload_id' => $uploadId,
        'chunk_index' => 0,
        'chunk_sha256' => hash('sha256', $c0),
        'chunk_path' => $p0,
    ], [], 'POST');
    expect($ch0['status'])->toBe(200);

    $ch1 = deploy_hook_handle($config, [
        'action' => 'chunk',
        'upload_id' => $uploadId,
        'chunk_index' => 1,
        'chunk_sha256' => hash('sha256', $c1),
        'chunk_path' => $p1,
    ], [], 'POST');
    expect($ch1['status'])->toBe(200);

    // Verkeerde checksum → weigeren (aparte upload)
    $initBad = deploy_hook_handle($config, [
        'action' => 'init',
        'release' => 'relbad',
        'total_size' => $size,
        'total_sha256' => $sha,
        'chunk_count' => 1,
    ], [], 'POST');
    $badId = $initBad['body']['upload_id'];
    $badChunk = $this->tmp.'/bad.bin';
    file_put_contents($badChunk, $raw);
    $bad = deploy_hook_handle($config, [
        'action' => 'chunk',
        'upload_id' => $badId,
        'chunk_index' => 0,
        'chunk_sha256' => str_repeat('a', 64),
        'chunk_path' => $badChunk,
    ], [], 'POST');
    expect($bad['status'])->toBe(400)
        ->and($bad['body']['error'])->toBe('chunk_checksum');

    // Hook-bestanden in docroot vóór finish — moeten bewaard blijven.
    file_put_contents($docroot.'/deploy-hook.php', "<?php\n// hook\n");
    file_put_contents($docroot.'/lib.php', "<?php\n// lib\n");

    $env = "APP_NAME=\"Gratis planten\"\nAPP_ENV=staging\nAPP_KEY=\nBACKUP_ENCRYPTION_KEY=\nAPP_URL=\"https://staging.example\"\n";
    $finish = deploy_hook_handle($config, [
        'action' => 'finish',
        'upload_id' => $uploadId,
        'env' => $env,
    ], [], 'POST');

    expect($finish['status'])->toBe(200)
        ->and($finish['body']['ok'])->toBeTrue()
        ->and($finish['body'])->toHaveKey('method');

    // Storage log behouden
    expect(file_get_contents($appDir.'/storage/logs/keep.log'))->toBe("keep-me\n");

    // APP_KEY behouden (upload was leeg)
    $writtenKey = deploy_hook_env_get($appDir.'/.env', 'APP_KEY');
    expect($writtenKey)->toBe('base64:existingkeyexistingkeyexisti');

    // Timestamped .env.backup buiten docroot (naast shared .env)
    $backups = glob($appDir.'/.env.backup-*') ?: [];
    expect($backups)->not->toBeEmpty()
        ->and(file_get_contents($backups[0]))->toContain('base64:existingkeyexistingkeyexisti')
        ->and(decoct(fileperms($backups[0]) & 0777))->toBe('600');

    // BACKUP_ENCRYPTION_KEY behouden (upload was leeg)
    expect(deploy_hook_env_get($appDir.'/.env', 'BACKUP_ENCRYPTION_KEY'))->toBe('keep-backup-key');

    // ADMIN_DASHBOARD_EMAILS behouden (upload ontbrak de key)
    expect(deploy_hook_env_get($appDir.'/.env', 'ADMIN_DASHBOARD_EMAILS'))->toBe('owner@example.test');

    expect(is_file($docroot.'/index.php'))->toBeTrue();
    expect(is_file($docroot.'/build/manifest.json'))->toBeTrue();
    expect(file_get_contents($docroot.'/deploy-hook.php'))->toBe("<?php\n// hook\n");
    expect(file_get_contents($docroot.'/lib.php'))->toBe("<?php\n// lib\n");
});

test('finish wist config:cache in vorige release en herschrijft lsphp_restart', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'tok-'.bin2hex(random_bytes(8));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);
    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    // home_dir default = dirname(app_dir) = $this->tmp
    expect($config['home_dir'])->toBe($this->tmp);

    // Simuleer vorige live release met stale config:cache (oud DEPLOY_TOKEN).
    $oldRelease = $appDir.'/releases/oldrel';
    mkdir($oldRelease.'/bootstrap/cache', 0755, true);
    mkdir($oldRelease.'/public', 0755, true);
    file_put_contents(
        $oldRelease.'/bootstrap/cache/config.php',
        "<?php return ['stekjes'=>['deploy_token'=>'OLD_STALE_TOKEN']];\n"
    );
    file_put_contents($oldRelease.'/bootstrap/cache/routes-v7.php', "<?php\nreturn [];\n");
    file_put_contents($oldRelease.'/bootstrap/cache/packages.php', "<?php\nreturn [];\n");
    symlink($oldRelease, $appDir.'/current');

    $marker = $this->tmp.'/.lsphp_restart.txt';
    // Oude touch-only marker: finish moet inhoud herschrijven.
    file_put_contents($marker, "stale-touch\n");
    $oldContents = file_get_contents($marker);

    $zipPath = $this->tmp.'/artifact.zip';
    deployHookMakeZip($zipPath, [
        'artisan' => "#!/usr/bin/env php\n",
        'vendor/autoload.php' => "<?php\n",
        'bootstrap/app.php' => "<?php\nreturn new stdClass;\n",
        'bootstrap/cache/packages.php' => "<?php\nreturn [];\n",
        'public/index.php' => "<?php\n",
        'public/build/manifest.json' => "{}\n",
        'public/deploy-lsphp-restart.php' => "<?php\n// restart endpoint\n",
        'deploy/hook/deploy-hook.php' => "<?php\n// new-hook\n",
        'deploy/hook/lib.php' => "<?php\n// new-lib\n",
    ]);
    $raw = file_get_contents($zipPath);
    $sha = hash('sha256', $raw);
    $chunk = $this->tmp.'/one.bin';
    file_put_contents($chunk, $raw);

    $init = deploy_hook_handle($config, [
        'action' => 'init',
        'release' => 'newrel',
        'total_size' => strlen($raw),
        'total_sha256' => $sha,
        'chunk_count' => 1,
    ], [], 'POST');
    expect($init['status'])->toBe(200);
    $uploadId = $init['body']['upload_id'];

    expect(deploy_hook_handle($config, [
        'action' => 'chunk',
        'upload_id' => $uploadId,
        'chunk_index' => 0,
        'chunk_sha256' => $sha,
        'chunk_path' => $chunk,
    ], [], 'POST')['status'])->toBe(200);

    $finish = deploy_hook_handle($config, [
        'action' => 'finish',
        'upload_id' => $uploadId,
        'env' => "APP_NAME=test\nAPP_ENV=staging\nAPP_KEY=base64:x\n",
    ], [], 'POST');
    expect($finish['status'])->toBe(200)
        ->and($finish['body']['ok'])->toBeTrue()
        ->and($finish['body']['lsphp_restart'])->toBeTrue();

    // LiteSpeed-marker: inhoud herschreven (niet alleen mtime).
    expect(is_file($marker))->toBeTrue();
    $newContents = (string) file_get_contents($marker);
    expect($newContents)->not->toBe($oldContents)
        ->and($newContents)->toContain('restart ');

    // index.php wijst naar release-map (niet current-symlink).
    $index = (string) file_get_contents($docroot.'/index.php');
    expect($index)->toContain('releases/newrel')
        ->and($index)->not->toContain('/current/');

    // Hook self-update uit release.
    expect(file_get_contents($docroot.'/deploy-hook.php'))->toBe("<?php\n// new-hook\n");
    expect(file_get_contents($docroot.'/lib.php'))->toBe("<?php\n// new-lib\n");
    expect(is_file($docroot.'/deploy-lsphp-restart.php'))->toBeTrue();
    expect(is_file($appDir.'/.deploy-release'))->toBeTrue();

    // Vorige release: config/routes weg, packages blijft (stale worker → .env).
    expect(is_dir($oldRelease))->toBeTrue();
    expect(is_file($oldRelease.'/bootstrap/cache/config.php'))->toBeFalse();
    expect(is_file($oldRelease.'/bootstrap/cache/routes-v7.php'))->toBeFalse();
    expect(is_file($oldRelease.'/bootstrap/cache/packages.php'))->toBeTrue();

    // Nieuwe release heeft geen stale config; packages uit zip blijft.
    $newRelease = $appDir.'/releases/newrel';
    expect(is_dir($newRelease))->toBeTrue();
    expect(is_file($newRelease.'/bootstrap/cache/config.php'))->toBeFalse();
    expect(is_file($newRelease.'/bootstrap/cache/packages.php'))->toBeTrue();
});

test('resolve_home_dir: dirname(app_dir) of optionele home_dir', function () {
    expect(deploy_hook_resolve_home_dir('/home/gratisplanten/app-staging'))
        ->toBe('/home/gratisplanten');
    expect(deploy_hook_resolve_home_dir('/home/gratisplanten/app-prod'))
        ->toBe('/home/gratisplanten');
    expect(deploy_hook_resolve_home_dir('/home/gratisplanten/app-staging', '/custom/home'))
        ->toBe('/custom/home');
});

test('write_lsphp_restart herschrijft inhoud en respecteert home_dir override', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $altHome = $this->tmp.'/alt-home';
    mkdir($altHome, 0700, true);

    $token = 'tok-'.bin2hex(random_bytes(4));
    $hash = password_hash($token, PASSWORD_DEFAULT);
    file_put_contents($this->tmp.'/.deploy/staging.php', "<?php\nreturn ["
        ."'token_hash'=>".var_export($hash, true).","
        ."'app_dir'=>".var_export($appDir, true).","
        ."'docroot'=>".var_export($docroot, true).","
        ."'env'=>'staging',"
        ."'home_dir'=>".var_export($altHome, true).","
        ."];\n");

    $config = deploy_hook_load_config($docroot);
    expect($config['home_dir'])->toBe($altHome);

    $marker = $altHome.'/.lsphp_restart.txt';
    file_put_contents($marker, "old\n");
    expect(deploy_hook_write_lsphp_restart($altHome))->toBeTrue();
    expect(file_get_contents($marker))->toContain('restart ')
        ->and(file_get_contents($marker))->not->toBe("old\n");
    expect(is_file($this->tmp.'/.lsphp_restart.txt'))->toBeFalse();

    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    $restart = deploy_hook_handle($config, ['action' => 'lsphp_restart'], [], 'POST');
    expect($restart['status'])->toBe(200)
        ->and($restart['body']['ok'])->toBeTrue()
        ->and($restart['body']['lsphp_restart'])->toBeTrue();
});

test('resolve_live_app_root kiest release of current', function () {
    $appDir = $this->tmp.'/app-staging';
    $rel = $appDir.'/releases/abc123';
    mkdir($rel.'/vendor', 0755, true);
    file_put_contents($rel.'/artisan', "#!/usr/bin/env php\n");
    file_put_contents($rel.'/vendor/autoload.php', "<?php\n");
    symlink($rel, $appDir.'/current');

    expect(deploy_hook_resolve_live_app_root($appDir, 'abc123'))->toBe($rel);
    expect(deploy_hook_resolve_live_app_root($appDir))->toBe(realpath($rel) ?: $rel);
});

test('action migrate faalt netjes zonder release', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'tok-'.bin2hex(random_bytes(4));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);
    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    $result = deploy_hook_handle($config, ['action' => 'migrate'], [], 'POST');
    expect($result['status'])->toBe(500)
        ->and($result['body']['ok'])->toBeFalse()
        ->and($result['body']['error'])->toBe('no_release');
});

test('clear_bootstrap_cache_files is no-op als cache ontbreekt', function () {
    $missing = $this->tmp.'/no-app-here';
    deploy_hook_clear_bootstrap_cache_files($missing);
    expect(is_dir($missing))->toBeFalse();

    $empty = $this->tmp.'/empty-bootstrap';
    mkdir($empty.'/bootstrap/cache', 0755, true);
    deploy_hook_clear_bootstrap_cache_files($empty);
    expect(is_dir($empty.'/bootstrap/cache'))->toBeTrue();
});

test('upload-script: lsphp-rewrite, CLI-migrate, footer+deelplekken smoke retries', function () {
    $script = dirname(__DIR__, 2).'/scripts/deploy-hook-upload.sh';
    $src = file_get_contents($script);
    expect($src)->toContain('stale_403')
        ->and($src)->toContain('mogelijk stale proces')
        ->and($src)->toMatch('/sleep_secs=30/')
        ->and($src)->toContain('max_attempts=5')
        ->and($src)->toContain('LSPHP_RESTART_WAIT_SECS')
        ->and($src)->toContain('trigger_lsphp_restart')
        ->and($src)->toContain('action=lsphp_restart')
        ->and($src)->toContain('action=migrate')
        ->and($src)->toContain('deploy-lsphp-restart.php')
        ->and($src)->toContain('SMOKE_VERSION_ATTEMPTS')
        ->and($src)->toContain('wait_for_footer_version')
        ->and($src)->toContain('EXPECTED_DEELPLEKKEN')
        ->and($src)->toContain('/api/deelplekken')
        ->and($src)->toContain('pre-migrate');
});

test('weigert non-POST en mistende config', function () {
    $docroot = $this->tmp.'/docroot';
    $appDir = $this->tmp.'/app-staging';
    $token = 'tok-'.bin2hex(random_bytes(4));
    deployHookWriteConfig($this->tmp, $docroot, $appDir, $token);
    $config = deploy_hook_load_config($docroot);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;

    $result = deploy_hook_handle($config, ['action' => 'init'], [], 'GET');
    expect($result['status'])->toBe(405);

    expect(deploy_hook_load_config($this->tmp.'/nope'))->toBeNull();
});

test('sync_docroot verwijdert cPanel-placeholder index.html, behoudt ini, synct robots', function () {
    $docroot = $this->tmp.'/docroot';
    $public = $this->tmp.'/public-src';
    mkdir($public.'/css', 0755, true);
    file_put_contents($public.'/index.php', "<?php\n");
    file_put_contents($public.'/css/app.css', "body{}\n");

    // cPanel leftovers in docroot
    file_put_contents($docroot.'/index.html', "<html>Domein gereserveerd</html>\n");
    file_put_contents($docroot.'/.user.ini', "memory_limit=256M\n");
    file_put_contents($docroot.'/php.ini', "display_errors=Off\n");
    file_put_contents($docroot.'/robots.txt', "User-agent: *\nDisallow: /old\n");
    mkdir($docroot.'/cgi-bin', 0755, true);
    file_put_contents($docroot.'/cgi-bin/keep.txt', "cgi\n");
    // Lege stray public/ in docroot
    mkdir($docroot.'/public', 0755, true);

    // Build-time robots in release-public moet docroot overschrijven
    file_put_contents($public.'/robots.txt', "User-agent: *\nDisallow: /\n");

    $sync = deploy_hook_sync_docroot($public, $docroot);
    expect($sync['ok'])->toBeTrue();

    expect(is_file($docroot.'/index.html'))->toBeFalse();
    expect(is_file($docroot.'/index.php'))->toBeTrue();
    expect(file_get_contents($docroot.'/.user.ini'))->toBe("memory_limit=256M\n");
    expect(file_get_contents($docroot.'/php.ini'))->toBe("display_errors=Off\n");
    expect(file_get_contents($docroot.'/robots.txt'))->toBe("User-agent: *\nDisallow: /\n");
    expect(file_get_contents($docroot.'/cgi-bin/keep.txt'))->toBe("cgi\n");
    expect(is_dir($docroot.'/public'))->toBeFalse();
});

test('sync_docroot wist index.html niet als die bij public/ hoort', function () {
    $docroot = $this->tmp.'/docroot';
    $public = $this->tmp.'/public-src';
    mkdir($public, 0755, true);
    file_put_contents($public.'/index.html', "<html>app landing</html>\n");
    file_put_contents($public.'/index.php', "<?php\n");

    $sync = deploy_hook_sync_docroot($public, $docroot);
    expect($sync['ok'])->toBeTrue();
    expect(file_get_contents($docroot.'/index.html'))->toBe("<html>app landing</html>\n");
});

test('sync_docroot verwijdert niet-lege stray public/ niet', function () {
    $docroot = $this->tmp.'/docroot';
    $public = $this->tmp.'/public-src';
    mkdir($public, 0755, true);
    file_put_contents($public.'/index.php', "<?php\n");
    mkdir($docroot.'/public', 0755, true);
    file_put_contents($docroot.'/public/keep.txt', "x\n");

    $sync = deploy_hook_sync_docroot($public, $docroot);
    expect($sync['ok'])->toBeTrue();
    expect(file_get_contents($docroot.'/public/keep.txt'))->toBe("x\n");
});
