@extends(config('isproject.layout'))

@section('title', 'Generator')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">Development</span>
            <h2 class="is-page-title">Generator</h2>
        </div>
        <span class="badge badge-soft-warning">{{ app()->environment() }} only</span>
    </div>

    @include('isproject::partials.alerts')

    @if (session('generatorOutput'))
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Generator output</h6>
                @if (session('generatedRoute'))
                    <a href="{{ url(session('generatedRoute')) }}" class="btn btn-sm btn-primary">
                        Open /{{ session('generatedRoute') }}
                    </a>
                @endif
            </div>
            <div class="card-body">
                <pre class="mb-0 small text-body-secondary" style="white-space: pre-wrap">{{ session('generatorOutput') }}</pre>
            </div>
        </div>
    @endif

    @include('isproject::partials.errors')

    <div class="alert alert-warning d-flex" role="alert">
        <x-isproject::icon name="alert-circle" size="17" />
        <div>
            This page writes PHP files into <code>app/</code> and
            <code>resources/views/</code>. It is available in the
            <strong>{{ app()->environment() }}</strong> environment only, and is never
            registered in production. Generated policies allow everything — tighten them
            before the module goes near real data.
        </div>
    </div>

    @if ($tables->isEmpty())
        <div class="card">
            <div class="is-empty">
                <div class="is-empty-icon"><x-isproject::icon name="inbox" size="22" /></div>
                <p class="mb-1 fw-medium">No tables to generate from</p>
                <p class="mb-0 small">
                    Write a migration and run <code>php artisan migrate</code>, then reload this page.
                </p>
            </div>
        </div>
    @else
        <p class="text-body-secondary small">
            {{ $tables->count() }} {{ Str::plural('table', $tables->count()) }} on the
            <code>{{ $connection }}</code> connection. Framework tables are hidden by
            <code>isproject.ignored_tables</code>.
        </p>

        <div class="row g-3">
            @foreach ($tables as $t)
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h6 class="mb-0 d-flex align-items-center gap-2">
                                    <x-isproject::icon name="box" />
                                    {{ $t['table'] }}
                                </h6>
                                <small class="text-body-secondary">
                                    generates <code>{{ $t['model'] }}</code> at <code>/{{ $t['route'] }}</code>
                                </small>
                            </div>

                            @if ($t['existing'])
                                <span class="badge badge-soft-success">exists</span>
                            @else
                                <span class="badge badge-soft-secondary">not generated</span>
                            @endif
                        </div>

                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 small text-body-secondary mb-3">
                                <span>{{ $t['columns'] }} columns</span>
                                <span>{{ $t['rows'] === null ? '—' : number_format($t['rows']) }} rows</span>
                                <span>{{ $t['searchable'] }} searchable</span>
                                @if ($t['soft_deletes'])
                                    <span>soft deletes</span>
                                @endif
                                @if ($t['archivable'])
                                    <span>archiving</span>
                                @endif
                                @if ($t['relations'])
                                    <span>&rarr; {{ implode(', ', $t['relations']) }}</span>
                                @endif
                            </div>

                            @unless ($t['archivable'])
                                {{-- Writes the migration; running it stays a deliberate step. --}}
                                <form method="POST" action="{{ route('isproject.generator.archivable') }}" class="mb-3">
                                    @csrf
                                    <input type="hidden" name="table" value="{{ $t['table'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2">
                                        <x-isproject::icon name="inbox" size="14" /> Add archiving
                                    </button>
                                    <span class="small text-body-secondary ms-1">writes a migration</span>
                                </form>
                            @endunless

                            <div class="d-flex flex-wrap gap-1 mb-3">
                                @foreach ($t['preview'] as $column)
                                    <span class="badge badge-soft-secondary fw-normal">
                                        {{ $column['name'] }}
                                        <span class="opacity-75">{{ $column['type'] }}{{ $column['nullable'] ? '?' : '' }}</span>
                                    </span>
                                @endforeach
                                @if ($t['columns'] > count($t['preview']))
                                    <span class="badge badge-soft-secondary fw-normal">
                                        +{{ $t['columns'] - count($t['preview']) }} more
                                    </span>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('isproject.generator.store') }}">
                                @csrf
                                <input type="hidden" name="table" value="{{ $t['table'] }}">

                                <details class="mb-3">
                                    <summary class="small text-body-secondary" style="cursor: pointer">
                                        Options
                                    </summary>

                                    <div class="mt-3">
                                        <label class="form-label" for="model-{{ $t['table'] }}">Model name</label>
                                        <input type="text" class="form-control form-control-sm mb-3"
                                               id="model-{{ $t['table'] }}" name="model" value="{{ $t['model'] }}">

                                        <label class="form-label d-block">Generate</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($targets as $key => $label)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           id="{{ $t['table'] }}-{{ $key }}"
                                                           name="targets[]" value="{{ $key }}"
                                                           @checked(in_array($key, $defaults, true))>
                                                    <label class="form-check-label small" for="{{ $t['table'] }}-{{ $key }}">
                                                        {{ $label }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </details>

                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                                        <x-isproject::icon name="plus" />
                                        {{ $t['existing'] ? 'Regenerate' : 'Generate CRUD' }}
                                    </button>

                                    @if ($t['existing'])
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   id="force-{{ $t['table'] }}" name="force">
                                            <label class="form-check-label small text-danger" for="force-{{ $t['table'] }}">
                                                Overwrite my edits
                                            </label>
                                        </div>

                                        <a href="{{ url($t['route']) }}" class="btn btn-sm btn-link text-body-secondary ms-auto">
                                            Open
                                        </a>
                                    @endif
                                </div>

                                @if ($t['existing'])
                                    <p class="small text-body-secondary mb-0 mt-2">
                                        Already generated: {{ implode(', ', $t['existing']) }}. Existing
                                        files are skipped unless you tick overwrite.
                                    </p>
                                @endif
                            </form>

                            {{--
                                A sibling form, not nested: removal posts somewhere
                                else, and a form inside a form is invalid HTML that
                                browsers discard.
                            --}}
                            @if ($t['existing'])
                                <details class="is-remove">
                                    <summary>Remove this module</summary>

                                    <form method="POST" action="{{ route('isproject.generator.destroy') }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="model" value="{{ $t['model'] }}">

                                        <p class="small mb-2">These files are deleted:</p>
                                        <ul class="is-remove-list">
                                            @foreach ($t['removable'] as $file)
                                                <li><code>{{ $file }}</code></li>
                                            @endforeach
                                            <li>route lines pointing at <code>{{ $t['model'] }}Controller</code></li>
                                        </ul>

                                        <p class="small mb-2">
                                            The <code>{{ $t['table'] }}</code> table, its rows and its migration are
                                            <strong>not</strong> touched. Anything you edited in those files is gone,
                                            so check it is committed first.
                                        </p>

                                        <label class="form-label small" for="confirm-{{ $t['table'] }}">
                                            Type <code>{{ $t['model'] }}</code> to confirm
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="text" class="form-control form-control-sm"
                                                   id="confirm-{{ $t['table'] }}" name="confirm"
                                                   autocomplete="off" placeholder="{{ $t['model'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">
                                                <x-isproject::icon name="trash" /> Remove
                                            </button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
