<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AanbodSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ZoekenController extends Controller
{
    private const RECENT_LIMIT = 24;

    public function __invoke(Request $request, AanbodSearch $search): JsonResponse
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
        if ($q === '') {
            return response()->json(['error' => 'Vul een postcode of plaats in.'], 400);
        }
        if (mb_strlen($q) > 120) {
            return response()->json(['error' => 'Zoekopdracht is te lang.'], 400);
        }

        $type = (string) $request->query('type', '');
        if ($type !== 'uitdelen' && $type !== 'ophalen') {
            $type = null;
        }

        $plant = trim((string) $request->query('plant', ''));
        if (mb_strlen($plant) > 80) {
            $plant = mb_substr($plant, 0, 80);
        }

        $key = 'search:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => 'Te vaak gezocht. Probeer later opnieuw.'], 429);
        }
        RateLimiter::hit($key, 3600);

        $result = $search->search($q, $type, true, $plant !== '' ? $plant : null);

        $payload = [
            'meta' => $result['meta'],
            'items' => $result['items'],
        ];

        // Privacy: geen e-mail/telefoon in API-payload
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (is_string($json) && preg_match('/"(email|telefoon|password|phone)"\s*:/i', $json)) {
            return response()->json(['error' => 'Interne privacyfout'], 500);
        }

        return response()->json($payload);
    }
}
