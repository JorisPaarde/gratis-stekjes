<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS-afdwingen (productie/staging) + veilige response-headers (HSTS, CSP, …).
 */
class SecurityHeaders
{
    private const HSTS_MAX_AGE = 31536000; // 1 jaar

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldForceHttps() && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if ($request->secure() || $this->shouldSendHsts()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains'
            );
        }

        return $response;
    }

    private function shouldForceHttps(): bool
    {
        // In HTTP-tests is HTTPS lastig betrouwbaar te simuleren; redirect wel in echte staging/prod.
        if (app()->runningUnitTests()) {
            return false;
        }

        return app()->environment(['production', 'staging']);
    }

    private function shouldSendHsts(): bool
    {
        return app()->environment(['production', 'staging']);
    }

    private function contentSecurityPolicy(): string
    {
        // Pragmatische CSP: Vite-assets + Leaflet (unpkg) + OSM-tegels + inline styles (bestaande Blade).
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self' https://unpkg.com 'unsafe-inline'",
            "style-src 'self' https://unpkg.com 'unsafe-inline'",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://tile.openstreetmap.org",
            "connect-src 'self'",
            "font-src 'self' data:",
            "worker-src 'self'",
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
