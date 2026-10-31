<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EncryptedBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Noshell-vriendelijke backup-trigger + download van versleutelde blobs.
 * Auth: zelfde X-Cron-Token als /cron. Geen sleutel in responses of logs.
 */
class BackupCronController extends Controller
{
    public function __invoke(Request $request, EncryptedBackupService $backups): Response|JsonResponse|BinaryFileResponse
    {
        $expected = (string) config('stekjes.cron_token', '');
        $given = (string) $request->header('X-Cron-Token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $download = (string) $request->query('download', '');
        if ($download !== '') {
            return $this->download($backups, $download);
        }

        if ($request->query->has('list')) {
            return response()->json([
                'ok' => true,
                'version' => config('site.version'),
                'files' => $backups->listBackups(),
                'retain_days' => $backups->retainDays(),
                'at' => now()->toIso8601String(),
            ]);
        }

        try {
            $result = $backups->run(withStorage: ! $request->boolean('no_storage'));
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e::class,
                'message' => $this->safeMessage($e->getMessage()),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'version' => config('site.version'),
            'db' => [
                'basename' => $result['db']['basename'],
                'bytes' => $result['db']['bytes'],
            ],
            'storage' => $result['storage'] === null ? null : [
                'basename' => $result['storage']['basename'],
                'bytes' => $result['storage']['bytes'],
            ],
            'pruned' => $result['pruned'],
            'retain_days' => $result['retain_days'],
            'at' => now()->toIso8601String(),
        ]);
    }

    private function download(EncryptedBackupService $backups, string $which): Response|BinaryFileResponse
    {
        $basename = match ($which) {
            'db', 'latest-db', 'latest' => $backups->latestBasename('db'),
            'storage', 'latest-storage' => $backups->latestBasename('storage'),
            default => $which,
        };

        if ($basename === null || $basename === '') {
            return response('Not Found', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $path = $backups->resolveDownloadBasename($basename);
        if ($path === null) {
            return response('Not Found', 404)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeMessage(string $message): string
    {
        $message = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $message);
        $message = (string) preg_replace('/\b(base64:[A-Za-z0-9+\/=]+)\b/', '[redacted]', $message);

        return mb_substr($message, 0, 200);
    }
}
