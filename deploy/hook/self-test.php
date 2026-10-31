#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Kleine CLI-harness zonder Pest/Composer (php deploy/hook/self-test.php).
 * Exit 0 = OK. CI draait ook Pest Unit/DeployHookTest.
 */

putenv('DEPLOY_HOOK_ALLOW_HTTP=1');
require __DIR__.'/lib.php';

function assert_true(bool $cond, string $msg): void
{
    if (! $cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$tmp = sys_get_temp_dir().'/hook-self-'.getmypid();
@mkdir($tmp.'/docroot', 0755, true);
@mkdir($tmp.'/.deploy', 0700, true);
@mkdir($tmp.'/app', 0755, true);

$token = bin2hex(random_bytes(16));
$hash = password_hash($token, PASSWORD_DEFAULT);
file_put_contents($tmp.'/.deploy/staging.php', "<?php return ["
    ."'token_hash'=>".var_export($hash, true).","
    ."'app_dir'=>".var_export($tmp.'/app', true).","
    ."'docroot'=>".var_export($tmp.'/docroot', true).","
    ."'env'=>'staging'];");

assert_true(deploy_hook_verify_token($hash, $token), 'password_verify token');
assert_true(! deploy_hook_verify_token($hash, 'x'), 'reject wrong token');

$shaHash = hash('sha256', $token);
assert_true(deploy_hook_verify_token($shaHash, $token), 'sha256 token_hash');

$cfg = deploy_hook_load_config($tmp.'/docroot');
assert_true($cfg !== null, 'load config by docroot');

$_SERVER['HTTPS'] = 'on';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong';
$r = deploy_hook_handle($cfg, ['action' => 'init'], [], 'POST');
assert_true($r['status'] === 403, 'forbidden on bad token');

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
$payload = random_bytes(100);
$init = deploy_hook_handle($cfg, [
    'action' => 'init',
    'release' => 'selftest',
    'total_size' => strlen($payload),
    'total_sha256' => hash('sha256', $payload),
    'chunk_count' => 1,
], [], 'POST');
assert_true($init['status'] === 200, 'init ok');

$chunkFile = $tmp.'/one.bin';
file_put_contents($chunkFile, $payload);
$ch = deploy_hook_handle($cfg, [
    'action' => 'chunk',
    'upload_id' => $init['body']['upload_id'],
    'chunk_index' => 0,
    'chunk_sha256' => hash('sha256', $payload),
    'chunk_path' => $chunkFile,
], [], 'POST');
assert_true($ch['status'] === 200, 'chunk ok');

// Minimal zip for finish
$zipPath = $tmp.'/a.zip';
$z = new ZipArchive;
$z->open($zipPath, ZipArchive::CREATE);
$z->addFromString('vendor/autoload.php', "<?php\n");
$z->addFromString('bootstrap/app.php', "<?php\nreturn 1;\n");
$z->addFromString('public/index.php', "<?php\n");
$z->addFromString('public/hello.txt', "hi\n");
$z->close();
$raw = file_get_contents($zipPath);

$init2 = deploy_hook_handle($cfg, [
    'action' => 'init',
    'release' => 'selftest2',
    'total_size' => strlen($raw),
    'total_sha256' => hash('sha256', $raw),
    'chunk_count' => 1,
], [], 'POST');
$cf = $tmp.'/z.bin';
file_put_contents($cf, $raw);
deploy_hook_handle($cfg, [
    'action' => 'chunk',
    'upload_id' => $init2['body']['upload_id'],
    'chunk_index' => 0,
    'chunk_sha256' => hash('sha256', $raw),
    'chunk_path' => $cf,
], [], 'POST');

mkdir($tmp.'/app/storage/logs', 0755, true);
file_put_contents($tmp.'/app/storage/logs/x.log', "preserve\n");

$fin = deploy_hook_handle($cfg, [
    'action' => 'finish',
    'upload_id' => $init2['body']['upload_id'],
    'env' => "APP_KEY=\nAPP_ENV=staging\n",
], [], 'POST');
$finMsg = ($fin['body']['method'] ?? $fin['body']['error'] ?? 'unknown');
assert_true($fin['status'] === 200, 'finish ok ('.$finMsg.')');
assert_true(is_file($tmp.'/app/storage/logs/x.log'), 'storage preserved');
assert_true(trim((string) file_get_contents($tmp.'/app/storage/logs/x.log')) === 'preserve', 'log content');
assert_true(is_file($tmp.'/docroot/index.php'), 'index.php written');

// BACKUP_ENCRYPTION_KEY behouden als upload leeg is.
$existingEnv = $tmp.'/preserve-backup.env';
file_put_contents($existingEnv, "APP_KEY=\"base64:abc\"\nBACKUP_ENCRYPTION_KEY=\"keep-me-secret\"\n");
$merged = deploy_hook_preserve_env_key("APP_KEY=\"base64:abc\"\nBACKUP_ENCRYPTION_KEY=\n", $existingEnv, 'BACKUP_ENCRYPTION_KEY');
assert_true(str_contains($merged, 'BACKUP_ENCRYPTION_KEY="keep-me-secret"'), 'backup key preserved');
$keepUpload = deploy_hook_preserve_env_key("BACKUP_ENCRYPTION_KEY=\"from-upload\"\n", $existingEnv, 'BACKUP_ENCRYPTION_KEY');
assert_true(str_contains($keepUpload, 'BACKUP_ENCRYPTION_KEY="from-upload"'), 'upload backup key wins');

// cleanup
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);

echo "self-test passed\n";
exit(0);
