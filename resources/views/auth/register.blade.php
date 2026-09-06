@extends(config('isproject.guest_layout'))

@section('title', 'Create an account')

@section('content')
    <h1 class="h5 mb-1">Create an account</h1>
    <p class="text-body-secondary small mb-4">It takes a moment.</p>

    @include('isproject::partials.errors')

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required autofocus autocomplete="name">
        </div>

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   required autocomplete="new-password">
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                   required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">Create account</button>
    </form>

    @if ($google)
        <div class="is-or"><span>or</span></div>

        <a href="{{ route('isproject.auth.google.redirect') }}"
           class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-2">
            <x-isproject::icon name="google" size="17" /> Continue with Google
        </a>
    @endif

    <p class="text-center small text-body-secondary mt-4 mb-0">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>.
    </p>
@endsection
