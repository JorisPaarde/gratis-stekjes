<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sessie-dismiss voor de postcode-herinneringsbanner (geen persistent cookie).
 */
class PostcodeHerinneringController extends Controller
{
    public const SESSION_KEY = 'postcode_herinnering_weg';

    public function dismiss(Request $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, true);

        $next = (string) $request->input('next', '');
        if ($next !== '' && str_starts_with($next, $request->root())) {
            return redirect()->to($next);
        }

        return redirect()->to(route('account.index'));
    }
}
