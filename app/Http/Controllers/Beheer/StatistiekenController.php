<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Models\Message;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StatistiekenController extends Controller
{
    public function index(): Response
    {
        $signupsTotal = User::query()->count();
        $confirmed = User::query()->whereNotNull('email_verified_at')->count();
        $unconfirmed = User::query()->whereNull('email_verified_at')->count();

        // Uitdeler = account met ≥1 aanbod; ophaler/alleen-account = geen aanbod.
        $uitdelers = User::query()->whereHas('aanbod')->count();
        $ophalers = max(0, $signupsTotal - $uitdelers);

        $offersTotal = Schema::hasTable('aanbod') ? Aanbod::query()->count() : 0;
        $offersActive = Schema::hasTable('aanbod')
            ? Aanbod::query()->publiclyListed()->count()
            : 0;

        $messagesTotal = Schema::hasTable('messages') ? Message::query()->count() : 0;

        $signupsPerDay = $this->signupsLastDays(30);
        $pageViews = $this->pageViewsLastDays(14);
        $pageViewsToday = $this->pageViewsToday();

        /** Chronological (oldest→newest) series for the 14-day pageview chart. */
        $pageViewsPerDay = $pageViews['byDay']
            ->sortBy('date')
            ->values()
            ->map(fn (array $day) => [
                'date' => $day['date'],
                'label' => $day['label'],
                'shortLabel' => Carbon::parse($day['date'])->timezone('Europe/Amsterdam')->format('d-m'),
                'total' => $day['total'],
            ]);

        $html = view('beheer.statistieken', [
            'title' => 'Statistieken',
            'metaDescription' => 'Aggregaatstatistieken (beheer).',
            'canonical' => route('beheer.statistieken'),
            'signupsTotal' => $signupsTotal,
            'confirmed' => $confirmed,
            'unconfirmed' => $unconfirmed,
            'uitdelers' => $uitdelers,
            'ophalers' => $ophalers,
            'offersTotal' => $offersTotal,
            'offersActive' => $offersActive,
            'messagesTotal' => $messagesTotal,
            'signupsPerDay' => $signupsPerDay,
            'signupsPerDayMax' => max(1, (int) $signupsPerDay->max('count')),
            'pageViewsByDay' => $pageViews['byDay'],
            'pageViewsPerDay' => $pageViewsPerDay,
            'pageViewsPerDayMax' => max(1, (int) $pageViewsPerDay->max('total')),
            'pageViewsTop' => $pageViews['top'],
            'pageViewsTotal' => $pageViews['total'],
            'pageViewsToday' => $pageViewsToday,
            'roleDefinition' => 'Uitdeler = account met minstens één aanbod; ophaler/alleen-account = nog geen aanbod.',
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    private function signupsLastDays(int $days): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = User::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $out = collect();
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $out->push([
                'date' => $key,
                'label' => $day->timezone('Europe/Amsterdam')->format('d-m'),
                'count' => (int) ($rows[$key] ?? 0),
            ]);
        }

        return $out;
    }

    private function pageViewsToday(): int
    {
        if (! Schema::hasTable('page_views')) {
            return 0;
        }

        return (int) PageView::query()
            ->whereDate('date', now()->toDateString())
            ->sum('count');
    }

    /**
     * @return array{
     *   byDay: Collection<int, array{date: string, label: string, total: int, pages: Collection<int, array{path: string, count: int}>}>,
     *   top: Collection<int, array{path: string, count: int}>,
     *   total: int
     * }
     */
    private function pageViewsLastDays(int $days): array
    {
        $empty = [
            'byDay' => collect(),
            'top' => collect(),
            'total' => 0,
        ];

        if (! Schema::hasTable('page_views')) {
            return $empty;
        }

        $from = now()->subDays($days - 1)->startOfDay()->toDateString();

        $rows = PageView::query()
            ->where('date', '>=', $from)
            ->orderByDesc('count')
            ->get(['date', 'path', 'count']);

        $total = (int) $rows->sum('count');

        $top = $rows
            ->groupBy('path')
            ->map(fn (Collection $group, string $path) => [
                'path' => $path,
                'count' => (int) $group->sum('count'),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(20)
            ->values();

        $byDate = $rows->groupBy(fn (PageView $row) => $row->date instanceof Carbon
            ? $row->date->toDateString()
            : (string) $row->date);

        $byDay = collect();
        $start = Carbon::parse($from)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            /** @var Collection<int, PageView> $dayRows */
            $dayRows = $byDate->get($key, collect());
            $pages = $dayRows
                ->sortByDesc('count')
                ->take(10)
                ->map(fn (PageView $row) => [
                    'path' => $row->path,
                    'count' => (int) $row->count,
                ])
                ->values();

            $byDay->push([
                'date' => $key,
                'label' => $day->timezone('Europe/Amsterdam')->format('d-m-Y'),
                'total' => (int) $dayRows->sum('count'),
                'pages' => $pages,
            ]);
        }

        return [
            'byDay' => $byDay->reverse()->values(),
            'top' => $top,
            'total' => $total,
        ];
    }
}
