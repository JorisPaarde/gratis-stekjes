<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NuTeStekken;
use Illuminate\Http\JsonResponse;

class NuTeStekkenController extends Controller
{
    public function __invoke(NuTeStekken $calendar): JsonResponse
    {
        $payload = $calendar->nu();

        return response()->json([
            'ok' => true,
            'maand' => $payload['maand'],
            'maand_naam' => $payload['maand_naam'],
            'timezone' => $payload['timezone'],
            'titel' => $payload['titel'],
            'intro' => $payload['intro'],
            'groepen' => $payload['groepen'],
            'stektips_index' => $payload['stektips_index'],
        ]);
    }
}
