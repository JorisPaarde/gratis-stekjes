<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privacy-vriendelijke paginateller: alleen path + datum + count.
 * Geen cookies, sessie, IP of user-agent opgeslagen.
 */
class RecordPageView
{
    public const MAX_PATH_LENGTH = 160;

    public const MAX_DISTINCT_PATHS_PER_DAY = 200;

    public const OTHER_PATH = '/_other';

    /** @var list<string> */
    private const EXCLUDED_PREFIXES = [
        '/beheer',
        '/account',
        '/feedback',
        '/build',
        '/storage',
        '/vendor',
        '/api',
        '/up',
        '/cron',
    ];

    /** @var list<string> */
    private const EXCLUDED_EXACT = [
        '/robots.txt',
        '/sitemap.xml',
        '/favicon.ico',
        '/manifest.webmanifest',
        '/sw.js',
        '/offline.html',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            Log::warning('page_views: teller mislukt', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function record(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            return;
        }

        if (! $this->isHtmlResponse($response)) {
            return;
        }

        if ($this->isBot($request)) {
            return;
        }

        $path = $this->normalizePath($request->getPathInfo());
        if ($path === null || $this->isExcluded($path)) {
            return;
        }

        if (! Schema::hasTable('page_views')) {
            return;
        }

        $date = now()->toDateString();
        $path = $this->capDistinctPaths($date, $path);

        $this->increment($date, $path);
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === ''
            || str_contains($contentType, 'text/html');
    }

    private function isBot(Request $request): bool
    {
        $ua = trim((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|outbrain|pinterest|vkshare|whatsapp|telegram|discordbot|headless|wget|curl\/|python-requests|scrapy|httpclient|libwww/i',
            $ua
        );
    }

    private function normalizePath(string $pathInfo): ?string
    {
        $path = '/'.ltrim($pathInfo, '/');
        $path = rawurldecode($path);
        $path = strtok($path, '?#') ?: '/';
        $path = (string) preg_replace('#/+#', '/', $path);

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        if (preg_match('/\.(css|js|map|json|xml|txt|ico|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|pdf)$/i', $path)) {
            return null;
        }

        if (strlen($path) > self::MAX_PATH_LENGTH) {
            return self::OTHER_PATH;
        }

        return $path;
    }

    private function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED_EXACT as $exact) {
            if ($path === $exact) {
                return true;
            }
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function capDistinctPaths(string $date, string $path): string
    {
        $exists = DB::table('page_views')
            ->where('date', $date)
            ->where('path', $path)
            ->exists();

        if ($exists || $path === self::OTHER_PATH) {
            return $path;
        }

        $distinct = (int) DB::table('page_views')
            ->where('date', $date)
            ->where('path', '!=', self::OTHER_PATH)
            ->count();

        if ($distinct >= self::MAX_DISTINCT_PATHS_PER_DAY) {
            return self::OTHER_PATH;
        }

        return $path;
    }

    private function increment(string $date, string $path): void
    {
        $updated = DB::table('page_views')
            ->where('date', $date)
            ->where('path', $path)
            ->increment('count');

        if ($updated > 0) {
            return;
        }

        try {
            DB::table('page_views')->insert([
                'date' => $date,
                'path' => $path,
                'count' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            DB::table('page_views')
                ->where('date', $date)
                ->where('path', $path)
                ->increment('count');
        }
    }
}
