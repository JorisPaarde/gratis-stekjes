<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Stub tot de partners-PR: lege markers zodat de zoekkaart geen 404 krijgt. */
class PartnersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'items' => [],
            'types' => [
                'partner' => 'Partner',
                'voedselbos' => 'Voedselbos',
                'plantenasiel' => 'Plantenasiel',
                'plantenbieb' => 'Plantenbieb',
                'ruilkast' => 'Ruilkast',
            ],
        ]);
    }
}
