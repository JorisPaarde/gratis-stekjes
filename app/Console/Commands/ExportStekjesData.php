<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StekjesDataMigrator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Exporteer gebruikers-/app-data naar een package (data.json + optioneel files.tar in zip).
 * Output bevat alleen tabelnamen + tellingen (geen PII).
 */
class ExportStekjesData extends Command
{
    protected $signature = 'stekjes:export-data
                            {--path= : Doelmap (default: storage/app/stekjes-export-<timestamp>)}';

    protected $description = 'Exporteer users + gerelateerde app-data naar JSON/zip (geen sessions/cache/jobs/seed-deelplekken)';

    public function handle(StekjesDataMigrator $migrator): int
    {
        $path = $this->option('path');
        if (! is_string($path) || trim($path) === '') {
            $path = storage_path('app/stekjes-export-'.gmdate('Ymd\THis\Z'));
        }

        try {
            $result = $migrator->writeExportPackage($path);
        } catch (Throwable $e) {
            $this->error('Export mislukt: '.$e::class);

            return self::FAILURE;
        }

        $this->info('Export OK');
        $this->line('path='.$result['package_zip']);
        $this->line('files_count='.$result['files_count']);
        foreach ($result['counts'] as $table => $count) {
            $this->line($table.'='.$count);
        }

        return self::SUCCESS;
    }
}
