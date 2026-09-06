<?php

namespace IsProject\Framework\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use IsProject\Framework\Models\SocialAccount;
use IsProject\Framework\Support\AuthOptions;
use IsProject\Framework\Support\GoogleProvider;
use RuntimeException;
use Throwable;

/**
 * Sign in with Google.
 *
 * Both actions refuse outright unless the feature is configured *and* switched
 * on, so a stale link or a bookmarked callback cannot start a flow the site is
 * not offering.
 */
class GoogleController extends Controller
{
    public function redirect(Request $request, GoogleProvider $google): RedirectResponse
    {
        if (! AuthOptions::googleEnabled()) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in is not available.']);
        }

        return redirect()->away($google->redirectUrl($request));
    }

    public function callback(Request $request, GoogleProvider $google): RedirectResponse
    {
        if (! AuthOptions::googleEnabled()) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in is not available.']);
        }

        try {
            $profile = $google->handleCallback($request);
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        } catch (Throwable $e) {
            // A network failure or a malformed response from Google. The
            // details belong in the log, not on a sign-in screen.
            report($e);

            return redirect()->route('login')->withErrors(['email' => 'Could not reach Google. Please try again.']);
        }

        $user = $this->resolveUser($profile);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => "There is no account for {$profile['email']}. Ask an administrator to create one.",
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(config('isproject.auth.home', '/'));
    }

    /**
     * Find, link, or create the local account behind a verified Google identity.
     *
     * @param  array{id: string, email: string, name: string}  $profile
     */
    private function resolveUser(array $profile): ?Model
    {
        $link = SocialAccount::query()
            ->where('provider', 'google')
            ->where('provider_id', $profile['id'])
            ->first();

        if ($link && ($user = $link->user)) {
            return $user;
        }

        $users = $this->users();
        $existing = $users->newQuery()->where('email', $profile['email'])->first();

        if ($existing) {
            // Linking on email is only safe because the provider told us the
            // address is verified — GoogleProvider refuses the callback
            // otherwise. Without that check this would be account takeover.
            $this->link($existing, $profile);

            return $existing;
        }

        if (! AuthOptions::googleMayCreateUsers()) {
            return null;
        }

        $created = $users->newInstance();
        $created->forceFill([
            'name' => $profile['name'],
            'email' => $profile['email'],

            // No usable password: this account signs in through Google. A
            // random hash rather than null keeps the column NOT NULL happy and
            // cannot be guessed into.
            'password' => bcrypt(Str::random(64)),
            'email_verified_at' => now(),
        ])->save();

        $this->link($created, $profile);

        return $created;
    }

    /** @param  array{id: string, email: string, name: string}  $profile */
    private function link(Model $user, array $profile): void
    {
        SocialAccount::query()->updateOrCreate(
            ['provider' => 'google', 'provider_id' => $profile['id']],
            ['user_id' => $user->getKey(), 'email' => $profile['email']],
        );
    }

    private function users(): Model
    {
        $class = config('auth.providers.users.model');

        abort_if(! $class || ! class_exists($class), 500, 'No user model is configured for the default auth provider.');

        return new $class;
    }
}
