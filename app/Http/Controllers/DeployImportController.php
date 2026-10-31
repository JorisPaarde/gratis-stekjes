<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StekjesDataMigrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Token-protected import voor dataverhuis (staging.stekjes → prod).
 * Alleen actief bij DEPLOY_IMPORT_ENABLED=true. Response: tabelnamen + counts, geen PII.
 */
class DeployImportController extends Controller
{
    private const RATE_MAX = 10;

    private const RATE_DECAY = 3600;

    private const CHUNK_DIR = 'stekjes-import-uploads';

    public function init(Request $request): JsonResponse
    {
        $denied = $this->authorize($request);
        if ($denied !== null) {
            return $denied;
        }

        $totalSize = (int) $request->input('total_size', 0);
        $totalSha = (string) $request->input('total_sha256', '');
        $chunkCount = (int) $request->input('chunk_count', 0);

        if ($totalSize <= 0 || $chunkCount <= 0 || ! preg_match('/^[a-f0-9]{64}$/', $totalSha)) {
            return response()->json(['ok' => false, 'error' => 'invalid_meta'], 422);
        }

        // Max 100 MiB — ~7 accounts + foto's; voorkomt disk-filling.
        if ($totalSize > 100 * 1024 * 1024) {
            return response()->json(['ok' => false, 'error' => 'too_large'], 413);
        }

        $uploadId = bin2hex(random_bytes(16));
        $dir = storage_path('app/'.self::CHUNK_DIR.'/'.$uploadId);
        if (! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return response()->json(['ok' => false, 'error' => 'mkdir_failed'], 500);
        }

        $meta = [
            'total_size' => $totalSize,
            'total_sha256' => $totalSha,
            'chunk_count' => $chunkCount,
            'received' => [],
            'created_at' => now()->toIso8601String(),
        ];
        file_put_contents($dir.'/meta.json', json_encode($meta));
        chmod($dir.'/meta.json', 0600);

        return response()->json([
            'ok' => true,
            'upload_id' => $uploadId,
        ]);
    }

    public function chunk(Request $request): JsonResponse
    {
        $denied = $this->authorize($request);
        if ($denied !== null) {
            return $denied;
        }

        $uploadId = (string) $request->input('upload_id', '');
        $chunkIndex = (int) $request->input('chunk_index', -1);
        $chunkSha = (string) $request->input('chunk_sha256', '');

        if (! preg_match('/^[a-f0-9]{32}$/', $uploadId) || $chunkIndex < 0) {
            return response()->json(['ok' => false, 'error' => 'invalid_meta'], 422);
        }

        $dir = storage_path('app/'.self::CHUNK_DIR.'/'.$uploadId);
        $metaPath = $dir.'/meta.json';
        if (! is_readable($metaPath)) {
            return response()->json(['ok' => false, 'error' => 'unknown_upload'], 404);
        }

        /** @var array<string, mixed> $meta */
        $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);
        if ($chunkIndex >= (int) $meta['chunk_count']) {
            return response()->json(['ok' => false, 'error' => 'chunk_oob'], 422);
        }

        $file = $request->file('chunk');
        if ($file === null || ! $file->isValid()) {
            return response()->json(['ok' => false, 'error' => 'missing_chunk'], 422);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return response()->json(['ok' => false, 'error' => 'read_failed'], 500);
        }
        if ($chunkSha !== '' && hash('sha256', $bytes) !== $chunkSha) {
            return response()->json(['ok' => false, 'error' => 'chunk_sha_mismatch'], 422);
        }

        $chunkPath = $dir.'/chunk_'.str_pad((string) $chunkIndex, 5, '0', STR_PAD_LEFT);
        file_put_contents($chunkPath, $bytes);
        chmod($chunkPath, 0600);

        $received = $meta['received'] ?? [];
        if (! is_array($received)) {
            $received = [];
        }
        $received[$chunkIndex] = true;
        $meta['received'] = $received;
        file_put_contents($metaPath, json_encode($meta));

