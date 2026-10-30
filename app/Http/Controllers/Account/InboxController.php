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
            ->latest()
            ->get()
            ->map(function (Conversation $c) use ($uid) {
                $other = (int) $c->user_a_id === $uid ? $c->userB : $c->userA;

                return [
                    'id' => $c->id,
                    'andere_naam' => $other?->name ?? 'Onbekend',
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
