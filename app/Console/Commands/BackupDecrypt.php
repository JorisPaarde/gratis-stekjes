<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BackupCrypto;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ontsleutel een .enc-backup naar plaintext (gzip/tar.gz).
 * Gebruikt BACKUP_ENCRYPTION_KEY uit .env; nooit de sleutel loggen.
 */
class BackupDecrypt extends Command
{
    protected $signature = 'backup:decrypt
                            {input : Pad naar .enc-bestand}
                            {output : Pad voor ontsleutelde output (.sql.gz of .tar.gz)}
                            {--key= : Optionele sleutel (alleen lokaal/tests; default: config)}';

    protected $description = 'Ontsleutel een GPB1-backupbestand (restore-hulp)';

    public function handle(): int
    {
        $input = (string) $this->argument('input');
        $output = (string) $this->argument('output');
        $keyOpt = $this->option('key');
        $key = is_string($keyOpt) && trim($keyOpt) !== ''
            ? trim($keyOpt)
            : trim((string) config('stekjes.backup_encryption_key', ''));

        if ($key === '') {
            $this->error('BACKUP_ENCRYPTION_KEY (of APP_KEY) ontbreekt.');

            return self::FAILURE;
        }
        if (! is_readable($input)) {
            $this->error('Kan input niet lezen.');

            return self::FAILURE;
        }

        try {
            $blob = file_get_contents($input);
            if ($blob === false) {
                throw new \RuntimeException('Lezen mislukt.');
            }
            $plain = BackupCrypto::decrypt($blob, $key);
            $dir = dirname($output);
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Kan output-map niet aanmaken.');
            }
            if (file_put_contents($output, $plain) === false) {
                throw new \RuntimeException('Schrijven mislukt.');
            }
            @chmod($output, 0600);
        } catch (Throwable $e) {
            $this->error('Ontsleutelen mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Ontsleuteld naar: '.$output);
        $this->line('Grootte: '.filesize($output).' bytes.');

        return self::SUCCESS;
    }
}
