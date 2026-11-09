<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Start een sessie alleen waar nodig (account, beheer, CSRF-formulieren, bestaande cookie).
 * Publieke GET-pagina's blijven zonder Set-Cookie en daarmee cachebaar.
 */
class StartSessionWhenNeeded extends StartSession
{
    public function handle($request, Closure $next): Response
    {
        if (! $this->shouldStartSession($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    private function shouldStartSession(Request $request): bool
    {
        // Telemetry/beacon: nooit sessie laden of terugschrijven. sendBeacon kan
        // parallel met form-POSTs lopen; een late save overschrijft anders flash/old.
        if ($request->is('meet', 'meet/*')) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $cookie = (string) config('session.cookie');
        if ($cookie !== '' && $request->cookies->has($cookie)) {
            return true;
        }

        // Pagina's met CSRF-formulier of auth-flow (eerste bezoek zonder cookie).
        if ($request->is(
            'account',
            'account/*',
            'beheer',
            'beheer/*',
            'feedback',
            'feedback/*',
            'voor-organisaties/aanmelden',
        )) {
            return true;
        }

        // Pest/Laravel withSession() start de store al vóór de middleware.
        try {
            if ($this->sessionConfigured() && $this->manager->driver()->isStarted()) {
                return true;
            }
        } catch (\Throwable) {
            // Geen sessie-driver beschikbaar.
        }

        return false;
    }
}
