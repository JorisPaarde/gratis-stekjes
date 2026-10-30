<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartConversationController extends Controller
{
    public function __invoke(Request $request, MessagingService $messaging): RedirectResponse
    {
        $other = (int) $request->query('user_id', 0);
        $aanbodId = $request->query('aanbod_id');
        $aanbodId = $aanbodId !== null && $aanbodId !== '' ? (int) $aanbodId : null;
        if ($aanbodId === 0) {
            $aanbodId = null;
        }

        if ($aanbodId !== null) {
            $aanbod = Aanbod::query()->find($aanbodId);
            if (! $aanbod) {
                return redirect()->route('aanbod')->with('error', 'Aanbod niet gevonden.');
            }
            // Eigenaar van het aanbod is de gesprekspartner als user_id ontbreekt
            if ($other <= 0) {
                $other = (int) $aanbod->user_id;
            }
        }

        $result = $messaging->startOrGet((int) $request->user()->id, $other, $aanbodId);
        if (empty($result['ok'])) {
            return redirect()->route('aanbod')->with('error', $result['error'] ?? 'Kon gesprek niet starten.');
        }

        return redirect()->route('account.gesprek', $result['conversation']);
    }
}
