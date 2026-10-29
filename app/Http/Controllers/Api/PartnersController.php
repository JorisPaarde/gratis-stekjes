<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerMarkers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    public function __invoke(Request $request, PartnerMarkers $partners): JsonResponse
    {
        $type = trim((string) $request->query('type', ''));
        if ($type !== '' && ! $partners->typeValid($type)) {
            return response()->json(['error' => 'Onbekend type.'], 400);
        }

        return response()->json([
            'types' => $partners->typeLabels(),
            'items' => $partners->listPublic($type !== '' ? $type : null),
        ]);
    }
}
