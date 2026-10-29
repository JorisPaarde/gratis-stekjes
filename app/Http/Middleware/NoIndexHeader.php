<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging is never indexed. Production stays indexable unless NOINDEX=true.
 */
class NoIndexHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('site.noindex') || app()->environment('staging')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
