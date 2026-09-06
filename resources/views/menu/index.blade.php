@extends(config('isproject.layout'))

@section('title', 'Menu')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Menu</h2>
        </div>

        <div class="d-flex gap-2">
            @if ($managed)
                <form method="POST" action="{{ route('isproject.menu.reset') }}"
                      onsubmit="return confirm('Delete every menu item and go back to the menu in config/isproject.php?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                        <x-isproject::icon name="arrow-left" /> Back to config
                    </button>
                </form>
            @endif

            <a href="{{ route('isproject.menu.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                <x-isproject::icon name="plus" /> New item
            </a>
        </div>
    </div>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    @unless ($managed)
        <div class="card is-guide mb-4">
            <div class="card-body">
                <h3 class="is-guide-title mb-2">
                    <x-isproject::icon name="info" /> The sidebar is coming from config
                </h3>

                <p class="is-guide-intro">
                    Right now the menu is the array in <code>config/isproject.php</code> —
                    {{ $configCount }} {{ \Illuminate\Support\Str::plural('entry', $configCount) }}, editable only
                    in a text editor. Copy it in here and you can reorder, rename, re-icon and nest it from this
                    screen instead. Nothing changes for anyone until you do.
                </p>

                <form method="POST" action="{{ route('isproject.menu.import') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <x-isproject::icon name="save" /> Manage the menu here
                    </button>
                </form>
            </div>
        </div>
    @endunless

    @include('isproject::menu.partials.unlisted', ['unlisted' => $unlisted])

    @if ($items->isEmpty())
        <div class="card">
            <div class="is-empty">
                <p class="mb-2">Nothing in the menu yet.</p>
                <p class="is-empty-icon">Import what is in config, or add the first item.</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h3 class="is-guide-title mb-0">Sidebar order</h3>

                {{-- Announced politely so a screen reader hears the save without
                     losing its place in the list. --}}
                <span class="small text-body-secondary is-menu-status" data-is-menu-status role="status" aria-live="polite"></span>
            </div>

            <div class="card-body">
                <p class="is-guide-intro">
                    Drag a row by its handle to reorder it, or use the arrows — they do the same thing and work
                    from the keyboard. Drop a row onto the indented area under another to make it a sub-item.
                </p>

                <ul class="is-menu-tree" data-is-menu-tree data-is-menu-url="{{ route('isproject.menu.reorder') }}" data-is-menu-list>
                    @foreach ($items as $item)
                        @include('isproject::menu.partials.row', ['item' => $item, 'child' => false])
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endsection
