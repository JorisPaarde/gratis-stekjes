<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Export/import van gebruikers- en app-data (geen sessions/cache/jobs/seed-referenties).
 * Geen PII in logs: callers loggen alleen tabelnamen + tellingen.
 */
final class StekjesDataMigrator
{
    public const FORMAT = 'stekjes-data-export';

    public const FORMAT_VERSION = 1;

    /** Tabellen die nooit meegaan in een verhuis-export. */
    public const EXCLUDED_TABLES = [
        'migrations',
        'sessions',
        'password_reset_tokens',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'page_views',
    ];

    /**
     * Export-volgorde (FK-vriendelijk). Alleen tabellen die bestaan worden meegenomen.
     * deelplekken: alleen user-aanmeldingen (source !== seed).
     *
     * @var list<string>
     */
    public const EXPORT_TABLE_ORDER = [
        'users',
        'geocode_cache',
        'partner_markers',
        'evenementen',
        'aanbod',
        'conversations',
        'messages',
        'blocks',
        'reports',
        'feedback',
        'mail_log',
        'deelplekken',
    ];

    /**
     * @return array{
     *   format: string,
     *   format_version: int,
     *   exported_at: string,
     *   app_version: string,
     *   manifest: array{tables: array<string, array{count: int, sha256: string}>, files_count: int, files_tar_sha256: ?string},
     *   tables: array<string, list<array<string, mixed>>>,
     *   files: list<string>
     * }
     */
    public function export(): array
    {
        $tables = [];
        $manifestTables = [];
        $referencedFiles = [];

        foreach (self::EXPORT_TABLE_ORDER as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = $this->fetchExportRows($table);
            $encoded = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException('JSON-encode mislukt voor tabel '.$table);
            }

            $tables[$table] = $rows;
            $manifestTables[$table] = [
                'count' => count($rows),
                'sha256' => hash('sha256', $encoded),
            ];

            foreach ($rows as $row) {
                foreach ($this->extractStoragePaths($row) as $path) {
                    $referencedFiles[$path] = true;
                }
            }
        }

        $fileList = array_keys($referencedFiles);
        sort($fileList);

