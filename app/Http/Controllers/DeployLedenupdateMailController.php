<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Noshell: draai gp:ledenupdate-mail via Bearer DEPLOY_TOKEN.
 * mode=dry-run|ids|send (+ id bij send). Response: artisan-output (geen e-mailadressen).
 */
class DeployLedenupdateMailController extends Controller
{
    /** Ruim genoeg voor send-loop (één POST per lid + dry-run/ids). */
    private const RATE_MAX = 400;

    private const RATE_DECAY = 3600;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->endpointEnabled()) {
            return response()->json(['ok' => false, 'error' => 'disabled'], 403);
        }

        $expected = (string) config('stekjes.deploy_token', '');
        $given = (string) ($request->bearerToken() ?? '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $throttleKey = 'deploy-ledenupdate-mail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        $mode = strtolower(trim((string) $request->input('mode', 'dry-run')));
        if (! in_array($mode, ['dry-run', 'ids', 'send'], true)) {
            return response()->json(['ok' => false, 'error' => 'invalid_mode'], 422);
        }

        $args = match ($mode) {
            'ids' => ['--ids' => true],
            'send' => [
                '--send' => true,
                '--id' => (string) $request->input('id', ''),
            ],
            default => ['--dry-run' => true],
        };

        if ($mode === 'send' && (! ctype_digit((string) $request->input('id', '')) || (int) $request->input('id') < 1)) {
            return response()->json(['ok' => false, 'error' => 'id_required'], 422);
        }

        try {
            $exit = Artisan::call('gp:ledenupdate-mail', $args);
            $output = Artisan::output();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'artisan_failed'], 500);
        }

        if ($exit !== 0) {
            return response()->json([
                'ok' => false,
                'error' => 'command_failed',
                'mode' => $mode,
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'at' => now()->toIso8601String(),
            'output' => $output,
        ]);
    }

    private function endpointEnabled(): bool
    {
        if (app()->environment('staging')) {
            return true;
        }

        return (bool) config('stekjes.deploy_endpoint_enabled', false);
    }
}
