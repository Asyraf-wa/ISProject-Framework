<?php

namespace IsProject\Framework\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Forgotten password, on Laravel's own password broker — so the token table,
 * expiry and throttling are the framework's, not ours.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('isproject::auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink($request->only('email'));

        // The same answer whatever the broker returned. Reporting "no such
        // account" here would turn this form into a way of testing which
        // addresses are registered.
        return back()->with('success', 'If that address has an account, a reset link is on its way.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('isproject::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),

                    // Invalidates "remember me" cookies issued before the
                    // reset, which is the point of resetting a password that
                    // may have been compromised.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. You can sign in now.');
    }
}
