<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Deelplek;
use App\Models\Evenement;
use App\Models\MailLog;
use App\Models\PartnerMarker;
use App\Models\Report;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Services\AvgService;
use App\Services\DeelplekAanmelding;
use App\Services\Deelplekken;
use App\Services\Evenementen;
use App\Services\MilestoneService;
use App\Services\PartnerMarkers;
use App\Support\Country;
use App\Support\SafeMail;
use App\Support\ThrottleMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PartnerMarkers $partners,
        Deelplekken $deelplekken,
        Evenementen $evenementen,
    ): View {
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? PartnerMarker::query()->find($editId) : null;

        $editEvenementId = (int) $request->query('edit_evenement', 0);
        $editingEvenement = $editEvenementId > 0
            ? Evenement::query()->find($editEvenementId)
            : null;

        $reports = collect();
        if (Schema::hasTable('reports')) {
            $reports = Report::query()
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $pendingDeelplekken = collect();
        if (Schema::hasTable('deelplekken')) {
            $pendingDeelplekken = Deelplek::query()
                ->pending()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        $users = collect();
        if (Schema::hasTable('users')) {
            $users = User::query()
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(function (User $user) {
                    $lastMail = null;
                    if (Schema::hasTable('mail_log')) {
                        $lastMail = MailLog::query()
                            ->where('user_id', $user->id)
                            ->orderByDesc('id')
                            ->first();
                    }

                    return [
                        'user' => $user,
                        'last_mail' => $lastMail,
                    ];
                });
        }

        $agenda = collect();
        if (Schema::hasTable('evenementen')) {
            $agenda = Evenement::query()
                ->orderByDesc('start_datum')
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        $pionierId = app(MilestoneService::class)->pionierUserId();

        return view('beheer.index', [
            'pionierId' => $pionierId,
            'title' => 'Beheer',
            'metaDescription' => 'Beheer',
            'canonical' => route('beheer.index'),
            'markers' => PartnerMarker::query()->orderBy('naam')->orderBy('id')->get(),
            'typeLabels' => $partners->typeLabels(),
            'countries' => Country::labels(),
            'deelplekTypeLabels' => $deelplekken->typeLabels(),
            'evenementTypeLabels' => $evenementen->typeLabels(),
            'provincies' => $evenementen->provincies(),
            'editing' => $editing,
            'editingEvenement' => $editingEvenement,
            'evenementen' => $agenda,
            'reports' => $reports,
            'hasReportsTable' => Schema::hasTable('reports'),
            'pendingDeelplekken' => $pendingDeelplekken,
            'users' => $users,
        ]);
    }

    /** Exclusieve Pionier-badge naar dit lid verplaatsen (er is er maar één). */
    public function setPionier(User $user, MilestoneService $milestones): RedirectResponse
    {
        $milestones->setPionier($user);

        return redirect()
            ->route('beheer.index')
            ->with('status', 'De Pionier-badge staat nu bij '.$user->name.'.');
    }

    public function confirmDestroyUser(User $user): View|RedirectResponse
    {
        if (Auth::check() && (int) Auth::id() === (int) $user->id) {
            return redirect()
                ->route('beheer.index')
                ->withErrors(['user' => 'Je kunt je eigen account niet via beheer verwijderen.']);
        }

        return view('beheer.user-delete', [
            'title' => 'Gebruiker verwijderen',
            'metaDescription' => 'Bevestig verwijderen van gebruiker.',
            'canonical' => route('beheer.users.destroy.confirm', $user),
            'user' => $user,
            'confirmWord' => 'VERWIJDER',
        ]);
    }

    public function destroyUser(Request $request, User $user, AvgService $avg): RedirectResponse
    {
        if (Auth::check() && (int) Auth::id() === (int) $user->id) {
            return redirect()
                ->route('beheer.index')
                ->withErrors(['user' => 'Je kunt je eigen account niet via beheer verwijderen.']);
        }

        $data = $request->validate([
            'confirm' => ['required', 'string', 'max:40'],
        ], [
            'confirm.required' => 'Typ VERWIJDER om te bevestigen.',
        ]);

        if (trim((string) $data['confirm']) !== 'VERWIJDER') {
            return back()
                ->withInput()
                ->withErrors(['confirm' => 'Typ precies VERWIJDER om te bevestigen.']);
        }

        $deletedId = (int) $user->id;
        $avg->purgeUserAccount($user);

        Log::info('beheer.user.deleted', [
            'admin' => Auth::check() ? 'account' : 'password',
            'admin_user_id' => Auth::id(),
            'deleted_user_id' => $deletedId,
            'at' => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Gebruiker #'.$deletedId.' verwijderd.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $user = User::query()->find($userId);
        if ($user === null) {
            return back()->withErrors(['user' => 'Gebruiker niet gevonden.']);
        }

        if ($user->hasVerifiedEmail()) {
            return back()->withErrors(['user' => 'Dit account is al bevestigd.']);
        }

        $key = 'beheer-verify-resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'user' => ThrottleMessage::forSeconds(RateLimiter::availableIn($key)),
            ]);
        }
        RateLimiter::hit($key, 600);

        $ok = SafeMail::notify($user, new VerifyEmailDutch);
        if (! $ok) {
            return redirect()
                ->route('beheer.index')
                ->with('status', 'Bevestigingsmail inplannen mislukt voor gebruiker #'.$user->id.'.');
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Bevestigingsmail opnieuw gestuurd voor gebruiker #'.$user->id.'.');
    }

    public function approveDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->approve($plek);

        return redirect()->route('beheer.index')->with('status', 'Deelplek goedgekeurd en zichtbaar.');
    }

    public function rejectDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->reject($plek);

        return redirect()->route('beheer.index')->with('status', 'Deelplek afgewezen.');
    }

    public function saveMarker(Request $request, PartnerMarkers $partners): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $result = $partners->save($id > 0 ? $id : null, [
            'naam' => $request->input('naam'),
            'type' => $request->input('type'),
            'country' => $request->input('country', 'NL'),
            'plaats' => $request->input('plaats'),
            'beschrijving' => $request->input('beschrijving'),
            'url' => $request->input('url'),
            'bron' => $request->input('bron'),
            'visible' => $request->boolean('visible'),
        ]);

        if (empty($result['ok'])) {
            return back()->withErrors([
                'marker' => $result['error'] ?? 'Opslaan mislukt.',
            ])->withInput();
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Marker opgeslagen.');
    }

    public function deleteMarker(Request $request, PartnerMarkers $partners): RedirectResponse
    {
        $result = $partners->delete((int) $request->input('id', 0));
        if (empty($result['ok'])) {
            return back()->withErrors([
                'marker' => $result['error'] ?? 'Verwijderen mislukt.',
            ]);
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Marker verwijderd.');
    }

    public function markReportHandled(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('reports')) {
            return back();
        }
        $id = (int) $request->input('id', 0);
        DB::table('reports')->where('id', $id)->update(['handled' => true]);

        return redirect()->route('beheer.index')->with('status', 'Melding afgehandeld.');
    }

    public function saveEvenement(Request $request, Evenementen $evenementen): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $result = $evenementen->save($id > 0 ? $id : null, [
            'titel' => $request->input('titel'),
            'type' => $request->input('type'),
            'start_datum' => $request->input('start_datum'),
            'eind_datum' => $request->input('eind_datum'),
            'plaats' => $request->input('plaats'),
            'provincie' => $request->input('provincie'),
            'organisator' => $request->input('organisator'),
            'url' => $request->input('url'),
            'bron' => $request->input('bron'),
            'gecontroleerd_op' => $request->input('gecontroleerd_op'),
            'visible' => $request->boolean('visible'),
        ]);

        if (empty($result['ok'])) {
            return back()->withErrors([
                'evenement' => $result['error'] ?? 'Opslaan mislukt.',
            ])->withInput();
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Evenement opgeslagen.');
    }

    public function hideEvenement(Request $request, Evenementen $evenementen): RedirectResponse
    {
        $result = $evenementen->hide((int) $request->input('id', 0));
        if (empty($result['ok'])) {
            return back()->withErrors([
                'evenement' => $result['error'] ?? 'Verbergen mislukt.',
            ]);
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Evenement verborgen.');
    }
}
