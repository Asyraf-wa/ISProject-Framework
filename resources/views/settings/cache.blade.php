{{--
    Cache panel. A sibling of the settings form, never nested inside it.
    Which buttons appear is config('isproject.settings.cache_actions').
--}}
<div class="card is-guide">
    <div class="card-header">
        <h3 class="is-guide-title">
            <x-isproject::icon name="alert-circle" /> Caches
        </h3>
    </div>

    <div class="card-body">
        <p class="is-guide-intro">
            Laravel keeps compiled and cached copies of parts of the application.
            If a change you made is not showing up, clearing the matching cache is
            usually the fix. Nothing here deletes any of your data.
        </p>

        @if (empty($cacheActions))
            <p class="is-guide-intro mt-3 mb-0">No cache actions are enabled.</p>
        @else
            <div class="is-cache-actions">
                @foreach ($cacheActions as $action => $meta)
                    <form method="POST" action="{{ route('isproject.settings.cache') }}">
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">

                        <div class="is-cache-action">
                            <div>
                                <div class="is-cache-label">{{ $meta['label'] }}</div>
                                <div class="is-cache-help">{{ $meta['help'] }}</div>
                                <code class="is-cache-command">php artisan {{ $meta['command'] }}</code>
                            </div>

                            <button type="submit" class="btn btn-sm btn-outline-secondary">Clear</button>
                        </div>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</div>
