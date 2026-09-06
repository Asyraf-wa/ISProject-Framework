@extends(config('isproject.layout'))

@section('title', 'Your profile')

@section('content')
    <div class="is-page-header">
        <div>
            <span class="is-page-eyebrow">Account</span>
            <h2 class="is-page-title">Your profile</h2>
        </div>
    </div>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="is-guide-title">Details</h3>
                </div>

                <div class="card-body">
                    {{-- enctype, because the photo posts with the details:
                         one form, one save, one "Profile updated." --}}
                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="is-avatar-field">
                            <x-isproject::avatar :user="$user" size="lg" />

                            <div class="flex-grow-1">
                                <label class="form-label" for="avatar">Photo</label>
                                <input type="file" class="form-control @error('avatar') is-invalid @enderror"
                                       id="avatar" name="avatar"
                                       accept="image/png,image/jpeg,image/webp">
                                @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <div class="form-text">
                                    PNG, JPEG or WebP, up to 2 MB. A square image looks best — it is
                                    shown as a circle.
                                </div>

                                @if ($hasAvatar)
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox"
                                               id="remove_avatar" name="remove_avatar" value="1">
                                        <label class="form-check-label small" for="remove_avatar">
                                            Remove my photo on save
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="name">
                                    Name <span class="is-required-mark" aria-hidden="true">*</span>
                                </label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="{{ old('name', $user->name) }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="email">
                                    Email <span class="is-required-mark" aria-hidden="true">*</span>
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="{{ old('email', $user->email) }}" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <x-isproject::icon name="save" /> Save details
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="is-guide-title">Change password</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="current_password">Current password</label>
                                <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                                       id="current_password" name="current_password" autocomplete="current-password" required>
                                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12"></div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password">New password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                       id="password" name="password" autocomplete="new-password" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="password_confirmation">Confirm new password</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                       name="password_confirmation" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <x-isproject::icon name="save" /> Change password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card is-guide">
                <div class="card-header">
                    <h3 class="is-guide-title">
                        <x-isproject::icon name="info" /> Connected accounts
                    </h3>
                </div>

                <div class="card-body">
                    @forelse ($linked as $account)
                        <div class="is-linked-account">
                            <div>
                                <div class="is-cache-label">{{ \Illuminate\Support\Str::headline($account->provider) }}</div>
                                <div class="is-cache-help">{{ $account->email }}</div>
                            </div>

                            <form method="POST" action="{{ route('profile.social.unlink', $account) }}"
                                  onsubmit="return confirm('Disconnect this account?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Disconnect</button>
                            </form>
                        </div>
                    @empty
                        <p class="is-guide-intro mb-0">
                            Nothing connected. If Google sign-in is switched on, using it once
                            links that Google account to this one.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
