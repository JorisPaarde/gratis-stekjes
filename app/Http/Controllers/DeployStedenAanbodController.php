<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Noshell: draai gp:steden-aanbod via Bearer DEPLOY_TOKEN (read-only).
 * Response bevat artisan-output (plaatsnamen + aantallen, geen PII).
 */
class DeployStedenAanbodController extends Controller
{
    private const RATE_MAX = 20;

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

        $throttleKey = 'deploy-steden-aanbod:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        $args = [];
        if ($request->boolean('refresh')) {
            $args['--refresh'] = true;
        }
        if ($request->boolean('alleen_boven')) {
            $args['--alleen-boven'] = true;
        }

        try {
            $exit = Artisan::call('gp:steden-aanbod', $args);
            $output = Artisan::output();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'artisan_failed'], 500);
        }

        if ($exit !== 0) {
            return response()->json([
                'ok' => false,
                'error' => 'command_failed',
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'ok' => true,
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
