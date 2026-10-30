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

            // Primair: robots.txt zit in de release-public/ en wordt door de hook gesynct.
            // Secundair: schrijf opnieuw naar de échte docroot (SCRIPT_FILENAME = index.php daar)
            // voor het geval public_path() nog naar release/public wijst.
            $this->writeRobotsTxtToDocroot();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'version' => config('site.version'),
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Schrijf env-aware robots.txt naar de HTTP-docroot. Faalt hard (exception → 500)
     * als het bestand niet landt of de inhoud niet klopt.
     */
    private function writeRobotsTxtToDocroot(): void
    {
        $robots = app(PageController::class)->robotsBody();
        $paths = $this->robotsTxtTargetPaths();
        $written = false;
        $errors = [];

        foreach ($paths as $path) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                $errors[] = 'missing_dir:'.$path;

                continue;
            }
            if (@file_put_contents($path, $robots) === false) {
                $errors[] = 'write_failed:'.$path;

                continue;
            }
            $read = @file_get_contents($path);
            if ($read !== $robots) {
                $errors[] = 'verify_failed:'.$path;

                continue;
            }
            $written = true;
        }

        if (! $written) {
            throw new \RuntimeException('robots_txt_write_failed:'.implode(',', $errors));
        }
    }

    /**
     * @return list<string>
     */
    private function robotsTxtTargetPaths(): array
    {
        $paths = [];

        // Front-controller index.php staat in de docroot (hook: usePublicPath(__DIR__)).
        // Alleen vertrouwen als het echt index.php is (niet phpunit/pest in tests).
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($script !== '' && is_file($script) && basename($script) === 'index.php') {
            $paths[] = dirname($script).DIRECTORY_SEPARATOR.'robots.txt';
        }

        $paths[] = public_path('robots.txt');

        return array_values(array_unique($paths));
    }

    private function endpointEnabled(): bool
    {
        if (app()->environment('staging')) {
            return true;
        }

        return (bool) config('stekjes.deploy_endpoint_enabled', false);
    }
}
