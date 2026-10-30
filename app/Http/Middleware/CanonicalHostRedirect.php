<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301 van geparkte alias-hosts (gratisstekjes.nl) naar de canonieke host.
 * Uit op staging/local/testing zodat die omgevingen niet meeliften.
 */
class CanonicalHostRedirect
{
    /** @var list<string> */
    private const REDIRECT_HOSTS = [
        'gratisstekjes.nl',
        'www.gratisstekjes.nl',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'staging', 'testing'])) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        if (! in_array($host, self::REDIRECT_HOSTS, true)) {
            return $next($request);
        }

        $canonical = (string) config('site.canonical_host', 'gratisplanten.nl');
        $target = 'https://'.$canonical.$request->getRequestUri();

        return redirect()->away($target, 301);
    }
}
