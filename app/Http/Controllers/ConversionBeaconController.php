<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConversionEvent;
use App\Services\ConversionTracker;
use App\Support\ThrottleMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ConversionBeaconController extends Controller
{
    public function __invoke(Request $request, ConversionTracker $tracker): Response
    {
        $bucket = 'conversion-beacon:'.substr(hash('sha256', (string) $request->ip()), 0, 16);
        $limit = max(5, (int) config('conversion.beacon_per_minute', 30));
        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            return response(ThrottleMessage::forKey($bucket), 429)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        RateLimiter::hit($bucket, 60);

        if ($tracker->shouldSkipMeasurement($request)) {
            return response('ok', 204);
        }

        $type = Str::limit(trim((string) $request->input('type', '')), 40, '');
        if (! in_array($type, ConversionEvent::BEACON_TYPES, true)) {
            return response('bad type', 422)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $path = $tracker->normalizePath((string) $request->input('path', '/account/registreren'));
        if ($path === null || ! in_array($path, ConversionEvent::BEACON_PATHS, true)) {
            return response('bad path', 422)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // validation_error hoort niet op de publieke beacon (RegisterRequest logt server-side).
        $tracker->context($request);
        $tracker->queueFlowCookie($request);
        $tracker->record($type, $request, ['path' => $path]);

        return response('ok', 204);
    }
}
