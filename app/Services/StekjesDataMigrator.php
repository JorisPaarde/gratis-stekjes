<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Throwable;

/**
 * Export/samenvoeg-import van gebruikers- en app-data.
 * Import remapt IDs, wist niets op target, is idempotent via import_runs.
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
        'import_runs',
        'import_id_maps',
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
        'aanbod',
        'aanbod_fotos',
        'conversations',
        'messages',
        'blocks',
        'reports',
        'feedback',
        'mail_log',
        'deelplekken',
        'ervaringen',
        'milestones_sent',
        'evenementen',
        'page_views',
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
     * Samenvoeg-import: voegt bronrijen toe (ID-remap), wist/overschrijft niets op target.
     * Dry-run: exact dezelfde writes in een transactie die wordt teruggedraaid.
     * Exact dezelfde package-sha → already_imported. Zelfde bron-inhoud (andere export-tijd)
     * → per rij via import_id_maps als al_aanwezig (0 toevoegen / 0 optellen).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function import(array $payload, bool $force = false, bool $dryRun = false, ?string $packageSha256 = null): array
    {
        unset($force); // API-compat; samenvoegen blokkeert niet meer op bestaande users.
        $this->assertPayload($payload);

        $schema = $this->schemaCompatReport($payload);
        if ($schema['missing_tables'] !== []) {
            return $this->failureResult($dryRun, $schema, 'missing_tables');
        }

        $droppedColumns = [];
        foreach ($schema['tables'] as $table => $info) {
            if ($info['export_only'] !== []) {
                $droppedColumns[$table] = $info['export_only'];
            }
        }

        $packageSha256 = $packageSha256 ?? $this->fingerprintPayload($payload);
        $sourceFingerprint = $this->sourceFingerprint($payload);

        // Exact dezelfde package (zelfde zip/sha) → geen herverwerking.
        // Zelfde bron-inhoud met andere exported_at: wél verwerken via import_id_maps
        // zodat dry-run 0 toevoegen/optellen + al_aanwezig toont (niet de oude counts).
        if (Schema::hasTable('import_runs')) {
            $prior = DB::table('import_runs')
                ->where('package_sha256', $packageSha256)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->first();
            if ($prior !== null) {
                /** @var array<string, mixed> $stored */
                $stored = is_string($prior->stats_json)
                    ? (json_decode($prior->stats_json, true) ?: [])
                    : ((array) (json_decode(json_encode($prior->stats_json), true) ?: []));

                return [
                    'ok' => true,
                    'dry_run' => $dryRun,
                    'already_imported' => true,
                    'error' => 'already_imported',
                    'tables' => $stored['tables'] ?? [],
                    'counts' => $stored['counts'] ?? [],
                    'skipped' => $stored['skipped'] ?? [],
                    'dropped_columns' => $droppedColumns,
                    'schema' => $schema,
                    'package_sha256' => $packageSha256,
                    'source_fingerprint' => $sourceFingerprint,
                ];
            }
        }

        $tableStats = [];
        // Seed met mappings van eerdere geslaagde runs (bron-tabel + bron-id → prod-id).
        $idMaps = $this->loadPriorIdMaps();
        $counts = [];
        $skipped = [];

        try {
            DB::beginTransaction();
            try {
                $runId = null;
                if (Schema::hasTable('import_runs')) {
                    // Nieuwe package-sha mag naast bestaande fingerprint; unique op sha.
                    $runId = (int) DB::table('import_runs')->insertGetId([
                        'package_sha256' => $packageSha256,
                        'source_fingerprint' => $sourceFingerprint,
                        'source_app_version' => (string) ($payload['app_version'] ?? ''),
                        'source_exported_at' => (string) ($payload['exported_at'] ?? ''),
                        'status' => 'pending',
                        'stats_json' => null,
                        'created_at' => now(),
                        'completed_at' => null,
                    ]);
                }

                foreach (self::EXPORT_TABLE_ORDER as $table) {
                    if (! isset($payload['tables'][$table]) || ! is_array($payload['tables'][$table])) {
                        continue;
                    }
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    $tableStats[$table] = match ($table) {
                        'users' => $this->mergeUsers(
                            $payload['tables'][$table],
                            $droppedColumns['users'] ?? [],
                            $idMaps,
                            $runId
                        ),
                        'geocode_cache' => $this->mergeGeocodeCache(
                            $payload['tables'][$table],
                            $droppedColumns['geocode_cache'] ?? [],
                            $idMaps,
                            $runId
                        ),
                        'page_views' => $this->mergePageViews(
                            $payload['tables'][$table],
                            $droppedColumns['page_views'] ?? [],
                            $idMaps,
                            $runId
                        ),
                        'blocks' => $this->mergeBlocks(
                            $payload['tables'][$table],
                            $droppedColumns['blocks'] ?? [],
                            $idMaps,
                            $runId
                        ),
                        'deelplekken' => $this->mergeDeelplekken(
                            $payload['tables'][$table],
                            $droppedColumns['deelplekken'] ?? [],
                            $idMaps,
                            $runId
                        ),
                        default => $this->mergeIdTable(
                            $table,
                            $payload['tables'][$table],
                            $droppedColumns[$table] ?? [],
                            $idMaps,
                            $runId
                        ),
                    };
                }

                foreach ($tableStats as $table => $stats) {
                    $counts[$table] = (int) $stats['toegevoegd'];
                    $skipTotal = (int) $stats['overgeslagen']
                        + (int) $stats['samengevoegd']
                        + (int) ($stats['al_aanwezig'] ?? 0);
                    if ($skipTotal > 0) {
                        $skipped[$table] = $skipTotal;
                    }
                }

                $statsPayload = [
                    'tables' => $tableStats,
                    'counts' => $counts,
                    'skipped' => $skipped,
                ];

                if ($runId !== null) {
                    DB::table('import_runs')->where('id', $runId)->update([
                        'status' => 'completed',
                        'stats_json' => json_encode($statsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'completed_at' => now(),
                    ]);
                }

                if ($dryRun) {
                    DB::rollBack();

                    return [
                        'ok' => true,
                        'dry_run' => true,
                        'already_imported' => false,
                        'tables' => $tableStats,
                        'counts' => $counts,
                        'skipped' => $skipped,
                        'dropped_columns' => $droppedColumns,
                        'schema' => $schema,
                        'package_sha256' => $packageSha256,
                        'source_fingerprint' => $sourceFingerprint,
                    ];
                }

                DB::commit();
            } catch (Throwable $inner) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                throw $inner;
            }
        } catch (Throwable $e) {
            $diagnosis = $this->safePdoDiagnosis($e);
            Log::warning('stekjes import failed', $diagnosis);
            report($e);

            return [
                'ok' => false,
                'dry_run' => $dryRun,
                'already_imported' => false,
                'tables' => $tableStats,
                'counts' => $counts,
                'skipped' => $skipped,
                'dropped_columns' => $droppedColumns,
                'schema' => $schema,
                'error' => $diagnosis['error_class'],
                'diagnosis' => $diagnosis,
                'package_sha256' => $packageSha256,
                'source_fingerprint' => $sourceFingerprint,
            ];
        }

        return [
            'ok' => true,
            'dry_run' => false,
            'already_imported' => false,
            'tables' => $tableStats,
            'counts' => $counts,
            'skipped' => $skipped,
            'dropped_columns' => $droppedColumns,
            'schema' => $schema,
            'package_sha256' => $packageSha256,
            'source_fingerprint' => $sourceFingerprint,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function importPackage(string $zipPath, bool $force = false, bool $dryRun = false): array
    {
        $tmp = sys_get_temp_dir().'/stekjes-import-'.bin2hex(random_bytes(8));
        if (! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
            throw new \RuntimeException('Kan import-temp niet aanmaken.');
        }

        try {
            $packageSha = hash_file('sha256', $zipPath);
            if ($packageSha === false) {
                throw new \RuntimeException('Kan package-sha niet berekenen.');
            }

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

            $result = $this->import($payload, $force, $dryRun, $packageSha);

            if ($result['ok'] && ! $dryRun && empty($result['already_imported'])) {
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
     * @return array{sqlstate: ?string, driver_code: ?int, table: ?string, column: ?string, error_class: string}
     */
    public function safePdoDiagnosis(Throwable $e): array
    {
        $sqlstate = null;
        $driverCode = null;
        $message = $e->getMessage();

        if ($e instanceof QueryException) {
            $info = $e->errorInfo;
            if (is_array($info)) {
                $sqlstate = isset($info[0]) && is_string($info[0]) ? $info[0] : null;
                $driverCode = isset($info[1]) && is_numeric($info[1]) ? (int) $info[1] : null;
            }
            if ($sqlstate === null && preg_match('/^SQLSTATE\[([A-Z0-9]+)\]/', $message, $m) === 1) {
                $sqlstate = $m[1];
            }
        } elseif ($e instanceof PDOException) {
            $info = $e->errorInfo ?? null;
            if (is_array($info)) {
                $sqlstate = isset($info[0]) && is_string($info[0]) ? $info[0] : null;
                $driverCode = isset($info[1]) && is_numeric($info[1]) ? (int) $info[1] : null;
            }
            if ($sqlstate === null && is_string($e->getCode()) && preg_match('/^[A-Z0-9]{5}$/', $e->getCode()) === 1) {
                $sqlstate = $e->getCode();
            }
        }

        $class = $e::class;
        $short = str_contains($class, '\\') ? substr($class, strrpos($class, '\\') + 1) : $class;

        return [
            'sqlstate' => $sqlstate,
            'driver_code' => $driverCode,
            'table' => $this->extractSafeTableName($message),
            'column' => $this->extractSafeColumnName($message),
            'error_class' => $short,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{tables: array<string, mixed>, missing_tables: list<string>}  $schema
     * @return array<string, mixed>
     */
    private function failureResult(bool $dryRun, array $schema, string $error): array
    {
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'already_imported' => false,
            'tables' => [],
            'counts' => [],
            'skipped' => [],
            'dropped_columns' => [],
            'schema' => $schema,
            'error' => $error,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, prod_na: int}
     */
    private function mergeUsers(array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table('users')->count();
        $stats = $this->baseStats($prodVoor, count($rows));
        $targetCols = Schema::getColumnListing('users');
        $idMaps['users'] = $idMaps['users'] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $oldId = isset($row['id']) ? (string) (int) $row['id'] : '';
            if ($email === '' || $oldId === '' || $oldId === '0') {
                throw new \RuntimeException('Ongeldige user-rij in export.');
            }

            // Eerdere run heeft deze bron-id al gemapt → geen merge/insert opnieuw.
            if (isset($idMaps['users'][$oldId])) {
                $this->storeIdMap($runId, 'users', $oldId, $idMaps['users'][$oldId]);
                $stats['al_aanwezig']++;
                continue;
            }

            // Kopie van eerdere ID-behoudende import (v0.24.12): zelfde id + inhoud.
            if ($this->isExactCopy('users', $row)) {
                $idMaps['users'][$oldId] = $oldId;
                $this->storeIdMap($runId, 'users', $oldId, $oldId);
                $stats['al_aanwezig']++;
                continue;
            }

            $existing = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing !== null) {
                $update = $this->fillEmptyUserFields((array) $existing, $row);
                if ($update !== []) {
                    DB::table('users')->where('id', $existing->id)->update($update);
                }
                $newId = (string) (int) $existing->id;
                $idMaps['users'][$oldId] = $newId;
                $this->storeIdMap($runId, 'users', $oldId, $newId);
                $stats['samengevoegd']++;
                continue;
            }

            $insert = $row;
            // Zelfde id maar andere inhoud = echte prod-rij: bron krijgt nieuw id.
            unset($insert['id']);
            // Password-hash letterlijk behouden (geen Eloquent casts).
            $newId = (string) (int) DB::table('users')->insertGetId($insert);
            $idMaps['users'][$oldId] = $newId;
            $this->storeIdMap($runId, 'users', $oldId, $newId);
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table('users')->count();

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function fillEmptyUserFields(array $existing, array $incoming): array
    {
        $update = [];
        // Nooit: id, email, password, created_at.
        $candidates = ['name', 'telefoon', 'locatie_label', 'lat', 'lon', 'country', 'pending_email', 'email_verified_at', 'remember_token', 'active', 'updated_at'];
        foreach ($candidates as $col) {
            if (! array_key_exists($col, $incoming)) {
                continue;
            }
            $cur = $existing[$col] ?? null;
            if ($cur === null || $cur === '') {
                $update[$col] = $incoming[$col];
            }
        }

        return $update;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, al_aanwezig: int, prod_na: int}
     */
    private function mergeGeocodeCache(array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table('geocode_cache')->count();
        $stats = $this->baseStats($prodVoor, count($rows));
        $targetCols = Schema::getColumnListing('geocode_cache');
        $idMaps['geocode_cache'] = $idMaps['geocode_cache'] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $key = (string) ($row['query_key'] ?? '');
            if ($key === '') {
                $stats['overgeslagen']++;
                continue;
            }
            if (isset($idMaps['geocode_cache'][$key])) {
                $this->storeIdMap($runId, 'geocode_cache', $key, $idMaps['geocode_cache'][$key]);
                $stats['al_aanwezig']++;
                continue;
            }
            $existing = DB::table('geocode_cache')->where('query_key', $key)->first();
            if ($existing !== null) {
                $idMaps['geocode_cache'][$key] = $key;
                $this->storeIdMap($runId, 'geocode_cache', $key, $key);
                if ($this->contentMatches('geocode_cache', $row, (array) $existing)) {
                    $stats['al_aanwezig']++;
                } else {
                    $stats['overgeslagen']++;
                }
                continue;
            }
            DB::table('geocode_cache')->insert($row);
            $idMaps['geocode_cache'][$key] = $key;
            $this->storeIdMap($runId, 'geocode_cache', $key, $key);
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table('geocode_cache')->count();

        return $stats;
    }

    /**
     * Pageviews: per date+path counts optellen (niet vervangen).
     * Bron-rij die al via import_id_maps (id of date|path) bekend is → nooit opnieuw optellen.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, al_aanwezig: int, prod_na: int, sum_voor: int, sum_na: int}
     */
    private function mergePageViews(array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table('page_views')->count();
        $sumVoor = (int) DB::table('page_views')->sum('count');
        $stats = $this->baseStats($prodVoor, count($rows));
        $stats['sum_voor'] = $sumVoor;
        $stats['sum_na'] = $sumVoor;
        $targetCols = Schema::getColumnListing('page_views');
        $idMaps['page_views'] = $idMaps['page_views'] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $date = (string) ($row['date'] ?? '');
            $path = (string) ($row['path'] ?? '');
            $add = (int) ($row['count'] ?? 0);
            if ($date === '' || $path === '') {
                $stats['overgeslagen']++;
                continue;
            }
            $pathKey = $date.'|'.$path;
            $idKey = isset($row['id']) ? (string) (int) $row['id'] : '';

            // Al verwerkt in eerdere run (bron-id of date|path).
            $priorNew = null;
            if ($idKey !== '' && isset($idMaps['page_views'][$idKey])) {
                $priorNew = $idMaps['page_views'][$idKey];
            } elseif (isset($idMaps['page_views'][$pathKey])) {
                $priorNew = $idMaps['page_views'][$pathKey];
            }
            if ($priorNew !== null) {
                $this->rememberPageViewKeys($idMaps, $runId, $idKey, $pathKey, $priorNew);
                $stats['al_aanwezig']++;
                continue;
            }

            // Exacte kopie (zelfde id + date/path/count): niet opnieuw optellen.
            if ($this->isExactCopy('page_views', $row)) {
                $newKey = (string) (int) $row['id'];
                $this->rememberPageViewKeys($idMaps, $runId, $idKey, $pathKey, $newKey);
                $stats['al_aanwezig']++;
                continue;
            }

            $existing = DB::table('page_views')->where('date', $date)->where('path', $path)->first();
            if ($existing !== null) {
                DB::table('page_views')->where('id', $existing->id)->update([
                    'count' => (int) $existing->count + $add,
                ]);
                $newKey = (string) (int) $existing->id;
                $this->rememberPageViewKeys($idMaps, $runId, $idKey, $pathKey, $newKey);
                $stats['samengevoegd']++;
                continue;
            }

            $insert = $row;
            unset($insert['id']);
            $newKey = (string) (int) DB::table('page_views')->insertGetId($insert);
            $this->rememberPageViewKeys($idMaps, $runId, $idKey, $pathKey, $newKey);
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table('page_views')->count();
        $stats['sum_na'] = (int) DB::table('page_views')->sum('count');

        return $stats;
    }

    /**
     * @param  array<string, array<string, string>>  $idMaps
     */
    private function rememberPageViewKeys(array &$idMaps, ?int $runId, string $idKey, string $pathKey, string $newKey): void
    {
        $idMaps['page_views'] = $idMaps['page_views'] ?? [];
        if ($idKey !== '' && $idKey !== '0') {
            $idMaps['page_views'][$idKey] = $newKey;
            $this->storeIdMap($runId, 'page_views', $idKey, $newKey);
        }
        $idMaps['page_views'][$pathKey] = $newKey;
        $this->storeIdMap($runId, 'page_views', $pathKey, $newKey);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, prod_na: int}
     */
    private function mergeBlocks(array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table('blocks')->count();
        $stats = $this->baseStats($prodVoor, count($rows));
        $targetCols = Schema::getColumnListing('blocks');
        $idMaps['blocks'] = $idMaps['blocks'] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $rawBlocker = isset($row['blocker_id']) ? (string) (int) $row['blocker_id'] : '';
            $rawBlocked = isset($row['blocked_id']) ? (string) (int) $row['blocked_id'] : '';
            $oldKey = $rawBlocker.':'.$rawBlocked;

            if ($oldKey !== ':' && isset($idMaps['blocks'][$oldKey])) {
                $this->storeIdMap($runId, 'blocks', $oldKey, $idMaps['blocks'][$oldKey]);
                $stats['al_aanwezig']++;
                continue;
            }

            // Pre-remap: exacte kopie met bron-user-ids (v0.24.12).
            if ($rawBlocker !== '' && $rawBlocked !== '') {
                $existingBlock = DB::table('blocks')
                    ->where('blocker_id', (int) $rawBlocker)
                    ->where('blocked_id', (int) $rawBlocked)
                    ->first();
                if ($existingBlock !== null && $this->contentMatches('blocks', $row, (array) $existingBlock)) {
                    $idMaps['blocks'][$oldKey] = $oldKey;
                    $this->storeIdMap($runId, 'blocks', $oldKey, $oldKey);
                    $stats['al_aanwezig']++;
                    continue;
                }
            }

            $row = $this->remapForeignKeys('blocks', $row, $idMaps);
            $blocker = $row['blocker_id'] ?? null;
            $blocked = $row['blocked_id'] ?? null;
            if ($blocker === null || $blocked === null) {
                $stats['overgeslagen']++;
                continue;
            }
            unset($row['_old_blocker'], $row['_old_blocked']);

            $newKey = $blocker.':'.$blocked;
            if (DB::table('blocks')->where('blocker_id', $blocker)->where('blocked_id', $blocked)->exists()) {
                $idMaps['blocks'][$oldKey] = $newKey;
                $this->storeIdMap($runId, 'blocks', $oldKey, $newKey);
                $stats['overgeslagen']++;
                continue;
            }
            DB::table('blocks')->insert($row);
            $idMaps['blocks'][$oldKey] = $newKey;
            $this->storeIdMap($runId, 'blocks', $oldKey, $newKey);
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table('blocks')->count();

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, prod_na: int}
     */
    private function mergeDeelplekken(array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table('deelplekken')->count();
        $stats = $this->baseStats($prodVoor, count($rows));
        $targetCols = Schema::getColumnListing('deelplekken');
        $idMaps['deelplekken'] = $idMaps['deelplekken'] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $oldId = isset($row['id']) ? (string) (int) $row['id'] : '';

            if ($oldId !== '' && isset($idMaps['deelplekken'][$oldId])) {
                $this->storeIdMap($runId, 'deelplekken', $oldId, $idMaps['deelplekken'][$oldId]);
                $stats['al_aanwezig']++;
                continue;
            }

            if ($oldId !== '' && $this->isExactCopy('deelplekken', $row)) {
                $idMaps['deelplekken'][$oldId] = $oldId;
                $this->storeIdMap($runId, 'deelplekken', $oldId, $oldId);
                $stats['al_aanwezig']++;
                continue;
            }

            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && DB::table('deelplekken')->where('slug', $slug)->exists()) {
                $existingId = (string) (int) DB::table('deelplekken')->where('slug', $slug)->value('id');
                if ($oldId !== '') {
                    $idMaps['deelplekken'][$oldId] = $existingId;
                    $this->storeIdMap($runId, 'deelplekken', $oldId, $existingId);
                }
                $stats['overgeslagen']++;
                continue;
            }
            unset($row['id']);
            $newId = (string) (int) DB::table('deelplekken')->insertGetId($row);
            if ($oldId !== '') {
                $idMaps['deelplekken'][$oldId] = $newId;
                $this->storeIdMap($runId, 'deelplekken', $oldId, $newId);
            }
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table('deelplekken')->count();

        return $stats;
    }

    /**
     * Generieke insert met nieuwe auto-increment IDs + FK-remap.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $dropCols
     * @param  array<string, array<string, string>>  $idMaps
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, prod_na: int}
     */
    private function mergeIdTable(string $table, array $rows, array $dropCols, array &$idMaps, ?int $runId): array
    {
        $prodVoor = (int) DB::table($table)->count();
        $stats = $this->baseStats($prodVoor, count($rows));
        $targetCols = Schema::getColumnListing($table);
        $idMaps[$table] = $idMaps[$table] ?? [];

        foreach ($rows as $row) {
            $row = $this->filterColumns($row, $targetCols, $dropCols);
            $oldId = isset($row['id']) ? (string) (int) $row['id'] : '';

            // Eerdere run heeft deze bron-id al gemapt → geen insert opnieuw.
            if ($oldId !== '' && isset($idMaps[$table][$oldId])) {
                $this->storeIdMap($runId, $table, $oldId, $idMaps[$table][$oldId]);
                $stats['al_aanwezig']++;
                continue;
            }

            // Vóór FK-remap: kopie met behouden bron-id's (v0.24.12-import).
            if ($oldId !== '' && $this->isExactCopy($table, $row)) {
                $idMaps[$table][$oldId] = $oldId;
                $this->storeIdMap($runId, $table, $oldId, $oldId);
                $stats['al_aanwezig']++;
                continue;
            }

            $row = $this->remapForeignKeys($table, $row, $idMaps);

            if ($table === 'conversations') {
                $exists = DB::table('conversations')
                    ->where('user_a_id', $row['user_a_id'] ?? null)
                    ->where('user_b_id', $row['user_b_id'] ?? null)
                    ->where('aanbod_id', $row['aanbod_id'] ?? null)
                    ->first();
                if ($exists !== null) {
                    if ($oldId !== '') {
                        $idMaps[$table][$oldId] = (string) (int) $exists->id;
                        $this->storeIdMap($runId, $table, $oldId, (string) (int) $exists->id);
                    }
                    $stats['overgeslagen']++;
                    continue;
                }
            }

            if ($table === 'aanbod' && ! empty($row['renew_token'])) {
                if (DB::table('aanbod')->where('renew_token', $row['renew_token'])->exists()) {
                    $row['renew_token'] = null;
                    $row['renew_token_expires'] = null;
                }
            }

            if ($table === 'milestones_sent') {
                $uid = $row['user_id'] ?? null;
                $milestone = $row['milestone'] ?? null;
                if ($uid !== null && $milestone !== null
                    && DB::table('milestones_sent')->where('user_id', $uid)->where('milestone', $milestone)->exists()) {
                    if ($oldId !== '') {
                        $existingId = (string) (int) DB::table('milestones_sent')
                            ->where('user_id', $uid)->where('milestone', $milestone)->value('id');
                        $idMaps[$table][$oldId] = $existingId;
                        $this->storeIdMap($runId, $table, $oldId, $existingId);
                    }
                    $stats['overgeslagen']++;
                    continue;
                }
            }

            // Zelfde id maar andere inhoud → prod behouden, bron nieuw id.
            unset($row['id']);
            $newId = (string) (int) DB::table($table)->insertGetId($row);
            if ($oldId !== '') {
                $idMaps[$table][$oldId] = $newId;
                $this->storeIdMap($runId, $table, $oldId, $newId);
            }
            $stats['toegevoegd']++;
        }

        $stats['prod_na'] = (int) DB::table($table)->count();

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, string>>  $idMaps
     * @return array<string, mixed>
     */
    private function remapForeignKeys(string $table, array $row, array $idMaps): array
    {
        $map = match ($table) {
            'aanbod', 'feedback', 'mail_log', 'milestones_sent' => ['user_id' => 'users'],
            'aanbod_fotos' => ['aanbod_id' => 'aanbod'],
            'ervaringen' => [
                'user_id' => 'users',
                'aanbod_id' => 'aanbod',
                'conversation_id' => 'conversations',
            ],
            'conversations' => [
                'aanbod_id' => 'aanbod',
                'user_a_id' => 'users',
                'user_b_id' => 'users',
            ],
            'messages' => [
                'conversation_id' => 'conversations',
                'sender_id' => 'users',
            ],
            'blocks' => [
                'blocker_id' => 'users',
                'blocked_id' => 'users',
            ],
            'reports' => [
                'reporter_id' => 'users',
                'reported_user_id' => 'users',
                'conversation_id' => 'conversations',
            ],
            default => [],
        };

        foreach ($map as $col => $refTable) {
            if (! array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') {
                continue;
            }
            $old = (string) (int) $row[$col];
            if ($table === 'blocks' && $col === 'blocker_id') {
                $row['_old_blocker'] = $old;
            }
            if ($table === 'blocks' && $col === 'blocked_id') {
                $row['_old_blocked'] = $old;
            }
            if (isset($idMaps[$refTable][$old])) {
                $row[$col] = (int) $idMaps[$refTable][$old];
            } else {
                // Geen mapping: laat waarde staan (kan falen op FK — bewust zichtbaar in diagnose).
                $row[$col] = (int) $old;
            }
        }

        return $row;
    }

    private function storeIdMap(?int $runId, string $table, string $oldKey, string $newKey): void
    {
        if ($runId === null || ! Schema::hasTable('import_id_maps')) {
            return;
        }
        $old = mb_substr($oldKey, 0, 191);
        $new = mb_substr($newKey, 0, 191);
        // Zelfde run +zelfde old_key (bv. page_views id én date|path pad) → upsert.
        DB::table('import_id_maps')->updateOrInsert(
            [
                'import_run_id' => $runId,
                'table_name' => $table,
                'old_key' => $old,
            ],
            [
                'new_key' => $new,
                'created_at' => now(),
            ]
        );
    }

    /**
     * Alle bron→prod mappings van eerdere geslaagde imports (idempotentie over export-sha heen).
     *
     * @return array<string, array<string, string>>
     */
    private function loadPriorIdMaps(): array
    {
        $maps = [];
        if (! Schema::hasTable('import_id_maps') || ! Schema::hasTable('import_runs')) {
            return $maps;
        }

        $rows = DB::table('import_id_maps as m')
            ->join('import_runs as r', 'r.id', '=', 'm.import_run_id')
            ->where('r.status', 'completed')
            ->orderBy('m.id')
            ->get(['m.table_name', 'm.old_key', 'm.new_key']);

        foreach ($rows as $row) {
            $maps[(string) $row->table_name][(string) $row->old_key] = (string) $row->new_key;
        }

        return $maps;
    }

    /**
     * @return array{prod_voor: int, bron: int, toegevoegd: int, samengevoegd: int, overgeslagen: int, al_aanwezig: int, prod_na: int}
     */
    private function baseStats(int $prodVoor, int $bron): array
    {
        return [
            'prod_voor' => $prodVoor,
            'bron' => $bron,
            'toegevoegd' => 0,
            'samengevoegd' => 0,
            'overgeslagen' => 0,
            'al_aanwezig' => 0,
            'prod_na' => $prodVoor,
        ];
    }

    /**
     * Sleutelvelden + created_at (of equivalent) om een v0.24.12-kopie te herkennen:
     * zelfde id én dezelfde inhoud → al_aanwezig (niet opnieuw invoegen/optellen).
     *
     * @return list<string>
     */
    private function contentKeyFields(string $table): array
    {
        return match ($table) {
            'users' => ['email', 'created_at'],
            'aanbod' => ['user_id', 'type', 'created_at'],
            'aanbod_fotos' => ['aanbod_id', 'path', 'created_at'],
            'conversations' => ['user_a_id', 'user_b_id', 'aanbod_id', 'created_at'],
            'messages' => ['conversation_id', 'sender_id', 'created_at'],
            'blocks' => ['blocker_id', 'blocked_id', 'created_at'],
            'reports' => ['reporter_id', 'reported_user_id', 'created_at'],
            'feedback' => ['user_id', 'page_path', 'created_at'],
            'mail_log' => ['user_id', 'type', 'status', 'created_at'],
            'geocode_cache' => ['query_key', 'lat', 'lon', 'cached_at'],
            'partner_markers' => ['naam', 'lat', 'lon', 'created_at'],
            'deelplekken' => ['slug', 'created_at'],
            'ervaringen' => ['user_id', 'created_at'],
            'milestones_sent' => ['user_id', 'milestone'],
            'evenementen' => ['titel', 'start_datum', 'created_at'],
            'page_views' => ['date', 'path', 'count'],
            default => ['created_at'],
        };
    }

    /**
     * True als prod al een rij met dezelfde id én dezelfde sleutelinhoud heeft
     * (typisch een kopie van de mislukte v0.24.12-import die IDs behield).
     *
     * @param  array<string, mixed>  $sourceRow
     */
    private function isExactCopy(string $table, array $sourceRow): bool
    {
        if (! isset($sourceRow['id']) || ! Schema::hasColumn($table, 'id')) {
            return false;
        }
        $existing = DB::table($table)->where('id', $sourceRow['id'])->first();
        if ($existing === null) {
            return false;
        }

        return $this->contentMatches($table, $sourceRow, (array) $existing);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $existing
     */
    private function contentMatches(string $table, array $source, array $existing): bool
    {
        foreach ($this->contentKeyFields($table) as $field) {
            if (! array_key_exists($field, $source)) {
                return false;
            }
            if (! $this->valuesEqual($source[$field], $existing[$field] ?? null, $field)) {
                return false;
            }
        }

        return true;
    }

    private function valuesEqual(mixed $a, mixed $b, string $field): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        if ($field === 'email') {
            return strtolower(trim((string) $a)) === strtolower(trim((string) $b));
        }

        if (in_array($field, ['created_at', 'cached_at', 'updated_at', 'approved_at', 'email_verified_at', 'expires_at'], true)
            || str_ends_with($field, '_at')) {
            return $this->normalizeTimestamp($a) === $this->normalizeTimestamp($b);
        }

        if (in_array($field, ['lat', 'lon', 'lng'], true)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        if (is_bool($a) || is_bool($b)) {
            return (int) $a === (int) $b;
        }

        if (is_numeric($a) && is_numeric($b) && ! is_string($a) && ! is_string($b)) {
            return (string) $a === (string) $b || (float) $a === (float) $b;
        }

        // Datums / strings: normaliseer whitespace.
        return trim((string) $a) === trim((string) $b);
    }

    private function normalizeTimestamp(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $s = trim((string) $value);
        if ($s === '') {
            return '';
        }
        // ISO → MySQL-achtig; strip timezone-suffix voor vergelijking.
        $s = str_replace('T', ' ', $s);
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $s, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $s, $m) === 1) {
            return $m[1];
        }

        return $s;
    }

    /**
     * Package-fingerprint inclusief exported_at (uniek per export-zip).
     *
     * @param  array<string, mixed>  $payload
     */
    private function fingerprintPayload(array $payload): string
    {
        $canonical = json_encode([
            'app_version' => $payload['app_version'] ?? '',
            'exported_at' => $payload['exported_at'] ?? '',
            'manifest' => $payload['manifest'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical === false ? 'invalid' : $canonical);
    }

    /**
     * Bron-inhoud fingerprint: manifest table hashes (+ files tar), zonder exported_at.
     * Zelfde bron opnieuw geëxporteerd → dezelfde fingerprint.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sourceFingerprint(array $payload): string
    {
        $tables = $payload['manifest']['tables'] ?? [];
        if (! is_array($tables)) {
            $tables = [];
        }
        ksort($tables);
        $canonicalTables = [];
        foreach ($tables as $name => $meta) {
            $meta = is_array($meta) ? $meta : [];
            $canonicalTables[(string) $name] = [
                'count' => (int) ($meta['count'] ?? 0),
                'sha256' => (string) ($meta['sha256'] ?? ''),
            ];
        }

        $encoded = json_encode([
            'format' => $payload['format'] ?? self::FORMAT,
            'format_version' => (int) ($payload['format_version'] ?? self::FORMAT_VERSION),
            'tables' => $canonicalTables,
            'files_tar_sha256' => $payload['manifest']['files_tar_sha256'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded === false ? 'invalid' : $encoded);
    }

    private function extractSafeTableName(string $message): ?string
    {
        if (preg_match('/\bINSERT INTO `([a-z0-9_]+)`/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\bUPDATE `([a-z0-9_]+)`/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\bTable [\'`](?:[a-z0-9_]+\.)?([a-z0-9_]+)[\'`]/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/CONSTRAINT `([a-z0-9_]+)_/i', $message, $c) === 1) {
            $prefix = $c[1];
            foreach (self::EXPORT_TABLE_ORDER as $table) {
                if (str_starts_with($prefix, $table)) {
                    return $table;
                }
            }
        }
        if (preg_match('/\bfor key [\'`]([a-z0-9_]+)\./i', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractSafeColumnName(string $message): ?string
    {
        if (preg_match('/\bUnknown column [\'`]([a-z0-9_]+)[\'`]/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\bField [\'`]([a-z0-9_]+)[\'`] doesn\'t have a default value/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\bColumn [\'`]([a-z0-9_]+)[\'`]/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/FOREIGN KEY \(`([a-z0-9_]+)`\)/i', $message, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\bfor key [\'`](?:[a-z0-9_]+\.)?([a-z0-9_]+)[\'`]/i', $message, $m) === 1) {
            $key = $m[1];
            if (str_ends_with($key, '_unique')) {
                $key = substr($key, 0, -7);
            }
            if (str_ends_with($key, '_foreign')) {
                $key = substr($key, 0, -8);
            }
            if (preg_match('/^[a-z0-9]+_([a-z0-9_]+)$/', $key, $p) === 1) {
                return $p[1];
            }

            return $key;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchExportRows(string $table): array
    {
        $query = DB::table($table);

        if ($table === 'deelplekken') {
            if (! Schema::hasColumn('deelplekken', 'source')) {
                return [];
            }
            $query->where('source', '!=', 'seed');
        }

        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        } elseif ($table === 'geocode_cache') {
            $query->orderBy('query_key');
        } elseif ($table === 'page_views') {
            $query->orderBy('date')->orderBy('path');
        }

        return $query->get()->map(function ($row) {
            return $this->normalizeRow((array) $row);
        })->all();
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
        foreach ($row as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            // Private aanbod-fotos disk (kolommen path / thumb_path).
            if (in_array((string) $key, ['path', 'thumb_path'], true)
                && preg_match('#^[A-Za-z0-9_./-]+$#', $value) === 1
                && ! str_contains($value, '..')) {
                $paths[] = '__disk__/aanbod-fotos/'.ltrim($value, '/');
            }
            if (preg_match_all('#(?<![A-Za-z0-9_./-])((?:photos|uploads|ervaringen)/[A-Za-z0-9_./-]+)#', $value, $m) > 0) {
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
     * @param  list<string>  $relativePaths  public-relatief óf `__disk__/aanbod-fotos/...`
     */
    private function writeReferencedFilesTar(array $relativePaths, string $tarPath): int
    {
        $publicRoot = storage_path('app/public');
        $aanbodRoot = storage_path('app/aanbod-fotos');
        $staging = sys_get_temp_dir().'/stekjes-files-'.bin2hex(random_bytes(6));
        mkdir($staging, 0700, true);
        $count = 0;

        try {
            foreach ($relativePaths as $rel) {
                $rel = ltrim(str_replace(['\\'], ['/'], $rel), '/');
                if ($rel === '' || str_contains($rel, '..')) {
                    continue;
                }
                if (str_starts_with($rel, '__disk__/aanbod-fotos/')) {
                    $diskRel = substr($rel, strlen('__disk__/aanbod-fotos/'));
                    $src = $aanbodRoot.'/'.$diskRel;
                    $dest = $staging.'/__disk__/aanbod-fotos/'.$diskRel;
                } else {
                    $src = $publicRoot.'/'.$rel;
                    $dest = $staging.'/'.$rel;
                }
                if (! is_file($src)) {
                    continue;
                }
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

        $tmp = sys_get_temp_dir().'/stekjes-restore-'.bin2hex(random_bytes(6));
        mkdir($tmp, 0700, true);
        try {
            $result = Process::timeout(120)->run(['tar', '-xf', $tarPath, '-C', $tmp]);
            if ($result->failed()) {
                throw new \RuntimeException('tar extract mislukt.');
            }

            $publicRoot = storage_path('app/public');
            $aanbodRoot = storage_path('app/aanbod-fotos');
            if (! is_dir($publicRoot) && ! mkdir($publicRoot, 0755, true) && ! is_dir($publicRoot)) {
                throw new \RuntimeException('Kan storage/app/public niet aanmaken.');
            }
            if (! is_dir($aanbodRoot) && ! mkdir($aanbodRoot, 0755, true) && ! is_dir($aanbodRoot)) {
                throw new \RuntimeException('Kan storage/app/aanbod-fotos niet aanmaken.');
            }

            $count = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($tmp))), '/');
                if ($rel === '' || str_contains($rel, '..')) {
                    continue;
                }
                if (str_starts_with($rel, '__disk__/aanbod-fotos/')) {
                    $dest = $aanbodRoot.'/'.substr($rel, strlen('__disk__/aanbod-fotos/'));
                } else {
                    $dest = $publicRoot.'/'.$rel;
                }
                $dir = dirname($dest);
                if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    continue;
                }
                if (! is_file($dest)) {
                    copy($file->getPathname(), $dest);
                }
                $count++;
            }

            return $count;
        } finally {
            $this->removeDirectory($tmp);
        }
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
