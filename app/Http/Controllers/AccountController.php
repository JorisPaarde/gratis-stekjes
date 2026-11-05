<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Account\WelcomeController;
use App\Services\MilestoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, MilestoneService $milestones): View|RedirectResponse
    {
        $user = $request->user();

        // Recovery max. 1× per sessie: voorkomt permanente loop als markeren faalt.
        if ($user->welcome_shown_at === null
            && ! $request->session()->get(WelcomeController::SESSION_RECOVERY_DONE)) {
            $request->session()->put(WelcomeController::SESSION_RECOVERY_DONE, true);

            return redirect()->route('account.welkom');
        }

        return view('account.index', [
            'title' => 'Account',
            'metaDescription' => 'Je account bij '.config('site.name').'.',
            'canonical' => route('account.index'),
            'user' => $user,
            'badges' => $milestones->badgesFor($user),
        ]);
    }
}
