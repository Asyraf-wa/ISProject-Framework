<?php

namespace IsProject\Framework\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use IsProject\Framework\Support\AuthOptions;

/**
 * Self-registration, off unless switched on in the settings screen.
 *
 * Both actions check the setting, not just the one that renders the form: a
 * bookmarked POST must not be able to create an account after registration has
 * been turned off.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        abort_unless(AuthOptions::registrationEnabled(), 404);

        return view('isproject::auth.register', [
            'google' => AuthOptions::googleEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(AuthOptions::registrationEnabled(), 404);

        $users = $this->users();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($users->getTable(), 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $users->newInstance();
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('isproject.auth.home', '/'));
    }

    private function users(): Model
    {
        $class = config('auth.providers.users.model');

        abort_if(! $class || ! class_exists($class), 500, 'No user model is configured for the default auth provider.');

        return new $class;
    }
}
