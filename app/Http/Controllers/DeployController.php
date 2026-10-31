<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deelplek;
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

            // Na caches: opcache resetten + LiteSpeed-marker herschrijven (account-home).
            $this->refreshPhpWorkers();
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => $e::class,
                'message' => $e->getMessage(),
            ], 500);
        }

        $deelplekkenCount = 0;
        try {
            $deelplekkenCount = (int) Deelplek::query()->visible()->count();
        } catch (Throwable) {
            $deelplekkenCount = 0;
        }

        return response()->json([
            'ok' => true,
            'version' => config('site.version'),
            'deelplekken_count' => $deelplekkenCount,
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Best-effort: opcache_reset in dit web-proces, herschrijf
     * /home/<account>/.lsphp_restart.txt (inhoud, niet alleen mtime), en bump
     * entrypoint-mtimes. Geen secrets/paden in logs of response.
     */
    private function refreshPhpWorkers(): void
    {
        clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $docroot = $this->resolveHttpDocroot();
        if ($docroot !== null) {
            foreach (['index.php', '.htaccess'] as $name) {
                $path = $docroot.DIRECTORY_SEPARATOR.$name;
                if (is_file($path)) {
                    @touch($path);
                }
            }
        }

        foreach ($this->lsphpRestartHomeCandidates($docroot) as $home) {
            $marker = $home.DIRECTORY_SEPARATOR.'.lsphp_restart.txt';
            $payload = 'restart '.gmdate('c').' '.bin2hex(random_bytes(8))."\n";
            if (is_file($marker) || is_link($marker)) {
                @unlink($marker);
            }
            @file_put_contents($marker, $payload, LOCK_EX);
        }
    }

    /**
     * Docroot via front-controller SCRIPT_FILENAME (index.php), anders null.
     */
    private function resolveHttpDocroot(): ?string
    {
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($script === '' || ! is_file($script) || basename($script) !== 'index.php') {
            return null;
        }

        return dirname($script);
    }

    /**
     * Account-home kandidaten voor LiteSpeed-marker (nooit release-map).
     *
     * @return list<string>
     */
    private function lsphpRestartHomeCandidates(?string $docroot): array
    {
        $homes = [];
        if ($docroot !== null) {
            $fromDocroot = dirname($docroot);
            if ($fromDocroot !== '' && $fromDocroot !== '/' && $fromDocroot !== '.') {
                $homes[$fromDocroot] = true;
            }
        }

        // app-staging/current of app-staging/releases/<sha> → account-home.
        $base = base_path();
        $real = realpath($base) ?: $base;
        foreach ([2, 3] as $up) {
            $candidate = $real;
            for ($i = 0; $i < $up; $i++) {
                $candidate = dirname($candidate);
            }
            if ($candidate !== '' && $candidate !== '/' && is_dir($candidate.'/.deploy')) {
                $homes[$candidate] = true;
            }
        }

        $envHome = getenv('HOME');
        if (is_string($envHome) && trim($envHome) !== '' && is_dir($envHome)) {
            $homes[rtrim($envHome, '/')] = true;
        }

        return array_values(array_filter(
            array_keys($homes),
            static fn (string $h): bool => is_dir($h)
        ));
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
