@extends(config('isproject.guest_layout'))

@section('title', 'Choose a new password')

@section('content')
    <h1 class="h5 mb-1">Choose a new password</h1>
    <p class="text-body-secondary small mb-4">This link can only be used once.</p>

    @include('isproject::partials.errors')

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $email) }}" required autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">New password</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   required autofocus autocomplete="new-password">
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                   required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">Set the password</button>
    </form>
@endsection
