<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.index', [
            'title' => 'Account',
            'metaDescription' => 'Je account bij '.config('site.name').'.',
            'canonical' => route('account.index'),
            'user' => $request->user(),
        ]);
    }
}
