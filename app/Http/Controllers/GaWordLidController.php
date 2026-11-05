<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConversionEvent;
use App\Services\ConversionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Korte CTA-route: meet cta_click en stuurt door naar registreren.
 */
class GaWordLidController extends Controller
{
    public function __invoke(Request $request, ConversionTracker $tracker): RedirectResponse
    {
        $tracker->context($request);
        $tracker->queueFlowCookie($request);

        $from = $tracker->normalizePath((string) $request->query('from', $request->headers->get('Referer') ? (parse_url((string) $request->headers->get('Referer'), PHP_URL_PATH) ?: '/') : '/'));

        $tracker->record(ConversionEvent::TYPE_CTA_CLICK, $request, [
            'path' => $from ?: '/',
        ]);

        $query = $request->query();
        unset($query['from']);

        // Preserve initiatief-params en UTM naar registreren.
        return redirect()->route('account.registreren', $query);
    }
}
