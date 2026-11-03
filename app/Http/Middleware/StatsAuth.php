<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Alleen Gate see-stats (admin of is_stats_viewer). */
class StatsAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::allows('see-stats')) {
            return redirect()->route('beheer.login');
        }

        return $next($request);
    }
}
