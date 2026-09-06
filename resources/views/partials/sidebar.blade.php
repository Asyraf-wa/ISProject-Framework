@php
    $menu = app(\IsProject\Framework\Support\Menu::class)->items();
    $brand = config('isproject.brand');

    // Settings win over config, and config over the framework default, so the
    // screen a lecturer edits is the one that decides.
    $isBrandName = isproject_setting('app_name', $brand['name'] ?: config('app.name'));
    $isBrandIcon = isproject_setting('brand_icon', $brand['icon'] ?? 'grid');
    $isLogo = isproject_setting('logo');
@endphp

<aside class="is-sidebar" id="is-sidebar">
    {{--
        With a logo uploaded the logo speaks for itself, so the mark and the
        name are hidden rather than crowding a 264px rail. The mark is still
        rendered: the stylesheet brings it back when the sidebar is collapsed,
        where a wide logo would have nowhere to go.
    --}}
    <a href="{{ url('/') }}" class="is-brand{{ $isLogo ? ' is-brand-has-logo' : '' }}">
        @if ($isLogo)
            <img class="is-brand-logo"
                 src="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isLogo) }}"
                 alt="{{ $isBrandName }}">
        @endif

        <span class="is-brand-mark"><x-isproject::icon :name="$isBrandIcon" size="18" /></span>
        <span class="is-brand-text">{{ $isBrandName }}</span>
    </a>

    <ul class="is-nav">
        @foreach ($menu as $item)
            <x-isproject::nav-item :item="$item" />
        @endforeach
    </ul>

    @auth
        <div class="is-sidebar-footer">
            <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}">
                @csrf
                <button type="submit" class="is-nav-link" data-label="Log out">
                    <span class="is-nav-icon"><x-isproject::icon name="logout" /></span>
                    <span class="is-nav-text">Log out</span>
                </button>
            </form>
        </div>
    @endauth
</aside>

<div class="is-backdrop" aria-hidden="true"></div>
