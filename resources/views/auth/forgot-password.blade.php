@extends(config('isproject.guest_layout'))

@section('title', 'Reset your password')

@section('content')
    <h1 class="h5 mb-1">Reset your password</h1>
    <p class="text-body-secondary small mb-4">
        Tell us the address on your account and we will send a link to set a new password.
    </p>

    @include('isproject::partials.alerts')
    @include('isproject::partials.errors')

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>

        <button type="submit" class="btn btn-primary w-100">Send the link</button>
    </form>

    <p class="text-center small text-body-secondary mt-4 mb-0">
        <a href="{{ route('login') }}">Back to sign in</a>
    </p>
@endsection
