<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EncryptedBackupService;
use App\Support\BackupCrypto;
use Illuminate\Console\Command;
use Throwable;

/**
 * Maakt een versleutelde database-dump (AES-256-CBC) + optioneel storage/app/public.
 * Planning/off-site: zie docs/backups.md en workflow backup.yml.
 */
class BackupDatabaseEncrypted extends Command
{
    protected $signature = 'backup:database-encrypted
                            {--path= : Optioneel pad voor het DB-.enc-bestand}
                            {--storage-path= : Optioneel pad voor storage-.enc}
                            {--no-storage : Sla storage/app/public over}
                            {--retain-days= : Overschrijf bewaartermijn (dagen)}';

    protected $description = 'Schrijf versleutelde DB- (+ storage-)backup naar storage/backups';

    public function handle(EncryptedBackupService $backups): int
    {
        $retainOpt = $this->option('retain-days');
        if ($retainOpt !== null && $retainOpt !== '') {
            config(['stekjes.backup_retain_days' => max(1, (int) $retainOpt)]);
        }

        try {
            $result = $backups->run(
                withStorage: ! (bool) $this->option('no-storage'),
                dbPath: $this->option('path') ?: null,
                storagePath: $this->option('storage-path') ?: null,
            );
        } catch (Throwable $e) {
            $this->error('Backup mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Versleutelde DB-backup: '.$result['db']['path']);
        $this->line('Grootte: '.$result['db']['bytes'].' bytes (ciphertext).');
        if ($result['storage'] !== null) {
            $this->info('Versleutelde storage-backup: '.$result['storage']['path']);
            $this->line('Grootte: '.$result['storage']['bytes'].' bytes (ciphertext).');
        }
        $this->line('Retentie: '.$result['retain_days'].' dagen; verwijderd: '.$result['pruned'].'.');

        return self::SUCCESS;
    }

    /**
     * @internal voor tests / documentatie
     */
    public static function decryptForTests(string $blob, string $key): string
    {
        return BackupCrypto::decrypt($blob, $key);
    }
}
