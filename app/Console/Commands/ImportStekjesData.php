<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StekjesDataMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Importeer een stekjes-export package. Logt alleen tabelnamen + tellingen.
 */
class ImportStekjesData extends Command
{
    protected $signature = 'stekjes:import-data
                            {path : Pad naar stekjes-export.zip of data.json}
                            {--force : Sta import toe als target al non-admin users heeft}
                            {--dry-run : Alleen tellingen + schema-compat, geen writes}
                            {--skip-migrate : Sla migrate --force over}';

    protected $description = 'Importeer stekjes-data-export (behoud PKs/password hashes; geen stille remaps)';

    public function handle(StekjesDataMigrator $migrator): int
    {
        $path = (string) $this->argument('path');
        if (! is_readable($path)) {
            $this->error('Bestand niet leesbaar.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('skip-migrate')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $e) {
                $this->error('Migrate mislukt: '.$e::class);

                return self::FAILURE;
            }
        }

        try {
            if (str_ends_with(strtolower($path), '.json')) {
                $raw = file_get_contents($path);
                if ($raw === false) {
                    throw new \RuntimeException('Kan JSON niet lezen.');
                }
                /** @var array<string, mixed> $payload */
                $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $result = $migrator->import($payload, $force, $dryRun);
            } else {
                $result = $migrator->importPackage($path, $force, $dryRun);
            }
        } catch (Throwable $e) {
            $this->error('Import mislukt: '.$e::class);
            if ($e->getMessage() === 'email_id_mismatch' || $e->getMessage() === 'user_id_conflict') {
                $this->line('error='.$e->getMessage());
            }

            return self::FAILURE;
        }

        if (! $result['ok']) {
            $this->error('Import geweigerd: '.($result['error'] ?? 'unknown'));
            $this->printResult($result);

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run OK' : 'Import OK');
        $this->printResult($result);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function printResult(array $result): void
    {
        foreach (($result['counts'] ?? []) as $table => $count) {
            $this->line('count.'.$table.'='.$count);
        }
        foreach (($result['skipped'] ?? []) as $table => $count) {
            $this->line('skipped.'.$table.'='.$count);
        }
        foreach (($result['dropped_columns'] ?? []) as $table => $cols) {
            if (is_array($cols) && $cols !== []) {
                $this->line('dropped.'.$table.'='.implode(',', $cols));
            }
        }
        if (isset($result['files_restored'])) {
            $this->line('files_restored='.$result['files_restored']);
        }
        $missing = $result['schema']['missing_tables'] ?? [];
        if (is_array($missing) && $missing !== []) {
            $this->line('missing_tables='.implode(',', $missing));
        }
    }
}
