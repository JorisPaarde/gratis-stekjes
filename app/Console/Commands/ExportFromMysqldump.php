<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StekjesDataMigrator;
use Illuminate\Console\Command;

/**
 * Fallback: zet een mysqldump (alleen data-tabellen) om naar stekjes-export data.json.
 * Gebruikt wanneer de oude staging nog geen stekjes:export-data heeft.
 * Logt alleen tabelnamen + tellingen.
 */
class ExportFromMysqldump extends Command
{
    protected $signature = 'stekjes:export-from-mysqldump
                            {sql : Pad naar .sql dump}
                            {--out= : Doelmap voor package}';

    protected $description = 'Converteer mysqldump van user-tabellen naar stekjes-export package';

    public function handle(StekjesDataMigrator $migrator): int
    {
        $sqlPath = (string) $this->argument('sql');
        if (! is_readable($sqlPath)) {
            $this->error('SQL-bestand niet leesbaar.');

            return self::FAILURE;
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            $this->error('Kan SQL niet lezen.');

            return self::FAILURE;
        }

        $tables = $this->parseInserts($sql);
        $payload = [
            'format' => StekjesDataMigrator::FORMAT,
            'format_version' => StekjesDataMigrator::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_version' => (string) config('site.version'),
            'manifest' => ['tables' => [], 'files_count' => 0, 'files_tar_sha256' => null],
            'tables' => [],
        ];

        foreach (StekjesDataMigrator::EXPORT_TABLE_ORDER as $table) {
            $rows = $tables[$table] ?? [];
            if ($table === 'deelplekken') {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $r): bool => ($r['source'] ?? 'seed') !== 'seed'
                ));
            }
            $encoded = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payload['tables'][$table] = $rows;
            $payload['manifest']['tables'][$table] = [
                'count' => count($rows),
                'sha256' => hash('sha256', $encoded === false ? '[]' : $encoded),
            ];
        }

        $out = $this->option('out');
        if (! is_string($out) || trim($out) === '') {
            $out = storage_path('app/stekjes-export-from-sql-'.gmdate('Ymd\THis\Z'));
        }
        if (! is_dir($out) && ! mkdir($out, 0700, true) && ! is_dir($out)) {
            $this->error('Kan output-map niet aanmaken.');

            return self::FAILURE;
        }

        $dataJson = $out.'/data.json';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($dataJson, $json) === false) {
            $this->error('Schrijven data.json mislukt.');

            return self::FAILURE;
        }
        chmod($dataJson, 0600);

        $zipPath = $out.'/stekjes-export.zip';
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error('Zip openen mislukt.');

            return self::FAILURE;
        }
        $zip->addFile($dataJson, 'data.json');
        $zip->close();
        chmod($zipPath, 0600);

        $this->info('Convert OK');
        $this->line('path='.$zipPath);
        foreach ($payload['manifest']['tables'] as $table => $meta) {
            $this->line($table.'='.$meta['count']);
        }

        return self::SUCCESS;
    }

    /**
     * Parseer eenvoudige mysqldump INSERT INTO `table` VALUES (...),(...);
     * Geen PII naar stdout.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function parseInserts(string $sql): array
    {
        $result = [];
        // Match: INSERT INTO `table` (`col`, ...) VALUES (...);
        $pattern = '/INSERT\s+INTO\s+`([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+?);/is';
        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $m) {
                $table = $m[1];
                if (in_array($table, StekjesDataMigrator::EXCLUDED_TABLES, true)) {
                    continue;
                }
                if (! in_array($table, StekjesDataMigrator::EXPORT_TABLE_ORDER, true)) {
                    continue;
                }
                $cols = array_map(
                    static fn (string $c): string => trim($c, " \t\n\r\0\x0B`"),
                    explode(',', $m[2])
                );
                $valuesBlob = $m[3];
                foreach ($this->splitValueTuples($valuesBlob) as $tuple) {
                    $vals = $this->parseTupleValues($tuple);
                    if (count($vals) !== count($cols)) {
                        continue;
                    }
                    $row = [];
                    foreach ($cols as $i => $col) {
                        $row[$col] = $vals[$i];
                    }
                    $result[$table][] = $row;
                }
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function splitValueTuples(string $blob): array
    {
        $tuples = [];
        $len = strlen($blob);
        $depth = 0;
        $inString = false;
        $escape = false;
        $start = null;

        for ($i = 0; $i < $len; $i++) {
            $ch = $blob[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === "'") {
                    $inString = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inString = true;
                continue;
            }
            if ($ch === '(') {
                if ($depth === 0) {
                    $start = $i + 1;
                }
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $tuples[] = substr($blob, $start, $i - $start);
                    $start = null;
                }
            }
        }

        return $tuples;
    }

    /**
     * @return list<mixed>
     */
    private function parseTupleValues(string $tuple): array
    {
        $vals = [];
        $len = strlen($tuple);
        $inString = false;
        $escape = false;
        $buf = '';
        $started = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];
            if ($inString) {
                if ($escape) {
                    $buf .= $ch;
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === "'") {
                    // '' escape in SQL
                    if ($i + 1 < $len && $tuple[$i + 1] === "'") {
                        $buf .= "'";
                        $i++;
                        continue;
                    }
                    $inString = false;
                    $vals[] = $buf;
                    $buf = '';
                    $started = false;
                    continue;
                }
                $buf .= $ch;
                continue;
            }

            if ($ch === "'") {
                $inString = true;
                $buf = '';
                $started = true;
                continue;
            }
            if ($ch === ',') {
                if ($started) {
                    $vals[] = $this->castBare($buf);
                    $buf = '';
                    $started = false;
                }
                continue;
            }
            if (ctype_space($ch)) {
                continue;
            }
            $buf .= $ch;
            $started = true;
        }
        if ($started) {
            $vals[] = $this->castBare($buf);
        }

        return $vals;
    }

    private function castBare(string $raw): mixed
    {
        $raw = trim($raw);
        if (strcasecmp($raw, 'NULL') === 0) {
            return null;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }
}
