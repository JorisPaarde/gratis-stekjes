<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Deelplek;
use App\Models\Evenement;
use App\Models\MailLog;
use App\Models\PartnerMarker;
use App\Models\Report;
use App\Models\User;
use App\Models\Vereniging;
use App\Notifications\VerifyEmailDutch;
use App\Services\AvgService;
use App\Services\DeelplekAanmelding;
use App\Services\Deelplekken;
use App\Services\Evenementen;
use App\Services\MilestoneService;
use App\Services\PartnerMarkers;
use App\Services\VerenigingService;
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
    public function index(): View
    {
        $kort = ['leden' => 0, 'nieuw_week' => 0, 'actief_week' => 0, 'aanbod_actief' => 0, 'gesprekken_week' => 0];
        try {
            $kort['leden'] = User::query()->count();
            $kort['nieuw_week'] = User::query()->where('created_at', '>=', now()->subWeek())->count();
            $kort['actief_week'] = Schema::hasColumn('users', 'last_seen_at')
                ? User::query()->where('last_seen_at', '>=', now()->subWeek())->count()
                : 0;
            $kort['aanbod_actief'] = Schema::hasTable('aanbod') ? Aanbod::query()->publiclyListed()->count() : 0;
            $kort['gesprekken_week'] = Schema::hasTable('conversations')
                ? Conversation::query()->where('created_at', '>=', now()->subWeek())->count()
                : 0;
        } catch (\Throwable) {
            // Overzicht blijft werken als een telling faalt.
        }

        $mailfouten = Schema::hasTable('mail_log')
            ? MailLog::query()->with('user')->where('status', 'fail')
                ->where('created_at', '>=', now()->subDays(7))
                ->orderByDesc('id')->limit(5)->get()
            : collect();

        return view('beheer.index', [
            'title' => 'Beheer',
            'metaDescription' => 'Beheer',
            'canonical' => route('beheer.index'),
            'kort' => $kort,
            'nieuwste' => User::query()->orderByDesc('id')->limit(6)->get(),
            'mailfouten' => $mailfouten,
        ]);
    }

    public function verenigingen(): View
    {
        $verenigingen = VerenigingService::beschikbaar()
            ? Vereniging::query()->withCount('leden')
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->limit(300)
                ->get()
            : collect();

        return $this->sectie('verenigingen', 'Verenigingen', ['verenigingen' => $verenigingen]);
    }

    public function aanmeldingen(Deelplekken $deelplekken): View
    {
        $pending = Schema::hasTable('deelplekken')
            ? Deelplek::query()->pending()->orderByDesc('created_at')->limit(100)->get()
            : collect();

        return $this->sectie('aanmeldingen', 'Deelplek-aanmeldingen', [
            'pendingDeelplekken' => $pending,
            'deelplekTypeLabels' => $deelplekken->typeLabels(),
        ]);
    }

    public function agenda(Request $request, Evenementen $evenementen): View
    {
        $editId = (int) $request->query('edit_evenement', 0);

        return $this->sectie('agenda', 'Agenda', [
            'editingEvenement' => $editId > 0 ? Evenement::query()->find($editId) : null,
            'evenementen' => Schema::hasTable('evenementen')
                ? Evenement::query()->orderByDesc('start_datum')->orderByDesc('id')->limit(200)->get()
                : collect(),
            'evenementTypeLabels' => $evenementen->typeLabels(),
            'provincies' => $evenementen->provincies(),
            'countries' => Country::labels(),
        ]);
    }

    public function kaartmarkers(Request $request, PartnerMarkers $partners): View
    {
        $editId = (int) $request->query('edit', 0);

        return $this->sectie('kaartmarkers', 'Kaartmarkers', [
            'editing' => $editId > 0 ? PartnerMarker::query()->find($editId) : null,
            'markers' => PartnerMarker::query()->orderBy('naam')->orderBy('id')->get(),
            'typeLabels' => $partners->typeLabels(),
            'countries' => Country::labels(),
        ]);
    }

    public function meldingen(): View
    {
        $heeft = Schema::hasTable('reports');

        return $this->sectie('meldingen', 'Meldingen', [
            'hasReportsTable' => $heeft,
            'reports' => $heeft
                ? Report::query()->orderBy('handled')->orderByDesc('id')->limit(100)->get()
                : collect(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function sectie(string $sectie, string $titel, array $data): View
    {
        return view('beheer.sectie', array_merge($data, [
            'sectie' => $sectie,
            'title' => $titel.' · Beheer',
            'canonical' => route('beheer.'.$sectie),
        ]));
    }

    /** Vereniging van leden goedkeuren of verbergen. */
    public function setVerenigingStatus(Request $request, Vereniging $vereniging): RedirectResponse
    {
        $status = (string) $request->input('status');
        abort_unless(in_array($status, [Vereniging::STATUS_APPROVED, Vereniging::STATUS_HIDDEN], true), 422);
        $vereniging->update(['status' => $status]);

        return redirect()
            ->route('beheer.verenigingen')
            ->with('status', $vereniging->naam.($status === Vereniging::STATUS_APPROVED ? ' staat nu op de site.' : ' is verborgen.'));
    }

    /** Exclusieve Pionier-badge naar dit lid verplaatsen (er is er maar één). */
    public function setPionier(User $user, MilestoneService $milestones): RedirectResponse
    {
        $milestones->setPionier($user);

        return redirect()
            ->route('beheer.gebruikers.show', $user)
            ->with('status', 'De Pionier-badge staat nu bij '.$user->name.'.');
    }

    public function confirmDestroyUser(User $user): View|RedirectResponse
    {
        if (Auth::check() && (int) Auth::id() === (int) $user->id) {
            return redirect()
                ->route('beheer.gebruikers')
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
                ->route('beheer.gebruikers')
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
            ->route('beheer.gebruikers')
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
            return back()
                ->with('status', 'Bevestigingsmail inplannen mislukt voor gebruiker #'.$user->id.'.');
        }

        return back()
            ->with('status', 'Bevestigingsmail opnieuw gestuurd naar '.$user->name.'.');
    }

    public function approveDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->approve($plek);

        return redirect()->route('beheer.aanmeldingen')->with('status', 'Deelplek goedgekeurd en zichtbaar.');
    }

    public function rejectDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->reject($plek);

        return redirect()->route('beheer.aanmeldingen')->with('status', 'Deelplek afgewezen.');
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
            ->route('beheer.kaartmarkers')
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
            ->route('beheer.kaartmarkers')
            ->with('status', 'Marker verwijderd.');
    }

    public function markReportHandled(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('reports')) {
            return back();
        }
        $id = (int) $request->input('id', 0);
        DB::table('reports')->where('id', $id)->update(['handled' => true]);

        return redirect()->route('beheer.meldingen')->with('status', 'Melding afgehandeld.');
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
            ->route('beheer.agenda')
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
            ->route('beheer.agenda')
            ->with('status', 'Evenement verborgen.');
    }
}
