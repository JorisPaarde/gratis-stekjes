<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Tipmail «eerste stekje» uitzetten via de ondertekende link in die mail,
 * zonder in te loggen. GET toont alleen een knop (mailscanners zetten dus niets uit).
 * Ongeldige/verlopen handtekening → nette GP-pagina (403), geen kale fout.
 */
class ActivatieHerinneringController extends Controller
{
    public function show(Request $request, User $user): View|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->ongeldigeLink();
        }

        $uit = Schema::hasColumn('users', 'activatieherinnering')
            && $user->activatieherinnering === false;

        return view('pages.activatieherinnering-uit', [
            'title' => 'Tipmail uitzetten',
            'metaDescription' => 'Zet de tipmail over je eerste stekje op '.config('site.name').' uit.',
            'canonical' => route('home'),
            'actionUrl' => $request->fullUrl(),
            'uit' => $uit,
            'klaar' => false,
        ]);
    }

    public function update(Request $request, User $user): View|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->ongeldigeLink();
        }

        if (Schema::hasColumn('users', 'activatieherinnering')) {
            $user->forceFill(['activatieherinnering' => false])->save();
        }

        return view('pages.activatieherinnering-uit', [
            'title' => 'Tipmail staat uit',
            'metaDescription' => 'De tipmail over je eerste stekje op '.config('site.name').' staat uit.',
            'canonical' => route('home'),
            'actionUrl' => $request->fullUrl(),
            'uit' => true,
            'klaar' => true,
        ]);
    }

    private function ongeldigeLink(): Response
    {
        return response()->view('pages.activatieherinnering-ongeldig', [
            'title' => 'Link werkt niet meer',
            'metaDescription' => 'Deze tipmail-link is verlopen of ongeldig.',
            'canonical' => route('home'),
        ], 403);
    }
}
