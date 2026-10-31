<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\BackupCrypto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Versleutelde DB- (+ optioneel storage/app/public-)backups met retentie.
 */
final class EncryptedBackupService
{
    public function encryptionKey(): string
    {
        return trim((string) config('stekjes.backup_encryption_key', ''));
    }

    public function retainDays(): int
    {
        $days = (int) config('stekjes.backup_retain_days', 30);

        return max(1, $days);
    }

    public function backupsDir(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Kan storage/backups niet aanmaken.');
        }
        @chmod($dir, 0700);

        return $dir;
    }

    /**
     * @return array{
     *     db: array{path: string, bytes: int, basename: string},
     *     storage: array{path: string, bytes: int, basename: string}|null,
     *     pruned: int,
     *     retain_days: int
     * }
     */
    public function run(bool $withStorage = true, ?string $dbPath = null, ?string $storagePath = null): array
    {
        $key = $this->encryptionKey();
        if ($key === '') {
            throw new \RuntimeException('BACKUP_ENCRYPTION_KEY (of APP_KEY) ontbreekt — geen backup gemaakt.');
        }

        $dir = $this->backupsDir();
        $stamp = gmdate('Ymd\THis\Z');

        $dbTarget = $dbPath ?: $dir.'/db-'.$stamp.'.sql.gz.enc';
        $dbPlain = $this->dumpDatabaseGzip();
        $dbEnc = BackupCrypto::encrypt($dbPlain, $key);
        $this->writeEncryptedFile($dbTarget, $dbEnc);

        $storageMeta = null;
        if ($withStorage) {
            $storageTarget = $storagePath ?: $dir.'/storage-'.$stamp.'.tar.gz.enc';
            $tarGz = $this->packPublicStorageGzip();
            $storageEnc = BackupCrypto::encrypt($tarGz, $key);
            $this->writeEncryptedFile($storageTarget, $storageEnc);
            $storageMeta = [
                'path' => $storageTarget,
                'bytes' => strlen($storageEnc),
                'basename' => basename($storageTarget),
            ];
        }

        $pruned = $this->pruneOlderThan($this->retainDays());

        return [
            'db' => [
                'path' => $dbTarget,
                'bytes' => strlen($dbEnc),
                'basename' => basename($dbTarget),
            ],
            'storage' => $storageMeta,
            'pruned' => $pruned,
            'retain_days' => $this->retainDays(),
        ];
    }

    public function writeEncryptedFile(string $path, string $encrypted): void
    {
        $parent = dirname($path);
        if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
            throw new \RuntimeException('Kan backupmap niet aanmaken.');
        }
        if (file_put_contents($path, $encrypted) === false) {
            throw new \RuntimeException('Kan backupbestand niet schrijven.');
        }
        @chmod($path, 0600);
    }

    /**
     * Verwijder .enc-bestanden ouder dan $days (mtime). Alleen bestandsnamen
     * die bij dit formaat horen (db- / storage- + .enc).
     */
    public function pruneOlderThan(int $days): int
    {
        $dir = $this->backupsDir();
        $cutoff = time() - ($days * 86400);
        $deleted = 0;

        foreach (glob($dir.'/{db,storage}-*.enc', GLOB_BRACE) ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            $mtime = (int) filemtime($file);
            if ($mtime > 0 && $mtime < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Alleen bestandsnamen onder storage/backups met veilig patroon.
     */
    public function resolveDownloadBasename(string $basename): ?string
    {
        $basename = basename($basename);
        if (! preg_match('/^(db|storage)-[A-Za-z0-9._-]+\.enc$/', $basename)) {
            return null;
        }
        $full = $this->backupsDir().'/'.$basename;
        if (! is_file($full) || ! is_readable($full)) {
            return null;
        }

        return $full;
    }

    public function latestBasename(string $kind = 'db'): ?string
    {
        $kind = $kind === 'storage' ? 'storage' : 'db';
        $files = glob($this->backupsDir().'/'.$kind.'-*.enc') ?: [];
        if ($files === []) {
            return null;
        }
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return basename($files[0]);
    }

    /** @return list<array{basename: string, bytes: int, mtime: int}> */
    public function listBackups(): array
    {
        $out = [];
        foreach (glob($this->backupsDir().'/{db,storage}-*.enc', GLOB_BRACE) ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            $out[] = [
                'basename' => basename($file),
                'bytes' => (int) filesize($file),
                'mtime' => (int) filemtime($file),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    public function dumpDatabaseGzip(): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return $this->dumpSqliteGzip();
        }

        if ($driver !== 'mysql') {
            throw new \RuntimeException('Backup ondersteunt mysql/sqlite; driver='.$driver);
        }

        try {
            return $this->dumpMysqlViaMysqldump();
        } catch (Throwable $e) {
            // Shared hosting / web-SAPI: mysqldump soms niet in PATH → PDO-fallback.
            return $this->dumpMysqlViaPdo();
        }
    }

    private function dumpSqliteGzip(): string
    {
        $database = (string) config('database.connections.sqlite.database');
        if ($database === '' || $database === ':memory:' || ! is_readable($database)) {
            $sql = "-- sqlite backup placeholder\nPRAGMA user_version;\n";
            $gz = gzencode($sql, 9);
            if ($gz === false) {
                throw new \RuntimeException('gzip mislukt.');
            }

            return $gz;
        }
        $raw = file_get_contents($database);
        if ($raw === false) {
            throw new \RuntimeException('Kan sqlite-bestand niet lezen.');
        }
        $gz = gzencode($raw, 9);
        if ($gz === false) {
            throw new \RuntimeException('gzip mislukt.');
        }

        return $gz;
    }

    private function dumpMysqlViaMysqldump(): string
    {
        $host = (string) config('database.connections.mysql.host');
        $port = (string) (config('database.connections.mysql.port') ?: 3306);
        $database = (string) config('database.connections.mysql.database');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');

        $result = Process::env(['MYSQL_PWD' => $password])
            ->timeout(600)
            ->run([
                'mysqldump',
                '-h'.$host,
                '-P'.$port,
                '-u'.$username,
                '--single-transaction',
                '--routines',
                '--triggers',
                $database,
            ]);

        if ($result->failed()) {
            $err = trim($result->errorOutput());
            $err = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $err);
            throw new \RuntimeException('mysqldump mislukt'.($err !== '' ? ': '.mb_substr($err, 0, 160) : '.'));
        }

        $gz = gzencode($result->output(), 9);
        if ($gz === false) {
            throw new \RuntimeException('gzip van mysqldump-output mislukt.');
        }

        return $gz;
    }

    /**
     * Minimale SQL-dump via PDO (tabellen + data). Geen routines/triggers.
     * Voldoende als nood-fallback wanneer mysqldump ontbreekt.
     */
    private function dumpMysqlViaPdo(): string
    {
        $pdo = DB::connection()->getPdo();
        $database = (string) config('database.connections.mysql.database');
        $lines = [
            '-- Gratis planten PDO backup fallback',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'`;',
            'USE `'.str_replace('`', '``', $database).'`;',
        ];

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string) $table;
            $safe = str_replace('`', '``', $table);
            $create = $pdo->query('SHOW CREATE TABLE `'.$safe.'`')->fetch(\PDO::FETCH_ASSOC);
            $createSql = is_array($create)
                ? (string) ($create['Create Table'] ?? $create['Create View'] ?? '')
                : '';
            if ($createSql === '') {
                continue;
            }
            $lines[] = 'DROP TABLE IF EXISTS `'.$safe.'`;';
            $lines[] = $createSql.';';

            $rows = $pdo->query('SELECT * FROM `'.$safe.'`');
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $cols = [];
                $vals = [];
                foreach ($row as $col => $val) {
                    $cols[] = '`'.str_replace('`', '``', (string) $col).'`';
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string) $val);
                    }
                }
                $lines[] = 'INSERT INTO `'.$safe.'` ('.implode(',', $cols).') VALUES ('.implode(',', $vals).');';
            }
        }
        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        $gz = gzencode(implode("\n", $lines)."\n", 9);
        if ($gz === false) {
            throw new \RuntimeException('gzip van PDO-dump mislukt.');
        }

        return $gz;
    }

    /**
     * tar.gz van storage/app/public (uploads). Lege map → minimale tar.
     */
    public function packPublicStorageGzip(): string
    {
        $publicRoot = storage_path('app/public');
        if (! is_dir($publicRoot) && ! mkdir($publicRoot, 0755, true) && ! is_dir($publicRoot)) {
            throw new \RuntimeException('Kan storage/app/public niet aanmaken.');
        }

        $tmpTar = tempnam(sys_get_temp_dir(), 'gpb-storage-');
        if ($tmpTar === false) {
            throw new \RuntimeException('tempnam mislukt.');
        }
        @unlink($tmpTar);
        $tmpTarGz = $tmpTar.'.tar.gz';

        try {
            $result = Process::path($publicRoot)
                ->timeout(300)
                ->run(['tar', '-czf', $tmpTarGz, '.']);
            if ($result->failed() || ! is_file($tmpTarGz)) {
                // Fallback: lege gzip-payload met marker.
                $gz = gzencode("-- empty storage/app/public\n", 9);
                if ($gz === false) {
                    throw new \RuntimeException('gzip storage-fallback mislukt.');
                }

                return $gz;
            }
            $raw = file_get_contents($tmpTarGz);
            if ($raw === false) {
                throw new \RuntimeException('Kan storage-tar niet lezen.');
            }

            return $raw;
        } finally {
            @unlink($tmpTarGz);
            @unlink($tmpTar);
        }
    }
}
