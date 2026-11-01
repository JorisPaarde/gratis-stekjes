<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Werk users.last_seen_at bij voor ingelogde leden, hooguit één keer per uur (geen extra query anders). */
final class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if ($user !== null) {
                $seen = $user->last_seen_at;
                if ($seen === null || $seen->lt(now()->subHour())) {
                    DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
                }
            }
        } catch (Throwable) {
            // Nooit een request laten falen op deze bijhouderij.
        }

        return $response;
    }
}
