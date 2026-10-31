<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AanbodFoto;
use App\Services\AanbodFotoService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serveert herschreven aanbodfoto’s vanuit non-executable storage
 * (geen directe PHP-executie in de uploadmap).
 */
class AanbodFotoController extends Controller
{
    public function __invoke(AanbodFoto $foto, AanbodFotoService $fotos, string $variant = 'full'): BinaryFileResponse
    {
        $variant = $variant === 'thumb' ? 'thumb' : 'full';
        $path = $fotos->absolutePath($foto, $variant);

        abort_unless(is_file($path) && is_readable($path), 404);

        return response()->file($path, [
            'Content-Type' => $foto->mime ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
