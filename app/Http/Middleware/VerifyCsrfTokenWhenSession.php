<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Zet XSRF-TOKEN alleen als er een sessie is (publieke GET blijft cookieloos).
 */
class VerifyCsrfTokenWhenSession extends ValidateCsrfToken
{
    protected function addCookieToResponse($request, $response)
    {
        if (! $request->hasSession()) {
            return $response;
        }

        return parent::addCookieToResponse($request, $response);
    }
}
