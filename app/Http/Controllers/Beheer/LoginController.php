<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View|Response|RedirectResponse
    {
        if (Gate::allows('see-admin-dashboard')) {
            return redirect()->route('beheer.index');
        }

        $password = (string) config('stekjes.admin_password', '');
        if ($password === '') {
            return response('Admin niet geconfigureerd.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return view('beheer.login', [
            'title' => 'Beheer',
            'metaDescription' => 'Beheerlogin',
            'canonical' => route('beheer.login'),
        ]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $password = (string) config('stekjes.admin_password', '');
        if ($password === '') {
            return response('Admin niet geconfigureerd.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $given = (string) $request->input('password', '');
        if (! hash_equals($password, $given)) {
            return back()->withErrors(['password' => 'Wachtwoord klopt niet.']);
        }

        $request->session()->put('admin_ok', true);
        $request->session()->regenerate();

        return redirect()->route('beheer.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_ok');
        $request->session()->regenerate();

        // Account-sessie blijft; alleen de losse beheer-flag verdwijnt.
        if (Gate::allows('see-admin-dashboard')) {
            return redirect()->route('home');
        }

        return redirect()->route('beheer.login');
    }
}
