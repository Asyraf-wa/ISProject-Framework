@extends(config('isproject.layout'))

@section('title', 'Activity log')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Activity log</h2>
        </div>

        {{-- One click to the rows a security review actually wants: failed
             sign-ins, lockouts and password changes. --}}
        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2
                  {{ $security ? 'active' : '' }}"
           href="{{ $security ? route('isproject.activity.index') : route('isproject.activity.index', ['security' => 1]) }}">
            <x-isproject::icon name="alert-circle" />
            {{ $security ? 'Showing security events' : 'Security events only' }}
        </a>
    </div>

    @include('isproject::partials.alerts')

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('isproject.activity.index') }}" class="row g-2 align-items-end">
                @if ($security)
                    <input type="hidden" name="security" value="1">
                @endif

                <div class="col-12 col-md-3">
                    <label class="form-label" for="q">Search</label>
                    <input type="search" class="form-control" id="q" name="q"
                           value="{{ $filters['q'] ?? '' }}" placeholder="Person, description or IP">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label" for="event">Event</label>
                    <select class="form-select" id="event" name="event">
                        <option value="">All</option>
                        @foreach ($events as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['event'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="user">Person</label>
                    <select class="form-select" id="user" name="user">
                        <option value="">Anyone</option>
                        @foreach ($people as $id => $label)
                            <option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="from">From</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label" for="to">To</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <x-isproject::icon name="search" /> Filter
                    </button>
                    <a href="{{ route('isproject.activity.index') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>

        @if ($activities->isEmpty())
            <div class="is-empty">
                <p class="mb-2">Nothing recorded yet.</p>
                <p class="is-empty-icon">Signing in and out is the first thing that lands here.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Event</th>
                            <th>Person</th>
                            <th>Where from</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $activity->created_at?->format('d M Y, H:i') }}</div>
                                    <div class="small text-body-secondary">{{ $activity->created_at?->diffForHumans() }}</div>
                                </td>

                                <td>
                                    <span class="badge badge-soft-{{ $activity->tone() }}">{{ $activity->label() }}</span>

                                    {{-- Only when it adds something. For the framework's own
                                         events the description is the label, and printing both
                                         reads as a stutter. --}}
                                    @if ($activity->description !== $activity->label())
                                        <div class="small text-body-secondary">{{ $activity->description }}</div>
                                    @endif
                                </td>

                                <td>
                                    @if ($activity->user_label)
                                        {{ $activity->user_label }}
                                    @elseif ($email = data_get($activity->properties, 'email'))
                                        {{-- A failed sign-in has no signed-in user; the address
                                             that was tried is the only identity there is. --}}
                                        <span class="text-body-secondary">tried {{ $email }}</span>
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>

                                <td class="small text-body-secondary">{{ $activity->ip_address ?? '—' }}</td>

                                <td class="text-end">
                                    <a href="{{ route('isproject.activity.show', $activity) }}"
                                       class="btn btn-sm btn-icon btn-outline-secondary border-0"
                                       aria-label="View this entry">
                                        <x-isproject::icon name="eye" size="15" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($activities->hasPages())
        <div class="mt-3">{{ $activities->links() }}</div>
    @endif
@endsection
