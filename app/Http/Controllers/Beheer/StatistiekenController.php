<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Aanbod;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\PageView;
use App\Models\User;
use App\Support\StatsSvg;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StatistiekenController extends Controller
{
    public function index(): Response
    {
        $leden = User::query()->count();
        $confirmed = User::query()->whereNotNull('email_verified_at')->count();
        $unconfirmed = User::query()->whereNull('email_verified_at')->count();

        // Distinct members who ever had ≥1 listing (any visibility / expiry status).
        $ledenMetAanbod = Schema::hasTable('aanbod')
            ? (int) Aanbod::query()->distinct()->count('user_id')
            : 0;
        $ledenZonderAanbod = max(0, $leden - $ledenMetAanbod);

        $offersTotal = Schema::hasTable('aanbod') ? Aanbod::query()->count() : 0;
        $offersActive = Schema::hasTable('aanbod')
            ? Aanbod::query()->publiclyListed()->count()
            : 0;
        $offersByType = $this->offersByType();

        $messagesTotal = Schema::hasTable('messages') ? Message::query()->count() : 0;

        $feedbackTotal = Schema::hasTable('feedback') ? Feedback::query()->count() : 0;
        $feedbackUnread = Schema::hasTable('feedback')
            ? Feedback::query()->whereNull('read_at')->count()
            : 0;

        $signupsPerDay = $this->signupsLastDays(30);
        $pageViews = $this->pageViewsLastDays(30);
        $pageViews14 = $this->pageViewsLastDays(14);
        $pageViewsToday = $this->pageViewsToday();

        $pageViewsPerDay = $pageViews['byDay']
            ->sortBy('date')
            ->values()
            ->map(fn (array $day) => [
                'date' => $day['date'],
                'label' => $day['label'],
                'shortLabel' => Carbon::parse($day['date'])->timezone('Europe/Amsterdam')->format('d-m'),
                'total' => $day['total'],
            ]);

        $pageViewsPerDay14 = $pageViews14['byDay']
            ->sortBy('date')
            ->values()
            ->map(fn (array $day) => [
                'date' => $day['date'],
                'label' => Carbon::parse($day['date'])->timezone('Europe/Amsterdam')->format('d-m'),
                'total' => $day['total'],
            ]);

        $signupSpark = $signupsPerDay->pluck('count')->map(fn ($v) => (int) $v)->all();
        $viewsSpark = $pageViewsPerDay14->pluck('total')->map(fn ($v) => (int) $v)->all();
        $messagesPerDay = $this->messagesLastDays(14);
        $messageSpark = $messagesPerDay->pluck('count')->map(fn ($v) => (int) $v)->all();
        $offersPerDay = $this->offersLastDays(14);
        $offerSpark = $offersPerDay->pluck('count')->map(fn ($v) => (int) $v)->all();
        $feedbackPerDay = $this->feedbackLastDays(14);
        $feedbackSpark = $feedbackPerDay->pluck('count')->map(fn ($v) => (int) $v)->all();

        $overviewRows = [];
        $viewsByDate = $pageViewsPerDay->keyBy('date');
        foreach ($signupsPerDay as $row) {
            $overviewRows[] = [
                'label' => $row['label'],
                'a' => (int) $row['count'],
                'b' => (int) ($viewsByDate->get($row['date'])['total'] ?? 0),
            ];
        }
        $overviewText = 'Aanmeldingen en bezoekers per dag (30 dagen). '
            .'Aanmeldingen totaal in periode: '.$signupsPerDay->sum('count').'. '
            .'Bezoekers (pageviews) totaal in periode: '.$pageViews['total'].'.';

        $signupBars = $signupsPerDay->map(fn (array $r) => [
            'label' => $r['label'],
            'value' => (int) $r['count'],
            'title' => $r['label'].': '.$r['count'].' aanmeldingen',
        ])->all();

        $viewBars = $pageViewsPerDay14->map(fn (array $r) => [
            'label' => $r['label'],
            'value' => (int) $r['total'],
            'title' => $r['label'].': '.$r['total'].' bezoekers',
        ])->all();

        $topBars = $pageViews14['top']->map(fn (array $r) => [
            'label' => $r['path'],
            'value' => (int) $r['count'],
        ])->all();

        $topText = $pageViews14['top']->isEmpty()
            ? 'Nog geen pageviews geregistreerd.'
            : 'Top pagina’s (14 dagen): '.$pageViews14['top']
                ->map(fn (array $r) => $r['path'].' ('.$r['count'].')')
                ->implode(', ').'.';

        $html = view('beheer.statistieken', [
            'title' => 'Statistieken',
            'metaDescription' => 'Aggregaatstatistieken (beheer).',
            'canonical' => route('beheer.statistieken'),
            'leden' => $leden,
            'confirmed' => $confirmed,
            'unconfirmed' => $unconfirmed,
            'ledenMetAanbod' => $ledenMetAanbod,
            'ledenZonderAanbod' => $ledenZonderAanbod,
            'offersTotal' => $offersTotal,
            'offersActive' => $offersActive,
            'offersByType' => $offersByType,
            'messagesTotal' => $messagesTotal,
            'feedbackTotal' => $feedbackTotal,
            'feedbackUnread' => $feedbackUnread,
            'signupsPerDay' => $signupsPerDay,
            'pageViewsByDay' => $pageViews14['byDay'],
            'pageViewsTop' => $pageViews14['top'],
            'pageViewsTotal' => $pageViews14['total'],
            'pageViewsToday' => $pageViewsToday,
            'svgOverview' => StatsSvg::dualLineChart(
                $overviewRows,
                'Lijndiagram aanmeldingen en bezoekers per dag, laatste 30 dagen',
                $overviewText,
                'Aanmeldingen',
                'Bezoekers',
            ),
            'svgSignupSpark' => StatsSvg::sparkline($signupSpark, 'Sparkline aanmeldingen, 30 dagen'),
            'svgViewsSpark' => StatsSvg::sparkline($viewsSpark, 'Sparkline bezoekers, 14 dagen', stroke: StatsSvg::COLOR_ORANGE),
            'svgOfferSpark' => $offersPerDay->sum('count') > 0 || $offersTotal > 0
                ? StatsSvg::sparkline($offerSpark, 'Sparkline nieuwe aanbiedingen, 14 dagen')
                : '',
            'svgMessageSpark' => $messagesPerDay->sum('count') > 0 || $messagesTotal > 0
                ? StatsSvg::sparkline($messageSpark, 'Sparkline berichten, 14 dagen')
                : '',
            'svgFeedbackSpark' => $feedbackPerDay->sum('count') > 0 || $feedbackTotal > 0
                ? StatsSvg::sparkline($feedbackSpark, 'Sparkline feedback, 14 dagen', stroke: StatsSvg::COLOR_ORANGE)
                : '',
            'svgSignups' => StatsSvg::barChart(
                $signupBars,
                'Staafdiagram aanmeldingen per dag, laatste 30 dagen',
                'Aanmeldingen per dag (30 dagen): '.$signupsPerDay->map(fn ($r) => $r['label'].'='.$r['count'])->implode(', ').'.',
            ),
            'svgViews' => StatsSvg::barChart(
                $viewBars,
                'Staafdiagram bezoekers per dag, laatste 14 dagen',
                'Bezoekers per dag (14 dagen): '.$pageViewsPerDay14->map(fn ($r) => $r['label'].'='.$r['total'])->implode(', ').'.',
                fill: StatsSvg::COLOR_ORANGE,
            ),
            'svgConfirmed' => StatsSvg::donut(
                $confirmed,
                $unconfirmed,
                'Bevestigd',
                'Onbevestigd',
                'Donut bevestigd versus onbevestigd',
                'Bevestigd: '.$confirmed.', onbevestigd: '.$unconfirmed.'.',
            ),
            'svgLedenAanbod' => StatsSvg::donut(
                $ledenMetAanbod,
                $ledenZonderAanbod,
                'Heeft aangeboden',
                'Nog niet',
                'Donut leden die iets aangeboden hebben versus nog niet',
                $ledenMetAanbod.' van '.$leden.' leden heeft iets aangeboden.',
                StatsSvg::COLOR_LEAF,
                StatsSvg::COLOR_SAGE,
            ),
            'svgLedenProgress' => StatsSvg::progressShare(
                $ledenMetAanbod,
                $leden,
                'Voortgang: leden die iets aangeboden hebben',
                $ledenMetAanbod.' van '.$leden.' leden heeft iets aangeboden.',
            ),
            'svgOffersType' => StatsSvg::donut(
                (int) ($offersByType['uitdelen'] ?? 0),
                (int) ($offersByType['ophalen'] ?? 0),
                'Uitdelen',
                'Zoeken / ophalen',
                'Donut aanbod per type: uitdelen versus ophalen',
                'Aanbod uitdelen: '.(int) ($offersByType['uitdelen'] ?? 0).', zoeken/ophalen: '.(int) ($offersByType['ophalen'] ?? 0).'.',
                StatsSvg::COLOR_LEAF,
                StatsSvg::COLOR_ORANGE,
            ),
            'svgTopPages' => StatsSvg::horizontalBars(
                $topBars,
                'Horizontale staven top pagina’s, 14 dagen',
                $topText,
            ),
            'svgOffers' => StatsSvg::barChart(
                $offersPerDay->map(fn (array $r) => [
                    'label' => $r['label'],
                    'value' => (int) $r['count'],
                    'title' => $r['label'].': '.$r['count'].' nieuwe aanbiedingen',
                ])->all(),
                'Staafdiagram nieuwe aanbiedingen per dag, 14 dagen',
                'Nieuwe aanbiedingen per dag (14 dagen): '.$offersPerDay->map(fn ($r) => $r['label'].'='.$r['count'])->implode(', ').'.',
            ),
            'svgMessages' => StatsSvg::barChart(
                $messagesPerDay->map(fn (array $r) => [
                    'label' => $r['label'],
                    'value' => (int) $r['count'],
                    'title' => $r['label'].': '.$r['count'].' berichten',
                ])->all(),
                'Staafdiagram berichten per dag, 14 dagen',
                'Berichten per dag (14 dagen): '.$messagesPerDay->map(fn ($r) => $r['label'].'='.$r['count'])->implode(', ').'.',
                fill: StatsSvg::COLOR_SAGE,
            ),
            'svgFeedback' => StatsSvg::barChart(
                $feedbackPerDay->map(fn (array $r) => [
                    'label' => $r['label'],
                    'value' => (int) $r['count'],
                    'title' => $r['label'].': '.$r['count'].' feedback',
                ])->all(),
                'Staafdiagram feedback per dag, 14 dagen',
                'Feedback per dag (14 dagen): '.$feedbackPerDay->map(fn ($r) => $r['label'].'='.$r['count'])->implode(', ').'.',
                fill: StatsSvg::COLOR_ORANGE,
            ),
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @return array{uitdelen: int, ophalen: int}
     */
    private function offersByType(): array
    {
        if (! Schema::hasTable('aanbod')) {
            return ['uitdelen' => 0, 'ophalen' => 0];
        }

        $rows = Aanbod::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            'uitdelen' => (int) ($rows['uitdelen'] ?? 0),
            'ophalen' => (int) ($rows['ophalen'] ?? 0),
        ];
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

    /**
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    private function offersLastDays(int $days): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $out = collect();

        if (! Schema::hasTable('aanbod')) {
            for ($i = 0; $i < $days; $i++) {
                $day = $from->copy()->addDays($i);
                $out->push([
                    'date' => $day->toDateString(),
                    'label' => $day->timezone('Europe/Amsterdam')->format('d-m'),
                    'count' => 0,
                ]);
            }

            return $out;
        }

        $rows = Aanbod::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

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

    /**
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    private function messagesLastDays(int $days): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $out = collect();

        if (! Schema::hasTable('messages')) {
            for ($i = 0; $i < $days; $i++) {
                $day = $from->copy()->addDays($i);
                $out->push([
                    'date' => $day->toDateString(),
                    'label' => $day->timezone('Europe/Amsterdam')->format('d-m'),
                    'count' => 0,
                ]);
            }

            return $out;
        }

        $rows = Message::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

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

    /**
     * @return Collection<int, array{date: string, label: string, count: int}>
     */
    private function feedbackLastDays(int $days): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $out = collect();

        if (! Schema::hasTable('feedback')) {
            for ($i = 0; $i < $days; $i++) {
                $day = $from->copy()->addDays($i);
                $out->push([
                    'date' => $day->toDateString(),
                    'label' => $day->timezone('Europe/Amsterdam')->format('d-m'),
                    'count' => 0,
                ]);
            }

            return $out;
        }

        $rows = Feedback::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

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
            // Still return a full day series of zeros for chart alignment.
            $from = now()->subDays($days - 1)->startOfDay();
            $byDay = collect();
            for ($i = 0; $i < $days; $i++) {
                $day = $from->copy()->addDays($i);
                $byDay->push([
                    'date' => $day->toDateString(),
                    'label' => $day->timezone('Europe/Amsterdam')->format('d-m-Y'),
                    'total' => 0,
                    'pages' => collect(),
                ]);
            }

            return [
                'byDay' => $byDay->reverse()->values(),
                'top' => collect(),
                'total' => 0,
            ];
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
