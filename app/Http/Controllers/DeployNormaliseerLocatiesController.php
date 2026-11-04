<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Noshell: draai gp:normaliseer-locaties via Bearer DEPLOY_TOKEN.
 * Standaard dry-run; schrijven alleen met JSON {"apply": true}.
 * Response bevat artisan-output (geen PII: command print alleen id/label/land/reden).
 */
class DeployNormaliseerLocatiesController extends Controller
{
    private const RATE_MAX = 10;

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

        $throttleKey = 'deploy-normaliseer-locaties:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        $apply = $request->boolean('apply');
        $args = [
            '--pauze-ms' => (int) $request->input('pauze_ms', 200),
        ];
        if ($apply) {
            $args['--no-dry-run'] = true;
        } else {
            $args['--dry-run'] = true;
        }

        try {
            $exit = Artisan::call('gp:normaliseer-locaties', $args);
            $output = Artisan::output();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'artisan_failed'], 500);
        }

        if ($exit !== 0) {
            return response()->json([
                'ok' => false,
                'error' => 'command_failed',
                'apply' => $apply,
                'output' => $output,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'apply' => $apply,
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
};
