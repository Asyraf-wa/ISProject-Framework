@extends(config('isproject.layout'))

@section('title', 'Roles')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">System</span>
            <h2 class="is-page-title">Roles</h2>
        </div>

        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('isproject.roles.sync') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                    <x-isproject::icon name="search" /> Rescan routes
                </button>
            </form>

            <a href="{{ route('isproject.roles.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                <x-isproject::icon name="plus" /> New role
            </a>
        </div>
    </div>

    @include('isproject::partials.alerts')

    @include('isproject::access.partials.unsynced', ['unsynced' => $unsynced])

    <div class="card">
        @if ($roles->isEmpty())
            <div class="is-empty">
                <p class="mb-2">No roles yet.</p>
                <p class="is-empty-icon">Create one, then tick what it may reach.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <div class="fw-semibold d-flex align-items-center gap-2">
                                        {{ $role->name }}
                                        @if ($role->is_super_admin)
                                            <span class="badge badge-soft-warning">Super admin</span>
                                        @endif
                                    </div>
                                    @if ($role->description)
                                        <div class="small text-body-secondary">{{ $role->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($role->is_super_admin)
                                        <span class="text-body-secondary">Everything</span>
                                    @else
                                        {{ $role->permissions_count }}
                                    @endif
                                </td>
                                <td>{{ $role->users_count }}</td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary border-0"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Actions for {{ $role->name }}">
                                            <x-isproject::icon name="dots" />
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('isproject.roles.edit', $role) }}">
                                                <x-isproject::icon name="pencil" /> Edit
                                            </a>

                                            <form method="POST" action="{{ route('isproject.roles.destroy', $role) }}"
                                                  onsubmit="return confirm('Delete the role {{ $role->name }}?')">
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
        @endif
    </div>
@endsection
