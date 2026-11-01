<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mail bij nieuwe berichten uitzetten via de ondertekende link in die mail,
 * zonder in te loggen. GET toont alleen een knop (mailscanners zetten dus niets uit).
 */
class BerichtmailController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('pages.berichtmail-uit', [
            'title' => 'Mail bij nieuwe berichten uitzetten',
            'metaDescription' => 'Zet de mail bij nieuwe berichten op '.config('site.name').' uit.',
            'canonical' => route('home'),
            'actionUrl' => $request->fullUrl(),
            'uit' => $user->berichtmail === false,
            'klaar' => false,
        ]);
    }

    public function update(Request $request, User $user): View
    {
        $user->forceFill(['berichtmail' => false])->save();

        return view('pages.berichtmail-uit', [
            'title' => 'Mail bij nieuwe berichten staat uit',
            'metaDescription' => 'De mail bij nieuwe berichten op '.config('site.name').' staat uit.',
            'canonical' => route('home'),
            'actionUrl' => $request->fullUrl(),
            'uit' => true,
            'klaar' => true,
        ]);
    }
}
