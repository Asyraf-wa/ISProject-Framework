@extends(config('isproject.layout'))

@section('title', 'Manual')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">Help</span>
            <h2 class="is-page-title">Manual</h2>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            {{-- GET, so a search is a shareable address and the back button
                 behaves the way people expect it to. --}}
            <form method="GET" action="{{ route('isproject.manual.index') }}" role="search">
                <label class="form-label" for="q">Search the manual</label>

                <div class="d-flex gap-2">
                    <input type="search" class="form-control" id="q" name="q"
                           value="{{ $search }}" placeholder="archiving, permissions, logo…">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <x-isproject::icon name="search" /> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($results !== null)
        <div class="is-page-header">
            <h3 class="is-guide-title">
                {{ count($results) }}
                {{ \Illuminate\Support\Str::plural('chapter', count($results)) }}
                mention “{{ $search }}”
            </h3>

            <a href="{{ route('isproject.manual.index') }}" class="btn btn-sm btn-outline-secondary">
                Clear
            </a>
        </div>

        @forelse ($results as $result)
            <a class="card is-manual-card mb-3" href="{{ route('isproject.manual.show', $result['slug']) }}">
                <div class="card-body">
                    <h3 class="is-manual-card-title">
                        <x-isproject::icon :name="$result['icon']" />
                        {{ $result['title'] }}
                        <span class="badge badge-soft-secondary">
                            {{ $result['hits'] }} {{ \Illuminate\Support\Str::plural('mention', $result['hits']) }}
                        </span>
                    </h3>
                    <p class="is-manual-snippet mb-0">{{ $result['snippet'] }}</p>
                </div>
            </a>
        @empty
            <div class="card">
                <div class="is-empty">
                    <p class="mb-2">Nothing in the manual mentions “{{ $search }}”.</p>
                    <p class="is-empty-icon">Try a single word — “archive” rather than “how do I archive”.</p>
                </div>
            </div>
        @endforelse
    @else
        <div class="row g-3">
            @foreach ($chapters as $chapter)
                <div class="col-12 col-md-6">
                    <a class="card is-manual-card h-100" href="{{ route('isproject.manual.show', $chapter['slug']) }}">
                        <div class="card-body">
                            <h3 class="is-manual-card-title">
                                <x-isproject::icon :name="$chapter['icon']" />
                                {{ $chapter['title'] }}
                            </h3>
                            <p class="is-manual-snippet mb-0">{{ $chapter['summary'] }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
