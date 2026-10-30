<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Deelplekken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeelplekkenController extends Controller
{
    public function __invoke(Request $request, Deelplekken $deelplekken): JsonResponse
    {
        $type = trim((string) $request->query('type', ''));
        if ($type !== '' && ! $deelplekken->typeValid($type)) {
            return response()->json(['error' => 'Onbekend type.'], 400);
        }

        $provincie = trim((string) $request->query('provincie', ''));
        $plaats = trim((string) $request->query('plaats', ''));

        return response()->json([
            'types' => $deelplekken->typeLabels(),
            'items' => $deelplekken->listPublic(
                $type !== '' ? $type : null,
                $provincie !== '' ? $provincie : null,
                $plaats !== '' ? $plaats : null,
            ),
        ]);
    }
}
