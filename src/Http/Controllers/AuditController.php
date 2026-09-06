<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use IsProject\Framework\Models\Audit;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'in:created,updated,deleted,restored'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $audits = Audit::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('auditable_label', 'like', "%{$term}%")
                    ->orWhere('user_label', 'like', "%{$term}%")
                    ->orWhere('auditable_id', $term)
            ))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('auditable_type', $type))
            ->when($filters['event'] ?? null, fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($filters['user'] ?? null, fn (Builder $q, int $user) => $q->where('user_id', $user))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate((int) config('isproject.per_page', 15))
            ->withQueryString();

        return view('isproject::audit.index', [
            'audits' => $audits,
            'filters' => $filters,
            'types' => $this->types(),
            'actors' => $this->actors(),
        ]);
    }

    public function show(Audit $audit): View
    {
        return view('isproject::audit.show', ['audit' => $audit]);
    }

    /**
     * Everything the table has actually seen, for the type filter — rather than
     * a guess at which models use the trait.
     *
     * @return array<string, string>
     */
    private function types(): array
    {
        return Audit::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->mapWithKeys(fn (string $type) => [$type => Str::headline(class_basename($type))])
            ->all();
    }

    /**
     * People who appear in the log. Their label comes from the audit row, not
     * from the users table, so deleted accounts still show under their name.
     *
     * @return array<int, string>
     */
    private function actors(): array
    {
        return Audit::query()
            ->whereNotNull('user_id')
            ->select('user_id', 'user_label')
            ->distinct()
            ->orderBy('user_label')
            ->get()
            ->mapWithKeys(fn (Audit $audit) => [$audit->user_id => $audit->user_label ?: "User #{$audit->user_id}"])
            ->all();
    }
}
