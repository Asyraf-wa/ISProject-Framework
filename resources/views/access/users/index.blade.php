@extends(config('isproject.layout'))

@section('title', 'Users')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Users</h2>
        </div>

        <a href="{{ route('isproject.users.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <x-isproject::icon name="plus" /> New user
        </a>
    </div>

    @include('isproject::partials.alerts')

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('isproject.users.index') }}" class="d-flex gap-2">
                <input type="search" class="form-control" name="q" value="{{ $search }}"
                       placeholder="Search name or email" aria-label="Search users">
                <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                    <x-isproject::icon name="search" /> Search
                </button>
                @if ($search !== '')
                    <a href="{{ route('isproject.users.index') }}" class="btn btn-link text-body-secondary">Clear</a>
                @endif
            </form>
        </div>

        @if ($users->isEmpty())
            <div class="is-empty">
                <p class="mb-2">No users found.</p>
                <p class="is-empty-icon">{{ $search !== '' ? 'Try a different search.' : 'Add the first one.' }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <x-isproject::avatar :user="$user" />
                                        <span class="fw-semibold">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="badge {{ $role->is_super_admin ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-body-secondary">None</span>
                                    @endforelse
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary border-0"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $user->name }}">
                                            <x-isproject::icon name="dots" />
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('isproject.users.edit', $user) }}">
                                                <x-isproject::icon name="pencil" /> Edit
                                            </a>

                                            <form method="POST" action="{{ route('isproject.users.destroy', $user) }}"
                                                  onsubmit="return confirm('Delete {{ $user->email }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <x-isproject::icon name="trash" /> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="card-body">{{ $users->links() }}</div>
            @endif
        @endif
    </div>
@endsection
