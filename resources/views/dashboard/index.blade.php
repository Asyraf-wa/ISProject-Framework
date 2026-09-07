@extends(config('isproject.layout'))

@section('title', 'Dashboard')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">Overview</span>
            <h2 class="is-page-title">Dashboard</h2>
        </div>
    </div>

    @include('isproject::partials.alerts')

    @unless ($hasActivity)
        <div class="alert alert-warning d-flex gap-2">
            <x-isproject::icon name="alert-circle" />
            <div>
                <strong>The activity log table is not there yet.</strong>
                Run <code>php artisan migrate</code> and the charts below start filling as
                people use the system.
            </div>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        @foreach ($stats as $stat)
            <div class="col-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body is-stat">
                        <span class="is-stat-icon is-stat-{{ $stat['tone'] }}">
                            <x-isproject::icon :name="$stat['icon']" size="18" />
                        </span>

                        <div>
                            <div class="is-stat-value">{{ number_format($stat['value']) }}</div>
                            <div class="is-stat-label">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="is-guide-title">Sign-ins, last 14 days</h3>
                </div>
                <div class="card-body">
                    <x-isproject::chart :option="$signIns" height="300"
                                        aria-label="Successful and failed sign-ins per day over the last fourteen days" />
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="is-guide-title">What has been happening</h3>
                </div>
                <div class="card-body">
                    <x-isproject::chart :option="$events" height="300"
                                        aria-label="Activity by event type over the last fourteen days" />
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="is-guide-title">Records changed, by module</h3>
                </div>
                <div class="card-body">
                    <x-isproject::chart :option="$changes" height="280"
                                        aria-label="Number of audited changes per module over the last fourteen days" />
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <h3 class="is-guide-title mb-0">Latest activity</h3>

                    @if (\Illuminate\Support\Facades\Route::has('isproject.activity.index'))
                        <a href="{{ route('isproject.activity.index') }}" class="btn btn-sm btn-outline-secondary">
                            See all
                        </a>
                    @endif
                </div>

                @if ($recent->isEmpty())
                    <div class="is-empty">
                        <p class="mb-0">Nothing yet.</p>
                    </div>
                @else
                    <ul class="is-timeline">
                        @foreach ($recent as $activity)
                            <li>
                                <span class="badge badge-soft-{{ $activity->tone() }}">{{ $activity->label() }}</span>
                                <span class="is-timeline-who">{{ $activity->user_label ?? data_get($activity->properties, 'email', 'Nobody signed in') }}</span>
                                <span class="is-timeline-when">{{ $activity->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
