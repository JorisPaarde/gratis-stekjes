<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MilestoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

/**
 * Gebruikersoverzicht voor beheerders: naam, e-mail, aanmelddatum, aanbod en badges.
 *
 * De zoekterm gaat via POST naar de sessie (niet in de URL), zodat e-mailadressen niet in
 * querystrings, access-logs of de browsergeschiedenis belanden. Er wordt niets gelogd.
 */
class GebruikersController extends Controller
{
    public const PER_PAGE = 25;

    public const SESSION_KEY = 'beheer_gebruikers_zoek';

    public function index(Request $request, MilestoneService $milestones): Response
    {
        $zoek = (string) $request->session()->get(self::SESSION_KEY, '');

        $users = Schema::hasTable('users')
            ? $this->query($zoek)->paginate(self::PER_PAGE)->withPath(route('beheer.gebruikers'))
            : new LengthAwarePaginator([], 0, self::PER_PAGE);

        $rows = collect($users->items())->map(fn (User $u): array => [
            'user' => $u,
            'badges' => collect($milestones->badgesFor($u))->where('earned', true)->pluck('label')->values()->all(),
        ]);

        $html = view('beheer.gebruikers', [
            'title' => 'Gebruikers',
            'metaDescription' => 'Gebruikersoverzicht (beheer).',
            'canonical' => route('beheer.gebruikers'),
            'users' => $users,
            'rows' => $rows,
            'zoek' => $zoek,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function zoek(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zoek' => ['nullable', 'string', 'max:254'],
        ]);
        $zoek = trim((string) ($data['zoek'] ?? ''));

        if ($zoek === '' || $request->boolean('wissen')) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $zoek);
        }

        return redirect()->route('beheer.gebruikers');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<User> */
    private function query(string $zoek)
    {
        $q = User::query()
            ->withCount(Schema::hasTable('aanbod') ? ['aanbod'] : [])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($zoek !== '') {
            $like = '%'.addcslashes(mb_strtolower($zoek), '\\%_').'%';
            $q->where(function ($w) use ($like): void {
                $w->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        return $q;
    }
}
