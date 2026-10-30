<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View|Response|RedirectResponse
    {
        $password = (string) config('stekjes.admin_password', '');
        if ($password === '') {
            return response('Admin niet geconfigureerd.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        if ($request->session()->get('admin_ok')) {
            return redirect()->route('beheer.index');
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

        return redirect()->route('beheer.login');
    }
}
