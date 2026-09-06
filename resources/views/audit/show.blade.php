@extends(config('isproject.layout'))

@section('title', 'Audit entry')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.audit.index') }}">Audit trail</a>
            </span>
            <h2 class="is-page-title">
                {{ $audit->subjectName() }}
                {{ $audit->auditable_label ? '— '.$audit->auditable_label : '#'.$audit->auditable_id }}
            </h2>
        </div>

        <a href="{{ route('isproject.audit.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <x-isproject::icon name="arrow-left" /> Back to the trail
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <h3 class="is-guide-title">What changed</h3>
                    <x-isproject::audit-event :event="$audit->event" />
                </div>

                @php $changes = $audit->differences(); @endphp

                @if ($changes === [])
                    <div class="is-empty">
                        <p class="mb-2">No attribute values were recorded.</p>
                        <p class="is-empty-icon">
                            Everything on this record is either ignored or redacted by config.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Before</th>
                                    <th>After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($changes as $change)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $change['label'] }}</div>
                                            <div class="is-matrix-key">{{ $change['key'] }}</div>
                                        </td>
                                        <td class="is-diff-cell is-diff-old">
                                            {{ $change['old'] ?? '—' }}
                                        </td>
                                        <td class="is-diff-cell is-diff-new">
                                            {{ $change['new'] ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title">
                        <x-isproject::icon name="info" /> Context
                    </h3>
                </div>

                <div class="card-body">
                    <dl class="is-guide-list">
                        <dt>When</dt>
                        <dd>
                            {{ $audit->created_at?->format('d M Y, H:i:s') }}
                            <span class="d-block">{{ $audit->created_at?->diffForHumans() }}</span>
                        </dd>

                        <dt>Who</dt>
                        <dd>
                            {{ $audit->user_label ?: 'System' }}
                            @if ($audit->user_id)
                                <span class="d-block">User #{{ $audit->user_id }}</span>
                            @endif
                        </dd>

                        <dt>Record</dt>
                        <dd>
                            {{ $audit->auditable_type }}
                            <span class="d-block">#{{ $audit->auditable_id }}</span>
                        </dd>

                        @if ($audit->url)
                            <dt>Where</dt>
                            <dd class="text-break">{{ $audit->url }}</dd>
                        @endif

                        @if ($audit->ip_address)
                            <dt>From</dt>
                            <dd>{{ $audit->ip_address }}</dd>
                        @endif

                        @if ($audit->user_agent)
                            <dt>Browser</dt>
                            <dd class="text-break small">{{ $audit->user_agent }}</dd>
                        @endif
                    </dl>

                    <h4 class="is-guide-heading">Note</h4>
                    <p class="is-guide-intro mb-0">
                        Values marked <code>••••••••</code> are redacted by
                        <code>isproject.audit.redacted_attributes</code>. The trail records that
                        they changed, never what they became.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
