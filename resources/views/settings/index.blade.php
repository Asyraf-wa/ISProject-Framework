{{--
    Site configuration.

    The form is built from config('isproject.settings.groups'), so adding a
    setting there makes it appear here — this file does not need editing to add
    a field.

    The two columns are separate <form> elements, not one wrapping the other:
    the cache panel posts somewhere else, and a form inside a form is invalid
    HTML that browsers silently discard.
--}}
@extends(config('isproject.layout'))

@section('title', 'Settings')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Settings</h2>
        </div>
    </div>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    @unless ($storageLinked)
        <div class="alert alert-warning d-flex gap-2">
            <x-isproject::icon name="alert-circle" />
            <div>
                <strong>Uploads will not be visible yet.</strong>
                The <code>public/storage</code> link is missing, so a logo or favicon would
                save but render as a broken image. Run <code>php artisan storage:link</code> once.
            </div>
        </div>
    @endunless

    @if ($robotsFileShadows)
        <div class="alert alert-warning d-flex gap-2">
            <x-isproject::icon name="alert-circle" />
            <div>
                <strong>A <code>public/robots.txt</code> file is overruling the indexing setting.</strong>
                The web server serves that file directly, so the one this package generates is never
                reached. Delete <code>public/robots.txt</code> to let the setting take effect.
                <span class="d-block mt-1">
                    The <code>noindex</code> meta tag still works either way, and it is the control that
                    actually keeps pages out of a search index.
                </span>
            </div>
        </div>
    @endif

    @php $isPwa = app(\IsProject\Framework\Support\Pwa::class); @endphp

    {{-- Shown only once somebody has switched it on: before that it is advice
         about a feature nobody asked for. --}}
    @if ($isPwa->enabled() && ! $isPwa->ready())
        <div class="alert alert-warning d-flex gap-2">
            <x-isproject::icon name="alert-circle" />
            <div>
                <strong>Installing as an app is switched on, but browsers will not offer it yet.</strong>

                <ul class="is-pwa-checks">
                    @foreach ($isPwa->checks() as $check)
                        <li @class(['is-pwa-check', 'is-pwa-check-ok' => $check['ok']])>
                            <strong>{{ $check['label'] }}</strong> — {{ $check['detail'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('isproject.settings.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @foreach ($groups as $group)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="is-guide-title">
                                <x-isproject::icon :name="$group['icon']" /> {{ $group['label'] }}
                            </h3>
                            @if ($group['description'])
                                <p class="is-guide-intro mt-1">{{ $group['description'] }}</p>
                            @endif
                        </div>

                        <div class="card-body">
                            <div class="row">
                                @foreach ($group['fields'] as $key => $field)
                                    @include('isproject::settings.field', [
                                        'field' => $field,
                                        'value' => $values[$key] ?? null,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                    <x-isproject::icon name="save" /> Save settings
                </button>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            @include('isproject::settings.cache')
        </div>
    </div>
@endsection
