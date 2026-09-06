@extends(config('isproject.layout'))

@section('title', $chapter['title'])

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.manual.index') }}">Manual</a>
            </span>
            <h2 class="is-page-title">{{ $chapter['title'] }}</h2>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body">
                    {{--
                        Unescaped on purpose: this is Markdown rendered from
                        files that ship with the package, and the converter is
                        configured to escape any raw HTML inside them.
                    --}}
                    <div class="is-manual-prose">{!! $chapter['html'] !!}</div>
                </div>
            </div>

            <nav class="is-manual-nav" aria-label="Chapters">
                <div>
                    @if ($previous)
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"
                           href="{{ route('isproject.manual.show', $previous['slug']) }}">
                            <x-isproject::icon name="arrow-left" /> {{ $previous['title'] }}
                        </a>
                    @endif
                </div>

                <div>
                    @if ($next)
                        <a class="btn btn-primary d-inline-flex align-items-center gap-2"
                           href="{{ route('isproject.manual.show', $next['slug']) }}">
                            {{ $next['title'] }} <x-isproject::icon name="chevron-right" />
                        </a>
                    @endif
                </div>
            </nav>
        </div>

        <div class="col-12 col-lg-4">
            @if (! empty($chapter['contents']))
                <div class="card is-guide mb-4">
                    <div class="card-header">
                        <h3 class="is-guide-title">On this page</h3>
                    </div>

                    <div class="card-body">
                        <ul class="is-manual-toc">
                            @foreach ($chapter['contents'] as $heading)
                                <li><a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title">All chapters</h3>
                </div>

                <div class="card-body">
                    <ul class="is-manual-toc">
                        @foreach ($chapters as $other)
                            <li>
                                <a href="{{ route('isproject.manual.show', $other['slug']) }}"
                                   @class(['fw-semibold' => $other['slug'] === $chapter['slug']])
                                   @if ($other['slug'] === $chapter['slug']) aria-current="page" @endif>
                                    {{ $other['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
