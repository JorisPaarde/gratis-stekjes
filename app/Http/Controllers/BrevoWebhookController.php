<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BrevoNewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Brevo marketing-webhook (unsubscribe e.d.). Beveiligd met gedeeld token in de URL.
 * Geen persoonsgegevens in de JSON-response.
 */
class BrevoWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, BrevoNewsletterService $brevo): Response|JsonResponse
    {
        $expected = $brevo->webhookToken();
        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $payload = $request->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $result = $brevo->handleWebhook($payload);

        return response()->json([
            'ok' => true,
            'handled' => (int) ($result['handled'] ?? 0),
        ]);
    }
}
