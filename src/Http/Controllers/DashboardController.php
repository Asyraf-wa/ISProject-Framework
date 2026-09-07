<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use IsProject\Framework\Models\Activity;
use IsProject\Framework\Models\Audit;
use IsProject\Framework\Models\Role;
use Throwable;

/**
 * A dashboard built only from tables this package owns.
 *
 * It deliberately knows nothing about the application's own models: a starting
 * dashboard that assumes there is an "orders" table is a dashboard that breaks
 * on every project except the one it was written for. What it shows is who has
 * been signing in and what has been changing, which is true everywhere.
 *
 * Replace it with your own by pointing the menu somewhere else; nothing else in
 * the framework links here.
 */
class DashboardController extends Controller
{
    /** Days of history on the trend chart. */
    private const WINDOW = 14;

    public function index(): View
    {
        return view('isproject::dashboard.index', [
            'stats' => $this->stats(),
            'signIns' => $this->signInTrend(),
            'events' => $this->activityMix(),
            'changes' => $this->changesByModule(),
            'recent' => $this->recent(),
            'hasActivity' => $this->tableExists('isproject_activities'),
        ]);
    }

    /**
     * The four numbers worth a glance.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stats(): array
    {
        $today = Carbon::today();

        return [
            [
                'label' => 'Sign-ins today',
                'value' => $this->count(fn () => Activity::query()
                    ->where('event', Activity::LOGIN)
                    ->whereDate('created_at', $today)
                    ->count()),
                'icon' => 'user',
                'tone' => 'primary',
            ],
            [
                'label' => 'Failed sign-ins today',
                'value' => $this->count(fn () => Activity::query()
                    ->where('event', Activity::LOGIN_FAILED)
                    ->whereDate('created_at', $today)
                    ->count()),
                'icon' => 'alert-circle',
                'tone' => 'warning',
            ],
            [
                'label' => 'Changes today',
                'value' => $this->count(fn () => Audit::query()->whereDate('created_at', $today)->count()),
                'icon' => 'list',
                'tone' => 'success',
            ],
            [
                'label' => 'Roles',
                'value' => $this->count(fn () => Role::query()->count()),
                'icon' => 'check-circle',
                'tone' => 'secondary',
            ],
        ];
    }

    /**
     * Successful and failed sign-ins per day, as an ECharts option.
     *
     * Every day in the window is present even when nothing happened: a line
     * chart that skips empty days draws a straight line through a quiet week
     * and makes it look busy.
     *
     * @return array<string, mixed>
     */
    private function signInTrend(): array
    {
        $days = collect(range(self::WINDOW - 1, 0))
            ->map(fn (int $back) => Carbon::today()->subDays($back));

        $rows = $this->rows(fn () => Activity::query()
            ->selectRaw('DATE(created_at) as day, event, COUNT(*) as total')
            ->whereIn('event', [Activity::LOGIN, Activity::LOGIN_FAILED])
            ->where('created_at', '>=', Carbon::today()->subDays(self::WINDOW - 1))
            ->groupBy('day', 'event')
            ->get());

        $series = function (string $event) use ($days, $rows) {
            return $days->map(function (Carbon $day) use ($rows, $event) {
                $match = $rows->first(
                    fn ($row) => (string) $row->day === $day->toDateString() && $row->event === $event
                );

                return (int) ($match->total ?? 0);
            })->all();
        };

        return [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['data' => ['Signed in', 'Failed'], 'bottom' => 0],
            'grid' => ['left' => 40, 'right' => 16, 'top' => 16, 'bottom' => 48],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $days->map(fn (Carbon $day) => $day->format('d M'))->all(),
            ],
            'yAxis' => ['type' => 'value', 'minInterval' => 1],
            'series' => [
                [
                    'name' => 'Signed in',
                    'type' => 'line',
                    'smooth' => true,
                    'areaStyle' => ['opacity' => 0.12],
                    'data' => $series(Activity::LOGIN),
                ],
                [
                    'name' => 'Failed',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => $series(Activity::LOGIN_FAILED),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function activityMix(): array
    {
        $rows = $this->rows(fn () => Activity::query()
            ->selectRaw('event, COUNT(*) as total')
            ->where('created_at', '>=', Carbon::today()->subDays(self::WINDOW - 1))
            ->groupBy('event')
            ->orderByDesc('total')
            ->get());

        $data = $rows->map(fn ($row) => [
            'name' => (new Activity(['event' => $row->event]))->label(),
            'value' => (int) $row->total,
        ])->all();

        return [
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['bottom' => 0, 'type' => 'scroll'],
            'series' => [[
                'type' => 'pie',
                // A doughnut rather than a pie: the hole makes the slice
                // boundaries easier to compare than wedges meeting at a point.
                'radius' => ['45%', '70%'],
                'center' => ['50%', '45%'],
                'avoidLabelOverlap' => true,
                'itemStyle' => ['borderWidth' => 2, 'borderColor' => 'transparent'],
                'label' => ['show' => false],
                'data' => $data,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function changesByModule(): array
    {
        $rows = $this->rows(fn () => Audit::query()
            ->selectRaw('auditable_type, COUNT(*) as total')
            ->where('created_at', '>=', Carbon::today()->subDays(self::WINDOW - 1))
            ->groupBy('auditable_type')
            ->orderByDesc('total')
            ->limit(8)
            ->get());

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'grid' => ['left' => 110, 'right' => 24, 'top' => 8, 'bottom' => 24],
            'xAxis' => ['type' => 'value', 'minInterval' => 1],
            'yAxis' => [
                'type' => 'category',
                'data' => $rows->map(fn ($row) => class_basename((string) $row->auditable_type))->reverse()->values()->all(),
            ],
            'series' => [[
                'type' => 'bar',
                'barMaxWidth' => 18,
                'itemStyle' => ['borderRadius' => [0, 4, 4, 0]],
                'data' => $rows->map(fn ($row) => (int) $row->total)->reverse()->values()->all(),
            ]],
        ];
    }

    /** @return Collection<int, Activity> */
    private function recent()
    {
        return $this->rows(fn () => Activity::query()->latest('created_at')->latest('id')->limit(8)->get());
    }

    /**
     * Query helpers that survive a missing table.
     *
     * The dashboard is often the first screen somebody opens, and opening it
     * before `php artisan migrate` should show empty charts, not a stack trace.
     */
    private function count(callable $query): int
    {
        try {
            return (int) $query();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return Collection<int, mixed> */
    private function rows(callable $query)
    {
        try {
            return $query();
        } catch (Throwable) {
            return collect();
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
