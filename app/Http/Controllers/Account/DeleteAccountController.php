<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AvgService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeleteAccountController extends Controller
{
    public function edit(): View
    {
        return view('account.verwijderen', [
            'title' => 'Account verwijderen',
            'metaDescription' => 'Verwijder je account en gegevens bij '.config('site.name').'.',
            'canonical' => route('account.verwijderen'),
        ]);
    }

    public function destroy(Request $request, AvgService $avg): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Bevestig met je wachtwoord.',
        ]);

        $result = $avg->deleteUserAccount(
            $request->user(),
            (string) $request->input('password', '')
        );

        if (empty($result['ok'])) {
            return back()->withErrors([
                'password' => $result['error'] ?? 'Verwijderen mislukt.',
            ]);
        }

        return redirect()
            ->route('home')
            ->with('status', 'Je account en bijbehorende gegevens zijn verwijderd.');
    }
}
