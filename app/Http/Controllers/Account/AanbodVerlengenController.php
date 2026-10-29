<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AanbodExpiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AanbodVerlengenController extends Controller
{
    public function __invoke(Request $request, AanbodExpiryService $expiry): View|RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $result = $expiry->renewByToken($token);

        if (! empty($result['ok'])) {
            return view('account.aanbod-verlengen', [
                'title' => 'Aanbod verlengd',
                'metaDescription' => 'Je aanbod is verlengd.',
                'canonical' => url()->current(),
                'ok' => true,
                'expiresAt' => $result['expires_at'] ?? null,
            ]);
        }

        return view('account.aanbod-verlengen', [
            'title' => 'Verlengen mislukt',
            'metaDescription' => 'Deze verlenglink werkt niet.',
            'canonical' => url()->current(),
            'ok' => false,
            'error' => $result['error'] ?? 'Verlengen mislukt.',
        ]);
    }
}
