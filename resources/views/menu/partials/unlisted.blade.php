{{--
    Modules whose listing route exists but which nothing in the menu points at.

    This is the "I generated it and it is not in the sidebar" case. The route is
    already there to be discovered, so it is offered rather than left for
    somebody to guess the route name and type it in.
--}}
@if (! empty($unlisted))
    <div class="card is-guide mb-4">
        <div class="card-header">
            <h3 class="is-guide-title">
                <x-isproject::icon name="alert-circle" />
                {{ count($unlisted) }} {{ \Illuminate\Support\Str::plural('module', count($unlisted)) }} not in the menu
            </h3>
        </div>

        <div class="card-body">
            <p class="is-guide-intro">
                These have a listing page but nothing links to it. Adding one puts it at the bottom of the
                menu with the matching permission already set — drag it where you want it afterwards.
            </p>

            <div class="d-flex flex-wrap gap-2">
                @foreach ($unlisted as $module)
                    <form method="POST" action="{{ route('isproject.menu.adopt') }}">
                        @csrf
                        <input type="hidden" name="route" value="{{ $module['route'] }}">
                        <input type="hidden" name="label" value="{{ $module['label'] }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2">
                            <x-isproject::icon name="plus" size="14" /> {{ $module['label'] }}
                            <code class="small opacity-75">{{ $module['route'] }}</code>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endif
