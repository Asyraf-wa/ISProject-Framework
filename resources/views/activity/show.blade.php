@extends(config('isproject.layout'))

@section('title', $activity->label())

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.activity.index') }}">Activity log</a>
            </span>
            <h2 class="is-page-title">{{ $activity->label() }}</h2>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <dl class="is-detail-list">
                        <div>
                            <dt>What happened</dt>
                            <dd>
                                <span class="badge badge-soft-{{ $activity->tone() }}">{{ $activity->label() }}</span>
                                <div class="mt-1">{{ $activity->description }}</div>
                            </dd>
                        </div>

                        <div>
                            <dt>When</dt>
                            <dd>
                                {{ $activity->created_at?->format('d M Y, H:i:s') }}
                                <span class="text-body-secondary">({{ $activity->created_at?->diffForHumans() }})</span>
                            </dd>
                        </div>

                        <div>
                            <dt>Person</dt>
                            <dd>{{ $activity->user_label ?? 'Nobody signed in' }}</dd>
                        </div>

                        <div>
                            <dt>Request</dt>
                            <dd><code>{{ $activity->url ?? '—' }}</code></dd>
                        </div>

                        <div>
                            <dt>IP address</dt>
                            <dd>{{ $activity->ip_address ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt>Browser</dt>
                            <dd class="small">{{ $activity->user_agent ?? '—' }}</dd>
                        </div>

                        @if ($activity->subject_type)
                            <div>
                                <dt>About</dt>
                                <dd><code>{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</code></dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            @if (! empty($activity->properties))
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="is-guide-title">Details</h3>
                    </div>

                    <div class="card-body">
                        <dl class="is-detail-list">
                            @foreach ($activity->properties as $key => $value)
                                <div>
                                    <dt>{{ \Illuminate\Support\Str::headline((string) $key) }}</dt>
                                    <dd>
                                        @if (is_bool($value))
                                            {{ $value ? 'Yes' : 'No' }}
                                        @elseif (is_array($value))
                                            <code>{{ json_encode($value) }}</code>
                                        @else
                                            {{ $value === null || $value === '' ? '—' : $value }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title">
                        <x-isproject::icon name="info" /> Activity or audit?
                    </h3>
                </div>

                <div class="card-body">
                    <p class="is-guide-intro">
                        This log records what people <strong>did</strong> — signing in, failing to
                        sign in, changing a password. What <strong>changed</strong> in your data is
                        the audit trail, and it is a separate screen.
                    </p>

                    @if (\Illuminate\Support\Facades\Route::has('isproject.audit.index'))
                        <a href="{{ route('isproject.audit.index') }}"
                           class="btn btn-sm btn-outline-secondary mt-3 d-inline-flex align-items-center gap-2">
                            <x-isproject::icon name="list" /> Open the audit trail
                        </a>
                    @endif

                    @if ($activity->isSecurityEvent())
                        <div class="is-callout is-callout-warning mt-3">
                            <p class="is-callout-title">Security event</p>
                            <p class="mb-0">
                                Worth a second look if there are several close together from the
                                same address.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
