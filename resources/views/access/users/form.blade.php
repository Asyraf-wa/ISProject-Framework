@extends(config('isproject.layout'))

@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">
                <a href="{{ route('isproject.users.index') }}">Users</a>
            </span>
            <h2 class="is-page-title">{{ $user->exists ? $user->name : 'New user' }}</h2>
        </div>

        <a href="{{ route('isproject.users.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <x-isproject::icon name="arrow-left" /> Back to users
        </a>
    </div>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST"
                          action="{{ $user->exists ? route('isproject.users.update', $user) : route('isproject.users.store') }}">
                        @csrf
                        @if ($user->exists) @method('PUT') @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="name">
                                    Name <span class="is-required-mark" aria-hidden="true">*</span>
                                </label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="email">
                                    Email <span class="is-required-mark" aria-hidden="true">*</span>
                                </label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                       id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password">
                                    Password @unless ($user->exists) <span class="is-required-mark" aria-hidden="true">*</span> @endunless
                                </label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                       id="password" name="password" autocomplete="new-password"
                                       @unless ($user->exists) required @endunless>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if ($user->exists)
                                    <div class="form-text">Leave blank to keep the current password.</div>
                                @endif
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password_confirmation">Confirm password</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                       name="password_confirmation" autocomplete="new-password"
                                       @unless ($user->exists) required @endunless>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label d-block">Roles</label>

                                @forelse ($roles as $role)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]"
                                               id="role-{{ $role->id }}" value="{{ $role->id }}"
                                               @checked(in_array($role->id, old('roles', $assigned)))>
                                        <label class="form-check-label" for="role-{{ $role->id }}">
                                            {{ $role->name }}
                                            @if ($role->is_super_admin)
                                                <span class="badge badge-soft-warning">Super admin</span>
                                            @endif
                                            @if ($role->description)
                                                <span class="text-body-secondary small d-block">{{ $role->description }}</span>
                                            @endif
                                        </label>
                                    </div>
                                @empty
                                    <p class="text-body-secondary mb-0">
                                        No roles yet — <a href="{{ route('isproject.roles.create') }}">create one</a>.
                                    </p>
                                @endforelse
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                <x-isproject::icon name="save" /> {{ $user->exists ? 'Save user' : 'Create user' }}
                            </button>
                            <a href="{{ route('isproject.users.index') }}" class="btn btn-link text-body-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title">
                        <x-isproject::icon name="info" /> Accounts and access
                    </h3>
                </div>

                <div class="card-body">
                    <p class="is-guide-intro">
                        What a user may reach comes from their roles. Give someone a role on this
                        screen; decide what the role can do on the
                        <a href="{{ route('isproject.roles.index') }}">roles</a> screen.
                    </p>

                    <h4 class="is-guide-heading">Guards you cannot switch off</h4>
                    <dl class="is-guide-list">
                        <dt>You cannot delete yourself</dt>
                        <dd>Deleting the account you are signed in with is never the intended click.</dd>
                        <dt>The last super admin stays one</dt>
                        <dd>Removing that role from the only account holding it is refused, so there is always a way back in.</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