        return response()->json([
            'ok' => true,
            'chunk_index' => $chunkIndex,
            'received' => count($received),
        ]);
    }

    public function import(Request $request, StekjesDataMigrator $migrator): JsonResponse
    {
        $denied = $this->authorize($request);
        if ($denied !== null) {
            return $denied;
        }

        $force = filter_var($request->input('force', false), FILTER_VALIDATE_BOOL);
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOL);
        $uploadId = (string) $request->input('upload_id', '');

        $packagePath = null;
        $cleanupDir = null;

        try {
            if ($uploadId !== '') {
                if (! preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
                    return response()->json(['ok' => false, 'error' => 'invalid_meta'], 422);
                }
                $dir = storage_path('app/'.self::CHUNK_DIR.'/'.$uploadId);
                $cleanupDir = $dir;
                $packagePath = $this->assembleUpload($dir);
            } elseif ($request->hasFile('package')) {
                $file = $request->file('package');
                if ($file === null || ! $file->isValid()) {
                    return response()->json(['ok' => false, 'error' => 'missing_package'], 422);
                }
                $tmp = storage_path('app/'.self::CHUNK_DIR.'/direct-'.bin2hex(random_bytes(8)));
                mkdir($tmp, 0700, true);
                $cleanupDir = $tmp;
                $packagePath = $tmp.'/package.zip';
                $file->move($tmp, 'package.zip');
            } else {
                return response()->json(['ok' => false, 'error' => 'missing_package'], 422);
            }

            Artisan::call('migrate', ['--force' => true]);

            $result = $migrator->importPackage($packagePath, $force, $dryRun);

            // already_imported is succes (idempotent); andere !ok → 409.
            $already = ! empty($result['already_imported']);
            $status = ($result['ok'] || $already) ? 200 : 409;
            // Per-tabel merge-stats + veilige diagnose; nooit row-data/PII.
            $body = [
                'ok' => (bool) $result['ok'],
                'dry_run' => $result['dry_run'],
                'already_imported' => $already,
                'tables' => $result['tables'] ?? [],
                'counts' => $result['counts'] ?? [],
                'skipped' => $result['skipped'] ?? [],
                'dropped_columns' => $result['dropped_columns'] ?? [],
                'missing_tables' => $result['schema']['missing_tables'] ?? [],
                'files_restored' => $result['files_restored'] ?? null,
                'error' => $result['error'] ?? null,
            ];
            if (isset($result['diagnosis']) && is_array($result['diagnosis'])) {
                $body['diagnosis'] = [
                    'sqlstate' => $result['diagnosis']['sqlstate'] ?? null,
                    'driver_code' => $result['diagnosis']['driver_code'] ?? null,
                    'table' => $result['diagnosis']['table'] ?? null,
                    'column' => $result['diagnosis']['column'] ?? null,
                    'error_class' => $result['diagnosis']['error_class'] ?? null,
                ];
            }

            return response()->json($body, $status);
        } catch (Throwable $e) {
            $diagnosis = $migrator->safePdoDiagnosis($e);
            Log::warning('stekjes deploy import failed', $diagnosis);
            report($e);
            $msg = $e->getMessage();
            $safe = in_array($msg, ['email_id_mismatch', 'user_id_conflict', 'Ongeldig export-formaat.', 'Onbekende format_version.', 'unknown_upload', 'incomplete_upload', 'total_sha_mismatch'], true)
                ? $msg
                : ($diagnosis['error_class'] ?? 'error');

            return response()->json([
                'ok' => false,
                'error' => $safe,
                'diagnosis' => $diagnosis,
            ], 500);
        } finally {
            if ($cleanupDir !== null) {
                $this->removeDirectory($cleanupDir);
            }
        }
    }

    private function authorize(Request $request): ?JsonResponse
    {
        if (! (bool) config('stekjes.deploy_import_enabled', false)) {
            return response()->json(['ok' => false, 'error' => 'disabled'], 403);
        }

        $expected = (string) config('stekjes.deploy_token', '');
        $given = (string) ($request->bearerToken() ?? '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $throttleKey = 'deploy-import:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        return null;
    }

    private function assembleUpload(string $dir): string
    {
        $metaPath = $dir.'/meta.json';
        if (! is_readable($metaPath)) {
            throw new \RuntimeException('unknown_upload');
        }
        /** @var array<string, mixed> $meta */
        $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);
        $chunkCount = (int) $meta['chunk_count'];
        $received = $meta['received'] ?? [];
        if (! is_array($received) || count($received) !== $chunkCount) {
            throw new \RuntimeException('incomplete_upload');
        }

        $out = $dir.'/package.zip';
        $fh = fopen($out, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('assemble_failed');
        }
        try {
            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkPath = $dir.'/chunk_'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
                if (! is_readable($chunkPath)) {
                    throw new \RuntimeException('incomplete_upload');
                }
                $bytes = file_get_contents($chunkPath);
                if ($bytes === false) {
                    throw new \RuntimeException('assemble_failed');
                }
                fwrite($fh, $bytes);
            }
        } finally {
            fclose($fh);
        }
        chmod($out, 0600);

        $sha = hash_file('sha256', $out);
        if ($sha !== $meta['total_sha256']) {
            throw new \RuntimeException('total_sha_mismatch');
        }

        return $out;
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