        return [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_version' => (string) config('site.version'),
            'manifest' => [
                'tables' => $manifestTables,
                'files_count' => count($fileList),
                'files_tar_sha256' => null,
            ],
            'tables' => $tables,
            'files' => $fileList,
        ];
    }

    /**
     * Schrijf data.json (+ optioneel files.tar) naar $directory. Retourneert paden + tellingen.
     *
     * @return array{data_json: string, files_tar: ?string, package_zip: string, counts: array<string, int>, files_count: int}
     */
    public function writeExportPackage(string $directory): array
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Kan export-map niet aanmaken.');
        }
        chmod($directory, 0700);

        $payload = $this->export();
        $fileList = $payload['files'];
        unset($payload['files']);

        $filesTar = null;
        $filesCount = 0;
        if ($fileList !== []) {
            $filesTar = $directory.'/files.tar';
            $filesCount = $this->writeReferencedFilesTar($fileList, $filesTar);
            $payload['manifest']['files_count'] = $filesCount;
            $payload['manifest']['files_tar_sha256'] = hash_file('sha256', $filesTar) ?: null;
        } else {
            $payload['manifest']['files_count'] = 0;
            $payload['manifest']['files_tar_sha256'] = null;
        }

        $dataJson = $directory.'/data.json';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($dataJson, $json) === false) {
            throw new \RuntimeException('Kan data.json niet schrijven.');
        }
        chmod($dataJson, 0600);

        $zipPath = $directory.'/stekjes-export.zip';
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Kan zip niet openen.');
        }
        $zip->addFile($dataJson, 'data.json');
        if ($filesTar !== null && is_file($filesTar)) {
            $zip->addFile($filesTar, 'files.tar');
        }
        $zip->close();
        chmod($zipPath, 0600);

        $counts = [];
        foreach ($payload['manifest']['tables'] as $table => $meta) {
            $counts[$table] = (int) $meta['count'];
        }

        return [
            'data_json' => $dataJson,
            'files_tar' => $filesTar,
            'package_zip' => $zipPath,
            'counts' => $counts,
            'files_count' => $filesCount,
        ];
    }

    /**
     * Schema-compatibiliteitsrapport: kolommen in export die ontbreken op target, en omgekeerd.
     *
     * @param  array<string, mixed>  $payload
     * @return array{tables: array<string, array{export_only: list<string>, target_only: list<string>, common: list<string>}>, missing_tables: list<string>}
     */
    public function schemaCompatReport(array $payload): array
    {
        $this->assertPayload($payload);
        $report = ['tables' => [], 'missing_tables' => []];

        foreach ($payload['tables'] as $table => $rows) {
            if (! Schema::hasTable($table)) {
                $report['missing_tables'][] = $table;

                continue;
            }
            $targetCols = Schema::getColumnListing($table);
            $exportCols = [];
            if ($rows !== []) {
                $exportCols = array_keys($rows[0]);
            } elseif (isset($payload['manifest']['tables'][$table])) {
                // Lege tabel: geen kolominfo in rijen.
                $exportCols = $targetCols;
            }
            $report['tables'][$table] = [
                'export_only' => array_values(array_diff($exportCols, $targetCols)),
                'target_only' => array_values(array_diff($targetCols, $exportCols)),
                'common' => array_values(array_intersect($exportCols, $targetCols)),
            ];
        }

        return $report;
    }

    /**
     * Importeer payload. Behoudt primary keys; geen stille FK-remaps.
     * Admin-e-mailbotsing: skip user-rij als e-mail al bestaat én id gelijk is;
     * bij id-mismatch → fout (tenzij force + expliciete skip van die user + gerelateerde rijen).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   ok: bool,
     *   dry_run: bool,
     *   counts: array<string, int>,
     *   skipped: array<string, int>,
     *   dropped_columns: array<string, list<string>>,
     *   schema: array<string, mixed>,
     *   error?: string
     * }
     */
    public function import(array $payload, bool $force = false, bool $dryRun = false): array
    {
        $this->assertPayload($payload);

        $schema = $this->schemaCompatReport($payload);
        if ($schema['missing_tables'] !== []) {
            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'counts' => [],
                'skipped' => [],
                'dropped_columns' => [],
                'schema' => $schema,
                'error' => 'missing_tables',
            ];
        }

        $guard = $this->nonAdminUserGuard();
        if (! $force && $guard['non_admin_count'] > 0) {
            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'counts' => [],
                'skipped' => [],
                'dropped_columns' => [],
                'schema' => $schema,
                'error' => 'target_has_users',
            ];
        }

        $droppedColumns = [];
        foreach ($schema['tables'] as $table => $info) {
            if ($info['export_only'] !== []) {
                $droppedColumns[$table] = $info['export_only'];
            }
        }

        if ($dryRun) {
            $counts = [];
            foreach ($payload['manifest']['tables'] as $table => $meta) {
                $counts[$table] = (int) $meta['count'];
            }

            return [
                'ok' => true,
                'dry_run' => true,
                'counts' => $counts,
                'skipped' => [],
                'dropped_columns' => $droppedColumns,
                'schema' => $schema,
            ];
        }

        $skipped = [];
        $counts = [];
        $skipUserIds = [];

        try {
            DB::transaction(function () use ($payload, &$skipped, &$counts, &$skipUserIds, $droppedColumns): void {
                // Users eerst: e-mail-merge / conflict-detectie.
                if (isset($payload['tables']['users'])) {
                    $result = $this->importUsers($payload['tables']['users'], $droppedColumns['users'] ?? []);
                    $counts['users'] = $result['inserted'];
                    $skipped['users'] = $result['skipped'];
                    $skipUserIds = $result['skip_ids'];
                }

                foreach (self::EXPORT_TABLE_ORDER as $table) {
                    if ($table === 'users') {
                        continue;
                    }
                    if (! isset($payload['tables'][$table])) {
                        continue;
                    }
                    $result = $this->importTable(
                        $table,
                        $payload['tables'][$table],
                        $droppedColumns[$table] ?? [],
                        $skipUserIds
                    );
                    $counts[$table] = $result['inserted'];
                    if ($result['skipped'] > 0) {
                        $skipped[$table] = $result['skipped'];
                    }
                }

                $this->resetAutoIncrements(array_keys($counts));
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'dry_run' => false,
                'counts' => $counts,
                'skipped' => $skipped,
                'dropped_columns' => $droppedColumns,
                'schema' => $schema,
                'error' => $e::class,
            ];
        }

        return [
            'ok' => true,
            'dry_run' => false,
            'counts' => $counts,
            'skipped' => $skipped,
            'dropped_columns' => $droppedColumns,
            'schema' => $schema,
        ];
    }

    /**
     * Extraheer en importeer een package-zip (data.json + optioneel files.tar).
     *
     * @return array<string, mixed>
     */
    public function importPackage(string $zipPath, bool $force = false, bool $dryRun = false): array
    {
        $tmp = sys_get_temp_dir().'/stekjes-import-'.bin2hex(random_bytes(8));
        if (! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
            throw new \RuntimeException('Kan import-temp niet aanmaken.');
        }

        try {
            $zip = new \ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Kan package-zip niet openen.');
            }
            $zip->extractTo($tmp);
            $zip->close();

            $dataPath = $tmp.'/data.json';
            if (! is_readable($dataPath)) {
                throw new \RuntimeException('data.json ontbreekt in package.');
            }
            $raw = file_get_contents($dataPath);
            if ($raw === false) {
                throw new \RuntimeException('Kan data.json niet lezen.');
            }
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            $result = $this->import($payload, $force, $dryRun);

            if ($result['ok'] && ! $dryRun) {
                $filesTar = $tmp.'/files.tar';
                if (is_readable($filesTar)) {
                    $result['files_restored'] = $this->restoreFilesTar($filesTar);
                } else {
                    $result['files_restored'] = 0;
                }
            }

            return $result;
        } finally {
            $this->removeDirectory($tmp);
        }
    }

    /**
     * @return array{non_admin_count: int, total: int}
     */
    public function nonAdminUserGuard(): array
    {
        if (! Schema::hasTable('users')) {
            return ['non_admin_count' => 0, 'total' => 0];
        }

        $adminEmails = $this->adminEmails();
        $total = (int) DB::table('users')->count();
        if ($adminEmails === []) {
            return ['non_admin_count' => $total, 'total' => $total];
        }

        $nonAdmin = (int) DB::table('users')->whereNotIn('email', $adminEmails)->count();

        return ['non_admin_count' => $nonAdmin, 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchExportRows(string $table): array
    {
        $query = DB::table($table);

        if ($table === 'deelplekken') {
            // Zonder source-kolom zijn alle rijen seed-referentie → niet exporteren.
            if (! Schema::hasColumn('deelplekken', 'source')) {
                return [];
            }
            $query->where('source', '!=', 'seed');
        }

        // Stabiele volgorde: id als die bestaat, anders alle kolommen via primary.
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        $rows = $query->get()->map(function ($row) {
            return $this->normalizeRow((array) $row);
        })->all();

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function extractStoragePaths(array $row): array
    {
        $paths = [];
        foreach ($row as $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            // Relatieve paden onder de public-disk (hele waarde of substring).
            if (preg_match_all('#(?<![A-Za-z0-9_./-])((?:photos|uploads)/[A-Za-z0-9_./-]+)#', $value, $m) > 0) {
                foreach ($m[1] as $p) {
                    $paths[] = $p;
                }
            }
            if (preg_match('#(?:^|/)storage/app/public/([A-Za-z0-9_./-]+)#', $value, $m2) === 1) {
                $paths[] = $m2[1];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $relativePaths  paden t.o.v. storage/app/public
     */
    private function writeReferencedFilesTar(array $relativePaths, string $tarPath): int
    {
        $publicRoot = storage_path('app/public');
        $staging = sys_get_temp_dir().'/stekjes-files-'.bin2hex(random_bytes(6));
        mkdir($staging, 0700, true);
        $count = 0;

        try {
            foreach ($relativePaths as $rel) {
                $rel = ltrim(str_replace(['..', '\\'], ['', '/'], $rel), '/');
                if ($rel === '') {
                    continue;
                }
                $src = $publicRoot.'/'.$rel;
                if (! is_file($src)) {
                    continue;
                }
                $dest = $staging.'/'.$rel;
                $dir = dirname($dest);
                if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                    continue;
                }
                if (! copy($src, $dest)) {
                    continue;
                }
                $count++;
            }

            if ($count === 0) {
                touch($tarPath);
                chmod($tarPath, 0600);

                return 0;
            }

            if (is_file($tarPath)) {
                unlink($tarPath);
            }
            $result = Process::path($staging)->timeout(120)->run(['tar', '-cf', $tarPath, '.']);
            if ($result->failed() || ! is_file($tarPath)) {
                throw new \RuntimeException('tar van storage-bestanden mislukt.');
            }
            chmod($tarPath, 0600);

            return $count;
        } finally {
            $this->removeDirectory($staging);
        }
    }

    private function restoreFilesTar(string $tarPath): int
    {
        if (! is_file($tarPath) || filesize($tarPath) === 0) {
            return 0;
        }

        $publicRoot = storage_path('app/public');
        if (! is_dir($publicRoot) && ! mkdir($publicRoot, 0755, true) && ! is_dir($publicRoot)) {
            throw new \RuntimeException('Kan storage/app/public niet aanmaken.');
        }

        $result = Process::timeout(120)->run(['tar', '-xf', $tarPath, '-C', $publicRoot]);
        if ($result->failed()) {
            throw new \RuntimeException('tar extract mislukt.');
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($publicRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        // Tel alleen bestanden die in de tar zaten is lastig na merge; rapporteer tar-leden.
        $list = Process::timeout(60)->run(['tar', '-tf', $tarPath]);
        if (! $list->failed()) {
            $lines = array_filter(explode("\n", trim($list->output())), static function (string $l): bool {
                $l = trim($l);

                return $l !== '' && ! str_ends_with($l, '/');
            });

            return count($lines);
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @return array{inserted: int, skipped: int, skip_ids: list<int>}
     */
    private function importUsers(array $rows, array $dropCols): array
    {
        $inserted = 0;
        $skipped = 0;
        $skipIds = [];
        $targetCols = Schema::getColumnListing('users');

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $id = isset($row['id']) ? (int) $row['id'] : 0;

            if ($email === '' || $id <= 0) {
                throw new \RuntimeException('Ongeldige user-rij in export.');
            }

            $existing = DB::table('users')->where('email', $email)->first();
            if ($existing !== null) {
                if ((int) $existing->id !== $id) {
                    throw new \RuntimeException('email_id_mismatch');
                }
                // Zelfde e-mail + zelfde id: skip (behoud bestaande, o.a. prod-admin).
                $skipped++;
                $skipIds[] = $id;

                continue;
            }

            if (DB::table('users')->where('id', $id)->exists()) {
                throw new \RuntimeException('user_id_conflict');
            }

            // Password-hash letterlijk behouden (geen re-hash via Eloquent casts).
            DB::table('users')->insert($row);
            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped, 'skip_ids' => $skipIds];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  list<int>  $skipUserIds
     * @return array{inserted: int, skipped: int}
     */
    private function importTable(string $table, array $rows, array $dropCols, array $skipUserIds): array
    {
        $targetCols = Schema::getColumnListing($table);
        $inserted = 0;
        $skipped = 0;
        $userFkColumns = $this->userForeignKeyColumns($table);

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);

            if ($skipUserIds !== [] && $userFkColumns !== []) {
                $skip = false;
                foreach ($userFkColumns as $col) {
                    if (isset($row[$col]) && in_array((int) $row[$col], $skipUserIds, true)) {
                        // Gerelateerde rijen van gemergede admin: skip als PK al bestaat, anders toch inserten
                        // met dezelfde user_id (die bestaat al op target).
                        break;
                    }
                }
                // Skip-user betekent user-rij niet opnieuw geplaatst; FK naar die id is wél geldig.
                unset($skip);
            }

            // Composite primary (blocks): skip als al aanwezig.
            if ($table === 'blocks') {
                $exists = DB::table('blocks')
                    ->where('blocker_id', $row['blocker_id'] ?? null)
                    ->where('blocked_id', $row['blocked_id'] ?? null)
                    ->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }
                DB::table('blocks')->insert($row);
                $inserted++;

                continue;
            }

            if ($table === 'geocode_cache') {
                $key = $row['query_key'] ?? null;
                if ($key !== null && DB::table('geocode_cache')->where('query_key', $key)->exists()) {
                    $skipped++;

                    continue;
                }
                DB::table('geocode_cache')->insert($row);
                $inserted++;

                continue;
            }

            if (isset($row['id'])) {
                if (DB::table($table)->where('id', $row['id'])->exists()) {
                    $skipped++;

                    continue;
                }
            }

            DB::table($table)->insert($row);
            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * @return list<string>
     */
    private function userForeignKeyColumns(string $table): array
    {
        return match ($table) {
            'aanbod', 'feedback', 'mail_log' => ['user_id'],
            'conversations' => ['user_a_id', 'user_b_id'],
            'messages' => ['sender_id'],
            'blocks' => ['blocker_id', 'blocked_id'],
            'reports' => ['reporter_id', 'reported_user_id'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $targetCols
     * @param  list<string>  $dropCols
     * @return array<string, mixed>
     */
    private function filterColumns(array $row, array $targetCols, array $dropCols): array
    {
        $out = [];
        foreach ($row as $col => $value) {
            if (in_array($col, $dropCols, true)) {
                continue;
            }
            if (! in_array($col, $targetCols, true)) {
                continue;
            }
            $out[$col] = $value;
        }

        return $out;
    }

    /**
     * @param  list<string>  $tables
     */
    private function resetAutoIncrements(array $tables): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }
            $max = (int) DB::table($table)->max('id');
            $next = max($max, 0) + 1;

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.$next);
            } elseif ($driver === 'sqlite') {
                // sqlite_sequence bestaat alleen als er AUTOINCREMENT is gebruikt.
                try {
                    $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();
                    if ($exists) {
                        DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
                    }
                } catch (Throwable) {
                    // Geen sqlite_sequence — niets te doen.
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function adminEmails(): array
    {
        $emails = [];
        foreach ([
            (string) config('stekjes.admin_email', ''),
            (string) config('mail.from.address', ''),
            (string) config('site.contact_email', ''),
        ] as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPayload(array $payload): void
    {
        if (($payload['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('Ongeldig export-formaat.');
        }
        if ((int) ($payload['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            throw new \InvalidArgumentException('Onbekende format_version.');
        }
        if (! isset($payload['tables']) || ! is_array($payload['tables'])) {
            throw new \InvalidArgumentException('tables ontbreekt.');
        }
        if (! isset($payload['manifest']['tables']) || ! is_array($payload['manifest']['tables'])) {
            throw new \InvalidArgumentException('manifest ontbreekt.');
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
