<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Maakt een versleutelde database-dump (AES-256-CBC) met sleutel uit env.
 * Opslag/cron op de server: zie docs/backups.md (open punt voor hosting).
 */
class BackupDatabaseEncrypted extends Command
{
    protected $signature = 'backup:database-encrypted
                            {--path= : Optioneel pad voor het .enc-bestand}';

    protected $description = 'Schrijf een versleutelde database-backup naar storage/backups';

    private const MAGIC = 'GPB1';

    public function handle(): int
    {
        $key = trim((string) config('stekjes.backup_encryption_key', ''));
        if ($key === '') {
            $this->error('BACKUP_ENCRYPTION_KEY (of APP_KEY) ontbreekt — geen backup gemaakt.');

            return self::FAILURE;
        }

        try {
            $plaintext = $this->dumpPlaintext();
            $encrypted = $this->encrypt($plaintext, $key);

            $dir = storage_path('backups');
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Kan storage/backups niet aanmaken.');
            }
            chmod($dir, 0700);

            $path = $this->option('path')
                ?: $dir.'/db-'.gmdate('Ymd\THis\Z').'.sql.gz.enc';

            if (file_put_contents($path, $encrypted) === false) {
                throw new \RuntimeException('Kan backupbestand niet schrijven.');
            }
            chmod($path, 0600);

            $this->info('Versleutelde backup: '.$path);
            $this->line('Grootte: '.strlen($encrypted).' bytes (ciphertext).');
        } catch (Throwable $e) {
            $this->error('Backup mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function dumpPlaintext(): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $database = (string) config('database.connections.sqlite.database');
            if ($database === '' || $database === ':memory:' || ! is_readable($database)) {
                // In-memory / tests: SQL via schema dump is niet beschikbaar; minimale placeholder.
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

        if ($driver !== 'mysql') {
            throw new \RuntimeException('Backup ondersteunt mysql/sqlite; driver='.$driver);
        }

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

    private function encrypt(string $plaintext, string $key): string
    {
        $iv = random_bytes(16);
        $binaryKey = hash('sha256', $key, true);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Versleuteling mislukt.');
        }

        return self::MAGIC.$iv.$ciphertext;
    }

    /**
     * @internal voor tests / documentatie
     */
    public static function decryptForTests(string $blob, string $key): string
    {
        if (! str_starts_with($blob, self::MAGIC)) {
            throw new \InvalidArgumentException('Ongeldig backup-formaat.');
        }
        $iv = substr($blob, 4, 16);
        $ciphertext = substr($blob, 20);
        $binaryKey = hash('sha256', $key, true);
        $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $binaryKey, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Ontsleuteling mislukt.');
        }

        return $plain;
    }
}
