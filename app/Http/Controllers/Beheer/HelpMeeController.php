<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\HelpMeeInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class HelpMeeController extends Controller
{
    public function index(): Response
    {
        $items = Schema::hasTable('help_mee_interests')
            ? HelpMeeInterest::query()->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get()
            : collect();

        $html = view('beheer.help-mee', [
            'title' => 'Help mee',
            'metaDescription' => 'Aanmeldingen Help mee (beheer).',
            'canonical' => route('beheer.help-mee'),
            'items' => $items,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function markRead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $item = HelpMeeInterest::query()->find((int) $data['id']);
        if ($item && $item->read_at === null) {
            $item->update(['read_at' => now()]);
        }

        return redirect()
            ->route('beheer.help-mee')
            ->with('status', 'Aanmelding gemarkeerd als gelezen.');
    }
}
