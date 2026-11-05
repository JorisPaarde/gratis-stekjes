<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ConversionTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zorgt voor flow-cookie + first-touch UTM/referrer zonder de response te blokkeren.
 */
class CaptureConversionContext
{
    public function __construct(private readonly ConversionTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->tracker->isBot($request)) {
                $this->tracker->context($request);
                $this->tracker->queueFlowCookie($request);
            }
        } catch (\Throwable) {
            // Meting mag navigatie nooit blokkeren.
        }

        return $next($request);
    }
}
