<header class="is-topbar">
    <button type="button"
            class="btn btn-sm btn-icon btn-outline-secondary border-0"
            data-is-toggle="sidebar"
            aria-controls="is-sidebar"
            aria-label="Toggle navigation">
        <x-isproject::icon name="menu" size="18" />
    </button>

    <h1 class="is-topbar-title">@yield('title', isproject_setting('app_name', config('app.name')))</h1>

    <div class="ms-auto d-flex align-items-center gap-1">
        <button type="button"
                class="btn btn-sm btn-icon btn-outline-secondary border-0 is-theme-toggle"
                data-is-toggle="theme"
                aria-label="Toggle light and dark theme">
            {{-- No display utilities here: the stylesheet owns which one shows. --}}
            <span class="is-theme-icon-moon"><x-isproject::icon name="moon" size="17" /></span>
            <span class="is-theme-icon-sun"><x-isproject::icon name="sun" size="17" /></span>
        </button>

        @auth
            @php $user = auth()->user(); @endphp

            <div class="dropdown">
                <button type="button"
                        class="btn btn-sm border-0 d-flex align-items-center gap-2 ps-1 pe-2"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <x-isproject::avatar :user="$user" />
                    <span class="d-none d-sm-inline small fw-medium">{{ $user->name ?? $user->email }}</span>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                    <div class="px-3 py-2">
                        <div class="fw-semibold small">{{ $user->name ?? 'Account' }}</div>
                        <div class="text-body-secondary" style="font-size: .75rem">{{ $user->email }}</div>
                    </div>

                    <hr class="dropdown-divider">

                    @if (Route::has('profile.edit'))
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <x-isproject::icon name="user" /> Profile
                        </a>
                    @endif

                    <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <x-isproject::icon name="logout" /> Log out
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>
