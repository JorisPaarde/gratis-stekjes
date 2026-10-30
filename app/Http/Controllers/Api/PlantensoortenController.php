<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Plantensoorten;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlantensoortenController extends Controller
{
    public function __invoke(Request $request, Plantensoorten $plantensoorten): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }
        if (mb_strlen($q) > 80) {
            return response()->json(['error' => 'Zoekopdracht is te lang.'], 400);
        }

        $items = $plantensoorten->search($q, 10);

        return response()->json([
            'items' => array_map(static function (array $item): array {
                $url = null;
                if (! empty($item['slug'])) {
                    $url = route('stektips.show', ['slug' => $item['slug']]);
                }

                return [
                    'id' => $item['id'],
                    'naam' => $item['naam'],
                    'latijn' => $item['latijn'],
                    'synoniemen' => $item['synoniemen'],
                    'url' => $url,
                    'match' => $item['match'] ?? null,
                ];
            }, $items),
        ]);
    }
}
