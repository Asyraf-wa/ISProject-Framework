<?php

namespace IsProject\Framework\Support;

/**
 * What the sign-in screen offers, and whether each option is usable.
 *
 * The distinction that matters here is between *configured* and *enabled*:
 *
 *   configured — the credentials exist in .env, so the flow could work
 *   enabled    — someone ticked the box on the settings screen
 *
 * Google sign-in needs both. Keeping the two apart is what lets the settings
 * screen refuse to switch on a button that would send students to a Google
 * error page, and explain why instead.
 */
class AuthOptions
{
    /** Whether the framework's own auth routes should be registered at all. */
    public static function enabled(): bool
    {
        return (bool) config('isproject.auth.enabled', true);
    }

    /** Credentials are present, so the flow is capable of completing. */
    public static function googleConfigured(): bool
    {
        return self::googleClientId() !== '' && self::googleClientSecret() !== '';
    }

    /** Configured *and* switched on: the only state that shows the button. */
    public static function googleEnabled(): bool
    {
        return self::googleConfigured() && (bool) isproject_setting('auth_google_enabled', false);
    }

    /**
     * Whether a first-time Google sign-in may create a local account.
     *
     * Off by default. With it off, Google can only sign in someone who already
     * has an account here, which is what a course roster usually wants — a
     * Google account is not by itself a reason to be in the system.
     */
    public static function googleMayCreateUsers(): bool
    {
        return (bool) isproject_setting('auth_google_register', false);
    }

    public static function registrationEnabled(): bool
    {
        return (bool) isproject_setting('auth_registration_enabled', false);
    }

    public static function googleClientId(): string
    {
        return trim((string) config('isproject.auth.google.client_id', ''));
    }

    public static function googleClientSecret(): string
    {
        return trim((string) config('isproject.auth.google.client_secret', ''));
    }

    /**
     * Where Google sends the browser back to.
     *
     * Derived from the route so it is right by default, but overridable: behind
     * a proxy or a tunnel the URL Laravel generates is not always the one
     * registered in the Google console, and a mismatch there is the single most
     * common reason this flow fails.
     */
    public static function googleRedirectUri(): string
    {
        $configured = trim((string) config('isproject.auth.google.redirect', ''));

        return $configured !== '' ? $configured : route('isproject.auth.google.callback');
    }
}
