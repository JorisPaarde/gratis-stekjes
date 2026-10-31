<?php

declare(strict_types=1);

use App\Console\Commands\BackupDatabaseEncrypted;
use App\Services\EncryptedBackupService;
use App\Support\BackupCrypto;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'stekjes.backup_encryption_key' => 'test-backup-key-not-secret',
        'stekjes.backup_retain_days' => 30,
        'stekjes.cron_token' => 'test-cron-token',
    ]);
});

test('versleutelde backup command schrijft db en storage .enc', function () {
    $suffix = Str::random(8);
    $dbPath = storage_path('backups/test-db-'.$suffix.'.sql.gz.enc');
    $storagePath = storage_path('backups/test-storage-'.$suffix.'.tar.gz.enc');

    $public = storage_path('app/public');
    if (! is_dir($public)) {
        mkdir($public, 0755, true);
    }
    file_put_contents($public.'/backup-test-'.$suffix.'.txt', "hello-backup\n");

    $exit = Artisan::call('backup:database-encrypted', [
        '--path' => $dbPath,
        '--storage-path' => $storagePath,
    ]);
    expect($exit)->toBe(0);
    expect(is_file($dbPath))->toBeTrue();
    expect(is_file($storagePath))->toBeTrue();

    $dbBlob = (string) file_get_contents($dbPath);
    expect(str_starts_with($dbBlob, BackupCrypto::MAGIC))->toBeTrue();
    $plainGz = BackupDatabaseEncrypted::decryptForTests($dbBlob, 'test-backup-key-not-secret');
    expect(strlen($plainGz))->toBeGreaterThan(0);

    $stBlob = (string) file_get_contents($storagePath);
    $tarGz = BackupCrypto::decrypt($stBlob, 'test-backup-key-not-secret');
    expect(strlen($tarGz))->toBeGreaterThan(0);

    @unlink($dbPath);
    @unlink($storagePath);
    @unlink($public.'/backup-test-'.$suffix.'.txt');
});

test('backup:decrypt roundtrip herstelt gzip payload', function () {
    $suffix = Str::random(8);
    $enc = storage_path('backups/roundtrip-'.$suffix.'.sql.gz.enc');
    $out = storage_path('backups/roundtrip-'.$suffix.'.sql.gz');

    Artisan::call('backup:database-encrypted', [
        '--path' => $enc,
        '--no-storage' => true,
    ]);
    expect(is_file($enc))->toBeTrue();

    $exit = Artisan::call('backup:decrypt', [
        'input' => $enc,
        'output' => $out,
        '--key' => 'test-backup-key-not-secret',
    ]);
    expect($exit)->toBe(0);
    expect(is_file($out))->toBeTrue();
    expect(strlen((string) file_get_contents($out)))->toBeGreaterThan(0);

    @unlink($enc);
    @unlink($out);
});

test('retentie wist oude .enc bestanden', function () {
    $dir = storage_path('backups');
    if (! is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $old = $dir.'/db-old-'.Str::random(6).'.sql.gz.enc';
    $fresh = $dir.'/db-fresh-'.Str::random(6).'.sql.gz.enc';
    file_put_contents($old, BackupCrypto::encrypt('x', 'test-backup-key-not-secret'));
    file_put_contents($fresh, BackupCrypto::encrypt('y', 'test-backup-key-not-secret'));
    touch($old, time() - (40 * 86400));
    touch($fresh, time() - 60);

    $service = app(EncryptedBackupService::class);
    $deleted = $service->pruneOlderThan(30);
    expect($deleted)->toBeGreaterThanOrEqual(1);
    expect(is_file($old))->toBeFalse();
    expect(is_file($fresh))->toBeTrue();

    @unlink($fresh);
});

test('cron backup weigert zonder token en maakt backup met token', function () {
    $this->get('/cron/backup')->assertForbidden();
    $this->withHeader('X-Cron-Token', 'wrong')->get('/cron/backup')->assertForbidden();

    $response = $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->getJson('/cron/backup');

    $response->assertOk()->assertJsonPath('ok', true);
    $basename = $response->json('db.basename');
    expect($basename)->toBeString()->not->toBeEmpty();
    expect($response->json('storage.basename'))->toBeString()->not->toBeEmpty();
    expect($response->json('retain_days'))->toBe(30);

    $download = $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->get('/cron/backup?download=db');
    $download->assertOk();
    $download->assertDownload($basename);
    $onDisk = storage_path('backups/'.$basename);
    expect(is_file($onDisk))->toBeTrue();
    expect(str_starts_with((string) file_get_contents($onDisk), 'GPB1'))->toBeTrue();

    // Cleanup aangemaakte bestanden.
    $path = storage_path('backups/'.$basename);
    @unlink($path);
    $st = $response->json('storage.basename');
    if (is_string($st) && $st !== '') {
        @unlink(storage_path('backups/'.$st));
    }
});

test('cron backup download weigert path traversal', function () {
    $this->withHeader('X-Cron-Token', 'test-cron-token')
        ->get('/cron/backup?download='.urlencode('../.env'))
        ->assertNotFound();
});

test('BackupCrypto weigert lege sleutel en kapot blob', function () {
    expect(fn () => BackupCrypto::encrypt('a', ''))->toThrow(InvalidArgumentException::class);
    expect(fn () => BackupCrypto::decrypt('nope', 'k'))->toThrow(InvalidArgumentException::class);
});
