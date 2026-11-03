<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Noshell: draai gp:stats-lezer via Bearer DEPLOY_TOKEN.
 * E-mail in JSON-body; wachtwoord via header X-GP-Plantjes-Password → env
 * GP_PLANTJES_PASSWORD voor de artisan-call. Geen PII/secrets in response of logs.
 */
class DeployStatsLezerController extends Controller
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

        $throttleKey = 'deploy-stats-lezer:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'invalid_email'], 422);
        }

        $password = (string) $request->header('X-GP-Plantjes-Password', '');
        if ($password === '') {
            return response()->json(['ok' => false, 'error' => 'missing_password'], 422);
        }

        // Zet alleen voor deze request in de process-env; artisan leest env().
        putenv('GP_PLANTJES_PASSWORD='.$password);
        $_ENV['GP_PLANTJES_PASSWORD'] = $password;
        $_SERVER['GP_PLANTJES_PASSWORD'] = $password;

        try {
            $exit = Artisan::call('gp:stats-lezer', ['email' => $email]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'artisan_failed'], 500);
        } finally {
            putenv('GP_PLANTJES_PASSWORD');
            unset($_ENV['GP_PLANTJES_PASSWORD'], $_SERVER['GP_PLANTJES_PASSWORD']);
        }

        if ($exit !== 0) {
            return response()->json(['ok' => false, 'error' => 'command_failed'], 500);
        }

        return response()->json([
            'ok' => true,
            'at' => now()->toIso8601String(),
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
