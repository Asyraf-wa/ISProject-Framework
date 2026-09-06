@extends(config('isproject.layout'))

@section('title', $item->exists ? 'Edit menu item' : 'New menu item')

@section('content')
    @php
        $type = old('type', $item->type);
        $action = $item->exists
            ? route('isproject.menu.update', $item)
            : route('isproject.menu.store');
    @endphp

    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.menu.index') }}">Menu</a>
            </span>
            <h2 class="is-page-title">{{ $item->exists ? $item->label : 'New menu item' }}</h2>
        </div>
    </div>

    @include('isproject::partials.errors')

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $action }}" data-is-menu-form>
                        @csrf
                        @if ($item->exists) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label" for="type">
                                What is it <span class="is-required-mark" aria-hidden="true">*</span>
                            </label>
                            <select class="form-select" id="type" name="type" data-is-menu-type>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="label">
                                Label <span class="is-required-mark" aria-hidden="true">*</span>
                            </label>
                            <input type="text" class="form-control @error('label') is-invalid @enderror"
                                   id="label" name="label" value="{{ old('label', $item->label) }}" required>
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">What the sidebar shows.</div>
                        </div>

                        {{-- Panels for the fields each type needs. The type
                             select shows one and hides the rest; every panel is
                             in the HTML so this works with JavaScript off. --}}
                        <div data-is-menu-panel="route">
                            <div class="mb-3">
                                <label class="form-label" for="route_name">Page</label>

                                {{-- A real select rather than a datalist: the
                                     value has to be one of these anyway, the
                                     server refuses anything else, and a
                                     searchable select says so up front instead
                                     of failing after the save. --}}
                                <select class="form-select @error('route_name') is-invalid @enderror"
                                        id="route_name" name="route_name"
                                        data-is-select
                                        data-is-select-placeholder="Search for a page…">
                                    <option value="">— Choose a page —</option>
                                    @foreach ($routes as $route)
                                        <option value="{{ $route }}"
                                            @selected(old('route_name', $item->route_name) === $route)>{{ $route }}</option>
                                    @endforeach
                                </select>
                                @error('route_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    A route name. Start typing to see what this application has —
                                    <code>tasks.index</code> is the listing page of a module made with
                                    <code>isproject:crud</code>. If the route ever stops existing the row is
                                    skipped rather than breaking the sidebar.
                                </div>
                            </div>
                        </div>

                        {{-- One field for both link types, not two: a second
                             input sharing name="url" would submit its own empty
                             value and wipe whatever the visible one held. --}}
                        <div data-is-menu-panel="internal external">
                            <div class="mb-3">
                                <label class="form-label" for="url">
                                    <span data-is-menu-when="internal">Path</span>
                                    <span data-is-menu-when="external">Address</span>
                                </label>
                                <input type="text" class="form-control @error('url') is-invalid @enderror"
                                       id="url" name="url" value="{{ old('url', $item->url) }}"
                                       placeholder="{{ $type === 'external' ? 'https://example.edu/handbook' : '/reports' }}">
                                @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <div class="form-text" data-is-menu-when="internal">
                                    A path on this site, starting with a slash.
                                </div>
                                <div class="form-text" data-is-menu-when="external">
                                    Must start with <code>http://</code> or <code>https://</code>. External links
                                    always open in a new tab.
                                </div>
                            </div>
                        </div>

                        <div data-is-menu-panel="route internal external">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="icon">Icon</label>
                                    <select class="form-select" id="icon" name="icon">
                                        <option value="">— None —</option>
                                        @foreach ($icons as $icon)
                                            <option value="{{ $icon }}" @selected(old('icon', $item->icon) === $icon)>
                                                {{ $icon }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="parent_id">Sits under</label>
                                    <select class="form-select" id="parent_id" name="parent_id">
                                        <option value="">— Top level —</option>
                                        @foreach ($parents as $parent)
                                            <option value="{{ $parent->id }}"
                                                @selected((string) old('parent_id', $item->parent_id) === (string) $parent->id)>
                                                {{ $parent->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">The menu goes two levels deep.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="permission">Only show to people who may</label>
                                    <select class="form-select" id="permission" name="permission"
                                            data-is-select
                                            data-is-select-placeholder="Everyone signed in">
                                        <option value="">— Everyone signed in —</option>
                                        @foreach ($routes as $route)
                                            <option value="{{ $route }}"
                                                @selected(old('permission', $item->permission) === $route)>{{ $route }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">
                                        A permission name — usually the same route. Leave empty to show it to
                                        everyone signed in.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="badge">Badge</label>
                                    <input type="text" class="form-control" id="badge" name="badge"
                                           value="{{ old('badge', $item->badge) }}" maxlength="30" placeholder="New">
                                    <div class="form-text">A short label pinned to the right of the row.</div>
                                </div>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="opens_in_new_tab"
                                       name="opens_in_new_tab" value="1"
                                       @checked(old('opens_in_new_tab', $item->opens_in_new_tab))>
                                <label class="form-check-label" for="opens_in_new_tab">Open in a new tab</label>
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   @checked(old('is_active', $item->exists ? $item->is_active : true))>
                            <label class="form-check-label" for="is_active">Show it in the sidebar</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                <x-isproject::icon name="save" /> {{ $item->exists ? 'Save item' : 'Add to menu' }}
                            </button>

                            <a href="{{ route('isproject.menu.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title"><x-isproject::icon name="info" /> The four kinds</h3>
                </div>

                <div class="card-body">
                    <dl class="is-guide-list mb-0">
                        <dt>Link to a page in this system</dt>
                        <dd>
                            Points at a named route, so the link keeps working if the URL changes. This is what
                            a generated module wants.
                        </dd>

                        <dt>Link to a path</dt>
                        <dd>Any path on this site, for a page with no route name of its own.</dd>

                        <dt>Link to another website</dt>
                        <dd>
                            Opens in a new tab with <code>rel="noopener"</code>. Only <code>http</code> and
                            <code>https</code> are accepted.
                        </dd>

                        <dt>Heading</dt>
                        <dd>
                            Labels the group beneath it. A heading with nothing under it — because every item
                            was hidden by permissions — is dropped rather than left floating.
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
