<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CanonicalUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301 van /index.php en /index.php/... naar / resp. het pad zonder index.php.
 */
class StripIndexPhpRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo() ?: '/';

        if ($path !== '/index.php' && ! str_starts_with($path, '/index.php/')) {
            // Sommige servers laten PATH_INFO leeg en zetten index.php in REQUEST_URI.
            $uriPath = parse_url($request->getRequestUri(), PHP_URL_PATH) ?: '/';
            if ($uriPath !== '/index.php' && ! str_starts_with($uriPath, '/index.php/')) {
                return $next($request);
            }
            $path = $uriPath;
        }

        $stripped = substr($path, strlen('/index.php')) ?: '/';
        if ($stripped !== '/' && ! str_starts_with($stripped, '/')) {
            $stripped = '/'.$stripped;
        }

        $target = CanonicalUrl::absolute($stripped);
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->away($target, 301);
    }
}
