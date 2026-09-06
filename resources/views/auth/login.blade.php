@extends(config('isproject.guest_layout'))

@section('title', 'Sign in')

@section('content')
    <h1 class="h5 mb-1">Sign in</h1>
    <p class="text-body-secondary small mb-4">Enter your credentials to continue.</p>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-baseline">
                <label class="form-label" for="password">Password</label>
                <a class="small" href="{{ route('password.request') }}">Forgot it?</a>
            </div>
            <input type="password" id="password" name="password" class="form-control"
                   required autocomplete="current-password">
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1"
                   @checked(old('remember'))>
            <label class="form-check-label small" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    @if ($google)
        <div class="is-or"><span>or</span></div>

        {{-- A link, not a form: the redirect starts a GET flow at Google. --}}
        <a href="{{ route('isproject.auth.google.redirect') }}"
           class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-2">
            <x-isproject::icon name="google" size="17" /> Continue with Google
        </a>
    @endif

    @if ($registration)
        <p class="text-center small text-body-secondary mt-4 mb-0">
            No account? <a href="{{ route('register') }}">Create one</a>.
        </p>
    @endif
@endsection
