<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function show(Request $request, Conversation $gesprek, MessagingService $messaging): View
    {
        $view = $messaging->conversationView((int) $request->user()->id, $gesprek);
        abort_if($view === null, 404);

        return view('account.gesprek', [
            'title' => 'Gesprek',
            'metaDescription' => 'Gesprek op '.config('site.name').'.',
            'canonical' => route('account.gesprek', $gesprek),
            'view' => $view,
            'uid' => (int) $request->user()->id,
            'formStarted' => time(),
        ]);
    }

    public function store(Request $request, Conversation $gesprek, MessagingService $messaging): RedirectResponse
    {
        $uid = (int) $request->user()->id;
        abort_unless($gesprek->involves($uid), 403);

        $action = (string) $request->input('action', 'message');

        if ($action === 'message') {
            $r = $messaging->sendMessage(
                $uid,
                $gesprek,
                (string) $request->input('body', ''),
                $request->all(),
                (string) $request->ip()
            );
            $ok = ! empty($r['ok']);

            return back()->with($ok ? 'status' : 'error', $ok ? 'Bericht verstuurd.' : ($r['error'] ?? 'Mislukt'));
        }

        if ($action === 'contact') {
            $r = $messaging->setContactPreference($uid, $gesprek, (string) $request->input('choice', ''));
            $ok = ! empty($r['ok']);

            return back()->with($ok ? 'status' : 'error', $ok ? 'Keuze opgeslagen.' : ($r['error'] ?? 'Mislukt'));
        }

        if ($action === 'report') {
            $otherId = $gesprek->otherUserId($uid);
            $r = $messaging->report($uid, $otherId, $gesprek->id, (string) $request->input('reason', ''));
            $ok = ! empty($r['ok']);

            return back()->with($ok ? 'status' : 'error', $ok ? 'Melding opgeslagen.' : ($r['error'] ?? 'Mislukt'));
        }

        if ($action === 'block') {
            $otherId = $gesprek->otherUserId($uid);
            $r = $messaging->block($uid, $otherId);
            $ok = ! empty($r['ok']);

            return redirect()
                ->route('account.inbox')
                ->with($ok ? 'status' : 'error', $ok ? 'Gebruiker geblokkeerd.' : ($r['error'] ?? 'Mislukt'));
        }

        return back();
    }
}
