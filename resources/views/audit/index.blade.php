@extends(config('isproject.layout'))

@section('title', 'Audit trail')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Audit trail</h2>
        </div>
    </div>

    @include('isproject::partials.alerts')

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('isproject.audit.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="q">Search</label>
                    <input type="search" class="form-control" id="q" name="q"
                           value="{{ $filters['q'] ?? '' }}" placeholder="Record or person">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="type">Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="event">Event</label>
                    <select class="form-select" id="event" name="event">
                        <option value="">All</option>
                        @foreach (['created', 'updated', 'deleted', 'restored'] as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ ucfirst($event) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="user">Person</label>
                    <select class="form-select" id="user" name="user">
                        <option value="">Anyone</option>
                        @foreach ($actors as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-3 col-md-1">
                    <label class="form-label" for="from">From</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>

                <div class="col-3 col-md-1">
                    <label class="form-label" for="to">To</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>

                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
                </div>
            </form>

            @if (array_filter($filters))
                <a href="{{ route('isproject.audit.index') }}" class="btn btn-link btn-sm text-body-secondary ps-0 mt-2">
                    Clear filters
                </a>
            @endif
        </div>

        @if ($audits->isEmpty())
            <div class="is-empty">
                <p class="mb-2">Nothing recorded yet.</p>
                <p class="is-empty-icon">
                    Add <code>use IsProject\Framework\Concerns\Auditable;</code> to a model,
                    then change a record.
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Event</th>
                            <th>Record</th>
                            <th>Changed</th>
                            <th>By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            <tr>
                                <td class="text-nowrap">
                                    <div>{{ $audit->created_at?->format('d M Y, H:i') }}</div>
                                    <div class="small text-body-secondary">{{ $audit->created_at?->diffForHumans() }}</div>
                                </td>
                                <td>
                                    <x-isproject::audit-event :event="$audit->event" />
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $audit->auditable_label ?: '#'.$audit->auditable_id }}</div>
                                    <div class="small text-body-secondary">
                                        {{ $audit->subjectName() }} #{{ $audit->auditable_id }}
                                    </div>
                                </td>
                                <td>
                                    @php $keys = $audit->changedKeys(); @endphp

                                    @if ($keys === [])
                                        <span class="text-body-secondary">—</span>
                                    @else
                                        <span class="small">
                                            {{ implode(', ', array_slice($keys, 0, 3)) }}@if (count($keys) > 3)
                                                <span class="text-body-secondary">and {{ count($keys) - 3 }} more</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $audit->user_label ?: 'System' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('isproject.audit.show', $audit) }}"
                                       class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($audits->hasPages())
                <div class="card-body">{{ $audits->links() }}</div>
            @endif
        @endif
    </div>
@endsection
