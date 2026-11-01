<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\MailLog;
use App\Models\User;
use App\Notifications\VerifyEmailDutch;
use App\Services\MilestoneService;
use App\Support\BeheerTellers;
use App\Support\Naam;
use App\Support\SafeMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GebruikersController extends Controller
{
    public const PER_PAGINA = 50;

    /** Alias voor tests en code uit 0.45.0. */
    public const PER_PAGE = self::PER_PAGINA;

    /** Zoekterm staat in de sessie (via POST), nooit in de URL of access-logs. */
    public const SESSION_KEY = 'beheer_gebruikers_zoek';

    /** @var array<string, string> */
    public const FILTERS = [
        'alle' => 'Alle',
        'onbevestigd' => 'Onbevestigd',
        'mailfout' => 'Mail mislukt',
        'met_aanbod' => 'Met aanbod',
        'zonder_plek' => 'Zonder plek',
        'naam_email' => 'Naam = e-mail',
        'inactief' => 'Inactief (90 d)',
        'geblokkeerd' => 'Geblokkeerd',
    ];

    /** @var array<string, string> */
    public const SORTERINGEN = [
        'nieuw' => 'Nieuwste eerst',
        'actief' => 'Laatst actief',
        'aanbod' => 'Meeste aanbod',
        'naam' => 'Naam A-Z',
    ];

    public function index(Request $request, MilestoneService $milestones): Response
    {
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : 'alle';
        $sort = array_key_exists((string) $request->query('sort'), self::SORTERINGEN) ? (string) $request->query('sort') : 'nieuw';
        $q = mb_substr(trim((string) $request->session()->get(self::SESSION_KEY, '')), 0, 254);

        $query = User::query()
            ->withCount([
                'aanbod as aanbod_totaal',
                'aanbod as aanbod_actief' => fn ($a) => $a->publiclyListed(),
            ]);

        if ($q !== '') {
            $like = '%'.addcslashes(mb_strtolower($q), '\\%_').'%';
            // Zoeken op e-mail mag (support), maar de lijst toont het adres niet.
            $query->where(fn (Builder $w) => $w
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(locatie_label) LIKE ?', [$like])
                ->orWhere('id', ctype_digit($q) ? (int) $q : -1));
        }

        $this->pasFilterToe($query, $filter);

        match ($sort) {
            'actief' => $query->orderByRaw('last_seen_at IS NULL')->orderByDesc('last_seen_at'),
            'aanbod' => $query->orderByDesc('aanbod_totaal'),
            'naam' => $query->orderByRaw('LOWER(name)'),
            default => $query->orderByDesc('id'),
        };
        $query->orderByDesc('id');

        $gebruikers = $query->paginate(self::PER_PAGINA)->withQueryString();
        $ids = $gebruikers->getCollection()->pluck('id')->all();

        $laatsteMail = Schema::hasTable('mail_log') && $ids !== []
            ? MailLog::query()
                ->whereIn('id', MailLog::query()->selectRaw('MAX(id)')->whereIn('user_id', $ids)->groupBy('user_id'))
                ->get()
                ->keyBy('user_id')
            : collect();

        $gesprekken = Schema::hasTable('conversations') && $ids !== []
            ? $this->gesprekkenPerLid($ids)
            : [];

        $badges = [];
        foreach ($gebruikers->getCollection() as $u) {
            $badges[$u->id] = collect($milestones->badgesFor($u))->where('earned', true)->values()->all();
        }

        return $this->privé(view('beheer.gebruikers', [
            'badges' => $badges,
            'title' => 'Gebruikers · Beheer',
            'canonical' => route('beheer.gebruikers'),
            'gebruikers' => $gebruikers,
            'laatsteMail' => $laatsteMail,
            'gesprekken' => $gesprekken,
            'filter' => $filter,
            'sort' => $sort,
            'q' => $q,
            'filters' => self::FILTERS,
            'sorteringen' => self::SORTERINGEN,
            'pionierId' => app(MilestoneService::class)->pionierUserId(),
            'onbevestigdOud' => BeheerTellers::alle()['onbevestigd'],
        ]));
    }

    /** Zoekterm opslaan in de sessie (POST), zodat e-mailadressen niet in URL of logs komen. */
    public function zoek(Request $request): RedirectResponse
    {
        $data = $request->validate(['zoek' => ['nullable', 'string', 'max:254']]);
        $zoek = trim((string) ($data['zoek'] ?? ''));

        if ($zoek === '' || $request->boolean('wissen')) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $zoek);
        }

        return redirect()->route('beheer.gebruikers', array_filter([
            'filter' => $request->input('filter') !== 'alle' ? $request->input('filter') : null,
            'sort' => $request->input('sort') !== 'nieuw' ? $request->input('sort') : null,
        ]));
    }

    /** Beheerpagina's met persoonsgegevens: niet indexeren, niet cachen, geen referrer. */
    private function privé(View $view): Response
    {
        return response($view->render(), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function show(User $user, MilestoneService $milestones): Response
    {
        $aanbod = Schema::hasTable('aanbod')
            ? Aanbod::query()->where('user_id', $user->id)->orderByDesc('created_at')->limit(50)->get()
            : collect();
        $mails = Schema::hasTable('mail_log')
            ? MailLog::query()->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get()
            : collect();
        $gesprekken = Schema::hasTable('conversations')
            ? Conversation::query()->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))->count()
            : 0;
        $overdrachten = Schema::hasTable('conversations')
            ? Conversation::query()->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))->whereNotNull('overdracht_at')->count()
            : 0;

        return $this->privé(view('beheer.gebruiker', [
            'title' => $user->name.' · Gebruikers · Beheer',
            'canonical' => route('beheer.gebruikers.show', $user),
            'u' => $user,
            'aanbod' => $aanbod,
            'mails' => $mails,
            'gesprekken' => $gesprekken,
            'overdrachten' => $overdrachten,
            'badges' => $milestones->badgesFor($user),
            'verenigingen' => Schema::hasTable('vereniging_user') ? $user->verenigingen()->orderBy('naam')->get() : collect(),
            'isPionier' => $milestones->pionierUserId() === (int) $user->id,
            'isZelf' => Auth::check() && (int) Auth::id() === (int) $user->id,
            'naamIsEmail' => Naam::lijktOpEmail((string) $user->name),
        ]));
    }

    /** Account blokkeren of weer toestaan (geen inloggen, aanbod en berichten niet zichtbaar). */
    public function toggleActief(User $user): RedirectResponse
    {
        if (Auth::check() && (int) Auth::id() === (int) $user->id) {
            return back()->withErrors(['user' => 'Je kunt je eigen account niet blokkeren.']);
        }
        $user->forceFill(['active' => ! $user->active])->save();
        if (! $user->active && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('status', $user->active
            ? $user->name.' kan weer inloggen.'
            : $user->name.' is geblokkeerd en uitgelogd.');
    }

    /** Naam die een e-mailadres is terugzetten naar «Lid»; het lid kiest daarna zelf een naam. */
    public function resetNaam(User $user): RedirectResponse
    {
        $user->forceFill(['name' => 'Lid'])->save();

        return back()->with('status', 'Naam teruggezet naar «Lid». Bij de volgende keer opslaan van het profiel kiest het lid een eigen naam.');
    }

    /** Herinnering (opnieuw bevestigingsmail) naar onbevestigde leden ouder dan X dagen, max. 25 per keer. */
    public function herinnerOnbevestigd(): RedirectResponse
    {
        $key = 'beheer-herinnering';
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['user' => 'Dat kan één keer per uur. Probeer het straks opnieuw.']);
        }
        RateLimiter::hit($key, 3600);

        $leden = BeheerTellers::onbevestigdQuery()->orderBy('id')->limit(25)->get();
        foreach ($leden as $lid) {
            SafeMail::notify($lid, new VerifyEmailDutch);
        }

        return back()->with('status', $leden->count() === 1
            ? 'Herinnering gestuurd naar 1 lid.'
            : 'Herinnering gestuurd naar '.$leden->count().' leden.');
    }

    private function pasFilterToe(Builder $query, string $filter): void
    {
        match ($filter) {
            'onbevestigd' => $query->whereNull('email_verified_at')->where('active', true),
            'mailfout' => $query->whereIn('id', BeheerTellers::mailFoutUserIds()->all() ?: [-1]),
            'met_aanbod' => $query->whereHas('aanbod'),
            'zonder_plek' => $query->where(fn ($q) => $q->whereNull('locatie_label')->orWhere('locatie_label', '')),
            'naam_email' => $query->where(fn ($q) => $q->where('name', 'like', '%@%')
                ->orWhere('name', 'like', '%gmail%')->orWhere('name', 'like', '%hotmail%')
                ->orWhere('name', 'like', '%.com%')->orWhere('name', 'like', '%.nl%')),
            'inactief' => $query->whereNotNull('email_verified_at')
                ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(90)))
                ->where('created_at', '<', now()->subDays(90)),
            'geblokkeerd' => $query->where('active', false),
            default => null,
        };
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function gesprekkenPerLid(array $ids): array
    {
        $uit = array_fill_keys($ids, 0);
        foreach (['user_a_id', 'user_b_id'] as $kolom) {
            $rows = Conversation::query()->selectRaw($kolom.' as uid, COUNT(*) as n')->whereIn($kolom, $ids)->groupBy($kolom)->get();
            foreach ($rows as $r) {
                $uit[(int) $r->uid] = ($uit[(int) $r->uid] ?? 0) + (int) $r->n;
            }
        }

        return $uit;
    }
}
