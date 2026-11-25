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
        $user = $request->user();
        if ($user && ! $user->heeftGekozenNaam()) {
            return redirect()
                ->route('account.profiel')
                ->with('status', 'Kies eerst een voornaam of bijnaam. Die ziet de ander in het gesprek.');
        }

        $other = (int) $request->query('user_id', 0);
        $aanbodId = $request->query('aanbod_id');
        $aanbodId = $aanbodId !== null && $aanbodId !== '' ? (int) $aanbodId : null;
        if ($aanbodId === 0) {
            $aanbodId = null;
        }

        if ($aanbodId !== null) {
            $aanbod = Aanbod::query()->find($aanbodId);
            if (! $aanbod) {
                return $this->terugMetFout($request, 'Aanbod niet gevonden.');
            }
            // Eigenaar van het aanbod is de gesprekspartner als user_id ontbreekt
            if ($other <= 0) {
                $other = (int) $aanbod->user_id;
            }
        }

        $result = $messaging->startOrGet((int) $request->user()->id, $other, $aanbodId);
        if (empty($result['ok'])) {
            return $this->terugMetFout($request, $result['error'] ?? 'Kon gesprek niet starten.');
        }

        return redirect()->route('account.gesprek', $result['conversation']);
    }

    private function terugMetFout(Request $request, string $error): RedirectResponse
    {
        return redirect()->to($this->veiligeTerugUrl($request))->with('error', $error);
    }

    private function veiligeTerugUrl(Request $request): string
    {
        $fallback = route('aanbod');
        $previous = $request->headers->get('referer');
        $root = $request->root();
        if (! is_string($previous) || $previous === '') {
            return $fallback;
        }
        if ($previous !== $root && ! str_starts_with($previous, $root.'/')) {
            return $fallback;
        }
        $path = (string) (parse_url($previous, PHP_URL_PATH) ?? '');
        if ($path === '' || str_starts_with($path, '/account/bericht-starten')) {
            return $fallback;
        }

        return $previous;
    }
}
