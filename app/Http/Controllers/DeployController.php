<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Beveiligd staging-deploy endpoint: migraties + caches wanneer cPanel geen
 * betrouwbare CLI heeft. Geen secrets of persoonsgegevens in de response.
 */
class DeployController extends Controller
{
    private const RATE_MAX = 5;

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

        $throttleKey = 'deploy-run:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'DeelplekkenSeeder', '--force' => true]);
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            // Hosting server-wide robots.txt schaduwt de Laravel-route; schrijf verse
            // app-output als statisch bestand in de docroot (public_path = docroot via usePublicPath).
            $robots = app(PageController::class)->robotsBody();
            if (@file_put_contents(public_path('robots.txt'), $robots) === false) {
                throw new \RuntimeException('robots_txt_write_failed');
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => $e::class,
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'version' => config('site.version'),
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
