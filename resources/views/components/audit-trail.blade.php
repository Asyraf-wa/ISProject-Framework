{{--
    The history of one record, for dropping into a show screen:

        <x-isproject::audit-trail :model="$product" />

    Renders nothing at all when the model is not audited, so it is safe to add
    to a shared layout before every model has the trait.
--}}
@props(['model', 'limit' => 10])

@php
    $entries = method_exists($model, 'auditTrail')
        ? $model->auditTrail()->limit($limit)->get()
        : collect();
@endphp

@if ($entries->isNotEmpty())
    <div class="card is-guide">
        <div class="card-header">
            <h3 class="is-guide-title">
                <x-isproject::icon name="list" /> History
            </h3>
        </div>

        <div class="card-body">
            <ol class="is-timeline">
                @foreach ($entries as $entry)
                    <li class="is-timeline-item is-timeline-{{ $entry->event }}">
                        <div class="d-flex flex-wrap gap-2 align-items-baseline">
                            <x-isproject::audit-event :event="$entry->event" />
                            <span class="small text-body-secondary">
                                {{ $entry->created_at?->diffForHumans() }} by {{ $entry->user_label ?: 'System' }}
                            </span>
                        </div>

                        @php $changes = $entry->differences(); @endphp

                        @if ($changes !== [])
                            <dl class="is-diff mt-2">
                                @foreach (array_slice($changes, 0, 4) as $change)
                                    <dt>{{ $change['label'] }}</dt>
                                    <dd>
                                        @if ($entry->event === 'updated')
                                            <span class="is-diff-old">{{ $change['old'] ?? '—' }}</span>
                                            <span class="is-diff-arrow" aria-label="changed to">&rarr;</span>
                                        @endif
                                        <span class="is-diff-new">{{ ($entry->event === 'deleted' ? $change['old'] : $change['new']) ?? '—' }}</span>
                                    </dd>
                                @endforeach
                            </dl>

                            @if (count($changes) > 4)
                                <p class="small text-body-secondary mb-0">
                                    <a href="{{ route('isproject.audit.show', $entry) }}">
                                        and {{ count($changes) - 4 }} more
                                    </a>
                                </p>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
@endif
