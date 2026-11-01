<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxController extends Controller
{
    public function __invoke(Request $request): View
    {
        $uid = (int) $request->user()->id;

        $rows = Conversation::query()
            ->with(['userA', 'userB'])
            ->where(function ($q) use ($uid) {
                $q->where('user_a_id', $uid)->orWhere('user_b_id', $uid);
            })
            ->withMax('messages as laatste_bericht_at', 'created_at')
            ->withCount(['messages as ongelezen' => function ($q) use ($uid) {
                $q->whereNull('read_at')->where('sender_id', '!=', $uid);
            }])
            ->latest()
            ->get()
            // Gesprek met het nieuwste bericht bovenaan; zonder berichten op aanmaakdatum.
            ->sortByDesc(fn (Conversation $c) => (string) ($c->laatste_bericht_at ?? $c->created_at))
            ->values()
            ->map(function (Conversation $c) use ($uid) {
                $other = (int) $c->user_a_id === $uid ? $c->userB : $c->userA;

                return [
                    'id' => $c->id,
                    'andere_naam' => $other?->publiekeNaam() ?? 'Onbekend',
                    'andere_plaats' => (string) ($other?->locatie_label ?? ''),
                    'ongelezen' => (int) ($c->ongelezen ?? 0),
                ];
            });

        return view('account.inbox', [
            'title' => 'Inbox',
            'metaDescription' => 'Je berichten op '.config('site.name').'.',
            'canonical' => route('account.inbox'),
            'rows' => $rows,
        ]);
    }
}
