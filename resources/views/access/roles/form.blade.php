{{--
    The permission matrix.

    Rows are modules and columns are abilities, both discovered from the live
    route collection — so a module generated this morning is already here. The
    checkbox value is the route name, which is also the permission name and what
    the enforcement middleware matches on.
--}}
@extends(config('isproject.layout'))

@section('title', $role->exists ? 'Edit role' : 'New role')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.roles.index') }}">Roles</a>
            </span>
            <h2 class="is-page-title">{{ $role->exists ? $role->name : 'New role' }}</h2>
        </div>

        <a href="{{ route('isproject.roles.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <x-isproject::icon name="arrow-left" /> Back to roles
        </a>
    </div>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')
    @include('isproject::access.partials.unsynced', ['unsynced' => $unsynced])

    <form method="POST"
          action="{{ $role->exists ? route('isproject.roles.update', $role) : route('isproject.roles.store') }}">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="name">
                                    Name <span class="is-required-mark" aria-hidden="true">*</span>
                                </label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $role->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="description">Description</label>
                                <input type="text" class="form-control" id="description" name="description"
                                       value="{{ old('description', $role->description) }}">
                            </div>

                            <div class="col-12">
                                <input type="hidden" name="is_super_admin" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_super_admin"
                                           name="is_super_admin" value="1"
                                           @checked(old('is_super_admin', $role->is_super_admin))>
                                    <label class="form-check-label" for="is_super_admin">
                                        Super admin — passes every check
                                    </label>
                                </div>
                                <div class="form-text">
                                    Ignores the matrix below entirely. Keep at least one, or a wrong tick
                                    could lock everyone out of this screen.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" id="matrix">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <div>
                            <h3 class="is-guide-title">Permissions</h3>
                            <p class="is-guide-intro mt-1">
                                One row per module, one column per action. Read from your routes.
                            </p>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-is-matrix="all">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-is-matrix="none">Clear</button>
                        </div>
                    </div>

                    @if (empty($modules))
                        <div class="is-empty">
                            <p class="mb-2">No named routes to permit.</p>
                            <p class="is-empty-icon">Generate a module with <code>php artisan isproject:crud</code> and come back.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 is-matrix">
                                <thead>
                                    <tr>
                                        <th class="is-matrix-module">Module</th>
                                        @foreach ($columns as $ability)
                                            <th class="is-matrix-cell">
                                                <button type="button" class="is-matrix-head" data-is-matrix-column="{{ $ability }}">
                                                    {{ \Illuminate\Support\Str::headline($ability) }}
                                                </button>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($modules as $module)
                                        <tr>
                                            <td class="is-matrix-module">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox"
                                                           id="row-{{ $module['key'] }}" data-is-matrix-row="{{ $module['key'] }}">
                                                    <label class="form-check-label fw-semibold" for="row-{{ $module['key'] }}">
                                                        {{ $module['label'] }}
                                                    </label>
                                                </div>
                                                <div class="is-matrix-key">{{ $module['key'] }}</div>
                                            </td>

                                            @foreach ($columns as $ability)
                                                <td class="is-matrix-cell">
                                                    @isset($module['abilities'][$ability])
                                                        @php $permission = $module['abilities'][$ability]; @endphp
                                                        <input class="form-check-input"
                                                               type="checkbox"
                                                               name="permissions[]"
                                                               value="{{ $permission['name'] }}"
                                                               data-is-matrix-of="{{ $module['key'] }}"
                                                               data-is-matrix-ability="{{ $ability }}"
                                                               title="{{ $permission['methods'] }} {{ $permission['uri'] }}"
                                                               aria-label="{{ $module['label'] }} — {{ $permission['label'] }}"
                                                               @checked(in_array($permission['name'], old('permissions', $granted), true))>
                                                    @else
                                                        <span class="is-matrix-empty" aria-hidden="true">—</span>
                                                    @endisset
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                        <x-isproject::icon name="save" /> {{ $role->exists ? 'Save role' : 'Create role' }}
                    </button>
                    <a href="{{ route('isproject.roles.index') }}" class="btn btn-link text-body-secondary">Cancel</a>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card is-guide">
                    <div class="card-header">
                        <h3 class="is-guide-title">
                            <x-isproject::icon name="info" /> How this works
                        </h3>
                    </div>

                    <div class="card-body">
                        <p class="is-guide-intro">
                            Every named route in your application is one permission. Nothing is typed
                            by hand, so a module you generate today appears here as soon as you
                            press <em>Rescan routes</em>.
                        </p>

                        <h4 class="is-guide-heading">Enforcing it</h4>
                        <dl class="is-guide-list">
                            <dt>On routes</dt>
                            <dd>Add the <code>isproject.permission</code> middleware to a route group; it matches the route name against this matrix.</dd>
                            <dt>In Blade</dt>
                            <dd><code>&#64;can('products.create')</code> works, because the check runs through Laravel's own Gate.</dd>
                            <dt>In the menu</dt>
                            <dd>Give a menu entry <code>'can' =&gt; 'products.index'</code> and it hides itself for roles without it.</dd>
                        </dl>

                        <h4 class="is-guide-heading">Worth knowing</h4>
                        <dl class="is-guide-list">
                            <dt>Page level, not row level</dt>
                            <dd>This grants "may edit products", not "may edit their own products". Use the generated policy for that.</dd>
                            <dt>Super admin</dt>
                            <dd>Bypasses all of it. That is the safety net that stops a wrong tick locking you out.</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            // Row, column and select-all toggles. Plain delegation — the page
            // works without this, it just takes more clicking.
            (function () {
                var matrix = document.getElementById('matrix');

                if (!matrix) {
                    return;
                }

                function boxes(selector) {
                    return Array.prototype.slice.call(matrix.querySelectorAll(selector));
                }

                function syncRow(key) {
                    var row = matrix.querySelector('[data-is-matrix-row="' + key + '"]');
                    var cells = boxes('[data-is-matrix-of="' + key + '"]');

                    if (!row || !cells.length) {
                        return;
                    }

                    var ticked = cells.filter(function (c) { return c.checked; }).length;

                    row.checked = ticked === cells.length;
                    row.indeterminate = ticked > 0 && ticked < cells.length;
                }

                function syncAllRows() {
                    boxes('[data-is-matrix-row]').forEach(function (row) {
                        syncRow(row.getAttribute('data-is-matrix-row'));
                    });
                }

                matrix.addEventListener('change', function (event) {
                    var row = event.target.closest('[data-is-matrix-row]');

                    if (row) {
                        var key = row.getAttribute('data-is-matrix-row');
                        boxes('[data-is-matrix-of="' + key + '"]').forEach(function (cell) {
                            cell.checked = row.checked;
                        });
                        syncRow(key);

                        return;
                    }

                    if (event.target.closest('[data-is-matrix-of]')) {
                        syncRow(event.target.getAttribute('data-is-matrix-of'));
                    }
                });

                matrix.addEventListener('click', function (event) {
                    var column = event.target.closest('[data-is-matrix-column]');

                    if (column) {
                        event.preventDefault();

                        var cells = boxes('[data-is-matrix-ability="' + column.getAttribute('data-is-matrix-column') + '"]');
                        var turnOn = cells.some(function (c) { return !c.checked; });

                        cells.forEach(function (cell) { cell.checked = turnOn; });
                        syncAllRows();

                        return;
                    }

                    var bulk = event.target.closest('[data-is-matrix]');

                    if (bulk) {
                        event.preventDefault();

                        var on = bulk.getAttribute('data-is-matrix') === 'all';
                        boxes('[data-is-matrix-of]').forEach(function (cell) { cell.checked = on; });
                        syncAllRows();
                    }
                });

                syncAllRows();
            })();
        </script>
    @endpush
@endsection
