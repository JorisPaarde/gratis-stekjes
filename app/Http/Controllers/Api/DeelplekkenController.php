<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Deelplekken;
use App\Services\Evenementen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeelplekkenController extends Controller
{
    public function __invoke(Request $request, Deelplekken $deelplekken, Evenementen $evenementen): JsonResponse
    {
        $type = trim((string) $request->query('type', ''));
        if ($type !== '' && ! $deelplekken->typeValid($type)) {
            return response()->json(['error' => 'Onbekend type.'], 400);
        }

        $provincie = trim((string) $request->query('provincie', ''));
        $plaats = trim((string) $request->query('plaats', ''));

        $items = $deelplekken->listPublic(
            $type !== '' ? $type : null,
            $provincie !== '' ? $provincie : null,
            $plaats !== '' ? $plaats : null,
        );

        // Tijdelijke evenementen alleen zonder deelplek-typefilter (anders verdwijnen
        // bomenhubs bij filter plantenbieb e.d.). Provincie/plaats gelden wel.
        if ($type === '') {
            $items = array_values(array_merge(
                $items,
                $evenementen->listPublicForKaart(
                    $provincie !== '' ? $provincie : null,
                    $plaats !== '' ? $plaats : null,
                ),
            ));
        }

        return response()->json([
            'types' => $deelplekken->typeLabels(),
            'evenement_types' => $evenementen->typeLabels(),
            'items' => $items,
        ]);
    }
}
