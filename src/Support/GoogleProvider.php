<?php

namespace IsProject\Framework\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google sign-in, using the OAuth 2.0 authorization code flow.
 *
 * Written against Laravel's HTTP client rather than pulling in Socialite: the
 * whole point of this feature is "put two values in .env, tick a box", and
 * requiring a composer install first would undo that. Socialite remains a
 * perfectly good alternative if you already use it — see the README.
 *
 * The flow is deliberately the *server-side* one. The browser never sees the
 * client secret, and the code is exchanged for a token over TLS directly with
 * Google, so the response can be trusted without verifying a JWT signature.
 */
class GoogleProvider
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    /** Session key holding the anti-forgery state between the two requests. */
    public const STATE_KEY = 'isproject.google.state';

    /**
     * The URL to send the browser to, having stashed a one-time state value.
     *
     * The state is what stops a third party from feeding us a code they
     * obtained elsewhere: a callback that does not carry back the exact value
     * we generated is not the continuation of a flow this session started.
     */
    public function redirectUrl(Request $request): string
    {
        $state = Str::random(40);

        $request->session()->put(self::STATE_KEY, $state);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => AuthOptions::googleClientId(),
            'redirect_uri' => AuthOptions::googleRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,

            // Ask Google every time rather than silently reusing whichever
            // account the browser happens to be signed into.
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Turn the callback into a verified identity.
     *
     * @return array{id: string, email: string, name: string}
     *
     * @throws RuntimeException when the callback is not one we can trust
     */
    public function handleCallback(Request $request): array
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        // Single-use: pulled above, so a replayed callback finds nothing.
        if (! $expected || ! is_string($request->query('state')) || ! hash_equals($expected, $request->query('state'))) {
            throw new RuntimeException('That sign-in link has expired or was not started here. Please try again.');
        }

        if ($request->query('error')) {
            throw new RuntimeException('Google sign-in was cancelled.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            throw new RuntimeException('Google did not return an authorisation code.');
        }

        $token = $this->exchange($code);
        $profile = $this->fetchProfile($token);

        // The one check that must never be skipped. An unverified Google email
        // proves nothing about who owns that address, and this address is about
        // to be matched against a local account.
        if (! ($profile['email_verified'] ?? false)) {
            throw new RuntimeException('That Google account has no verified email address.');
        }

        if (empty($profile['sub']) || empty($profile['email'])) {
            throw new RuntimeException('Google did not return enough information to sign you in.');
        }

        return [
            'id' => (string) $profile['sub'],
            'email' => (string) $profile['email'],
            'name' => (string) ($profile['name'] ?? Str::before($profile['email'], '@')),
        ];
    }

    private function exchange(string $code): string
    {
        $response = Http::asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => AuthOptions::googleClientId(),
                'client_secret' => AuthOptions::googleClientSecret(),
                'redirect_uri' => AuthOptions::googleRedirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if ($response->failed() || ! $response->json('access_token')) {
            // Google's own message names the cause — usually a redirect_uri
            // that does not match the one registered in the console — and it is
            // far more use to a developer than "something went wrong".
            throw new RuntimeException('Google rejected the sign-in: '.$this->reason($response->json()));
        }

        return (string) $response->json('access_token');
    }

    /** @return array<string, mixed> */
    private function fetchProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->timeout(15)->get(self::USERINFO_URL);

        if ($response->failed()) {
            throw new RuntimeException('Could not read your Google profile.');
        }

        return (array) $response->json();
    }

    /** @param  array<string, mixed>|null  $payload */
    private function reason(?array $payload): string
    {
        return (string) ($payload['error_description'] ?? $payload['error'] ?? 'no reason given');
    }
}
