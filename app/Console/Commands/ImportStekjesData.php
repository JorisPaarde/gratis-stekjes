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
                            {--dry-run : Zelfde samenvoeg-writes als echt, in een transactie die wordt teruggedraaid}
                            {--skip-migrate : Sla migrate --force over}';

    protected $description = 'Samenvoeg-import stekjes-data-export (ID-remap, geen deletes; idempotent via import_runs)';

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
                $packageSha = hash_file('sha256', $path) ?: null;
                /** @var array<string, mixed> $payload */
                $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $result = $migrator->import($payload, $force, $dryRun, $packageSha);
            } else {
                $result = $migrator->importPackage($path, $force, $dryRun);
            }
        } catch (Throwable $e) {
            $this->error('Import mislukt: '.$e::class);

            return self::FAILURE;
        }

        if (! empty($result['already_imported'])) {
            $this->info('Already imported (idempotent)');
            $this->printResult($result);

            return self::SUCCESS;
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
        if (! empty($result['already_imported'])) {
            $this->line('already_imported=1');
        }
        foreach (($result['tables'] ?? []) as $table => $stats) {
            if (! is_array($stats)) {
                continue;
            }
            foreach (['prod_voor', 'bron', 'toegevoegd', 'samengevoegd', 'overgeslagen', 'prod_na', 'sum_voor', 'sum_na'] as $key) {
                if (array_key_exists($key, $stats)) {
                    $this->line($table.'.'.$key.'='.$stats[$key]);
                }
            }
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
        $diag = $result['diagnosis'] ?? null;
        if (is_array($diag)) {
            foreach (['sqlstate', 'driver_code', 'table', 'column', 'error_class'] as $key) {
                if (array_key_exists($key, $diag) && $diag[$key] !== null && $diag[$key] !== '') {
                    $this->line('diagnosis.'.$key.'='.$diag[$key]);
                }
            }
        }
    }
}
