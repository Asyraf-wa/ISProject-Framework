<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use IsProject\Framework\Models\Activity;

/**
 * The activity log.
 *
 * Read-only: rows are written by what people do, and an activity log somebody
 * can edit is not one. Removing old entries is a scheduled prune, not a button.
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:40'],
            'user' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'security' => ['nullable', 'boolean'],
        ]);

        $activities = Activity::query()
            ->when($filters['event'] ?? null, fn (Builder $q, $event) => $q->where('event', $event))
            ->when($filters['user'] ?? null, fn (Builder $q, $user) => $q->where('user_id', $user))
            ->when($request->boolean('security'), fn (Builder $q) => $q->whereIn('event', Activity::SECURITY))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('user_label', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
            ))
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) config('isproject.per_page', 15))
            ->withQueryString();

        return view('isproject::activity.index', [
            'activities' => $activities,
            'events' => Activity::eventOptions(),
            'people' => $this->people(),
            'filters' => $filters,
            'security' => $request->boolean('security'),
        ]);
    }

    public function show(int $activity): View
    {
        return view('isproject::activity.show', [
            'activity' => Activity::query()->findOrFail($activity),
        ]);
    }

    /**
     * People who appear in the log, for the filter. Taken from the log itself
     * rather than the users table: somebody deleted last month still has rows,
     * and leaving them out of the filter hides them.
     *
     * @return array<int, string>
     */
    private function people(): array
    {
        return Activity::query()
            ->whereNotNull('user_id')
            ->whereNotNull('user_label')
            ->select('user_id', 'user_label')
            ->distinct()
            ->orderBy('user_label')
            ->pluck('user_label', 'user_id')
            ->all();
    }
}
