<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Staging-only: detect plain-PHP schema, mysqldump backup, drop all tables.
 * Never wire this into the production deploy workflow.
 */
class ResetLegacyStagingSchema extends Command
{
    protected $signature = 'staging:reset-legacy-schema
                            {--force : Required to run the destructive reset}';

    protected $description = 'Staging only: backup + drop legacy plain-PHP tables so Laravel migrate can run';

    private const LARAVEL_USERS_MIGRATION = '0001_01_01_000000_create_users_table';

    public function handle(): int
    {
        if (! app()->environment('staging')) {
            $this->error('Weigert: alleen toegestaan bij APP_ENV=staging.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Weigert: geef --force mee (destructief: backup + drop alle tabellen).');

            return self::FAILURE;
        }

        if (! $this->stagingDbConfigured()) {
            $this->error('Weigert: staging DB-config ontbreekt (DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD).');

            return self::FAILURE;
        }

        if (! $this->isLegacySchema()) {
            $this->info('Geen legacy-schema gedetecteerd; niets gedaan.');

            return self::SUCCESS;
        }

        $this->warn('Legacy-schema gedetecteerd — backup maken en alle tabellen droppen.');

        try {
            $backup = $this->backupDatabase();
            if ($backup !== null) {
                $this->info('Backup: '.$backup);
            }

            $dropped = $this->dropAllTables();
            $this->info('Gedropt: '.$dropped.' tabel(len). Daarna: php artisan migrate --force');
        } catch (Throwable $e) {
            $this->error('Legacy-reset mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function stagingDbConfigured(): bool
    {
        if (config('database.default') !== 'mysql') {
            // SQLite in tests: allow detection/drop path without remote secrets.
            return app()->environment('staging') && DB::getDriverName() === 'sqlite';
        }

        $host = trim((string) config('database.connections.mysql.host'));
        $database = trim((string) config('database.connections.mysql.database'));
        $username = trim((string) config('database.connections.mysql.username'));
        $password = config('database.connections.mysql.password');

        return $host !== '' && $database !== '' && $username !== '' && is_string($password);
    }

    private function isLegacySchema(): bool
    {
        if (Schema::hasTable('schema_migrations')) {
            return true;
        }

        if (! Schema::hasTable('users')) {
            return false;
        }

        if (! Schema::hasTable('migrations')) {
            return true;
        }

        return ! DB::table('migrations')
            ->where('migration', self::LARAVEL_USERS_MIGRATION)
            ->exists();
    }

    /** @return non-empty-string|null Absolute path to gzip backup, or null when skipped (sqlite tests). */
    private function backupDatabase(): ?string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Kan storage/backups niet aanmaken.');
        }
        chmod($dir, 0700);

        if (DB::getDriverName() !== 'mysql') {
            $this->warn('Geen mysqldump (driver='.DB::getDriverName().'); alleen droppen.');

            return null;
        }

        $timestamp = gmdate('Ymd\THis\Z');
        $path = $dir.'/legacy-'.$timestamp.'.sql.gz';

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
            // Never echo credentials; mysqldump may mention host/db only.
            throw new \RuntimeException('mysqldump mislukt'.($err !== '' ? ': '.$this->redactDumpError($err) : '.'));
        }

        $gzipped = gzencode($result->output(), 9);
        if ($gzipped === false) {
            throw new \RuntimeException('gzip van mysqldump-output mislukt.');
        }

        if (file_put_contents($path, $gzipped) === false) {
            throw new \RuntimeException('Kan backupbestand niet schrijven.');
        }
        chmod($path, 0600);

        return $path;
    }

    private function redactDumpError(string $err): string
    {
        $err = preg_replace('/-p\S+/', '-p***', $err) ?? $err;
        $err = preg_replace('/MYSQL_PWD=\S+/', 'MYSQL_PWD=***', $err) ?? $err;

        return $err;
    }

    private function dropAllTables(): int
    {
        $driver = DB::getDriverName();
        $tables = Schema::getTableListing();

        if ($tables === []) {
            return 0;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
                Schema::drop($name);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // MySQL: also drop views if any slipped in (getTableListing is tables).
        if ($driver === 'mysql') {
            $views = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['VIEW']);
            foreach ($views as $row) {
                $values = array_values((array) $row);
                $name = (string) ($values[0] ?? '');
                if ($name !== '') {
                    DB::statement('DROP VIEW IF EXISTS `'.str_replace('`', '``', $name).'`');
                }
            }
        }

        return count($tables);
    }
}
