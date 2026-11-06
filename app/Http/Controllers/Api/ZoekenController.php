<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AanbodSearch;
use App\Services\ConversionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ZoekenController extends Controller
{
    private const RECENT_LIMIT = 24;

    public function __invoke(Request $request, AanbodSearch $search, ConversionTracker $conversions): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' && $request->boolean('recent')) {
            // Zonder locatie: alleen het nieuwste aanbod (begrensd, geen geocoding).
            $type = (string) $request->query('type', '');

            return response()->json([
                'meta' => null,
                'items' => $search->recent(
                    self::RECENT_LIMIT,
                    in_array($type, ['uitdelen', 'ophalen'], true) ? $type : null,
                ),
            ]);
        }
        $type = (string) $request->query('type', '');
        if ($type !== 'uitdelen' && $type !== 'ophalen') {
            $type = null;
        }

        $plant = trim((string) $request->query('plant', ''));
        if (mb_strlen($plant) > 80) {
            $plant = mb_substr($plant, 0, 80);
        }

        if ($q === '' && $plant === '') {
            return response()->json(['error' => 'Vul een postcode, plaats of plant in.'], 400);
        }
        if (mb_strlen($q) > 120) {
            return response()->json(['error' => 'Zoekopdracht is te lang.'], 400);
        }

        $key = 'search:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => 'Te vaak gezocht. Probeer later opnieuw.'], 429);
        }
        RateLimiter::hit($key, 3600);

        $geocodeMiss = false;
        if ($q === '') {
            // Alleen het 2e veld (plant): sorteren vanaf het eigen adres van het ingelogde lid,
            // anders nieuwste eerst. Geen coördinaten van het lid in de payload, alleen afstand_km.
            $user = $request->user();
            $payload = [
                'meta' => ['modus' => 'plant', 'plant' => $plant] + AanbodSearch::plantSortering($user),
                'items' => $search->zoekOpPlant($plant, $type, AanbodSearch::accountOrigin($user)),
            ];
        } else {
            $result = $search->search($q, $type, true, $plant !== '' ? $plant : null);
            $payload = [
                'meta' => $result['meta'],
                'items' => $result['items'],
            ];
            // meta=null na locatiezoeken = geocoder vond niets (lijst blijft ongesorteerd).
            $geocodeMiss = $result['meta'] === null;
        }

        // Privacy: geen e-mail/telefoon in API-payload
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (is_string($json) && preg_match('/"(email|telefoon|password|phone)"\s*:/i', $json)) {
            return response()->json(['error' => 'Interne privacyfout'], 500);
        }

        $bron = (string) $request->query('bron', '');
        $conversions->recordAanbodSearch(
            $request,
            $q,
            $plant,
            $type,
            count($payload['items'] ?? []),
            $geocodeMiss,
            $bron === 'auto' ? 'auto' : 'zelf',
        );

        return response()->json($payload);
    }
}
