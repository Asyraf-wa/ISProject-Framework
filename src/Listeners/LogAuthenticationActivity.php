<?php

namespace IsProject\Framework\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;
use IsProject\Framework\Models\Activity;
use IsProject\Framework\Support\ActivityLogger;

/**
 * Turns Laravel's own authentication events into activity rows.
 *
 * Listening to the framework's events rather than instrumenting this package's
 * controllers is deliberate: an application using Breeze, Fortify or its own
 * login screen fires exactly the same events, so the activity log keeps working
 * when isproject.auth.enabled is false and none of our controllers run.
 */
class LogAuthenticationActivity
{
    public function __construct(private ActivityLogger $logger) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            Registered::class => 'onRegistered',
            PasswordReset::class => 'onPasswordReset',
        ];
    }

    public function onLogin(Login $event): void
    {
        $this->logger->logFor($event->user, Activity::LOGIN, 'Signed in', [
            'guard' => $event->guard,
            'remembered' => $event->remember,
        ]);
    }

    public function onLogout(Logout $event): void
    {
        // The user is on the event because the session has already gone by the
        // time this fires; auth()->user() would be null here.
        $this->logger->logFor($event->user, Activity::LOGOUT, 'Signed out', [
            'guard' => $event->guard,
        ]);
    }

    public function onFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        // user_id stays null even when Laravel found the account: nobody was
        // authenticated, and "what did this user do" must not start listing
        // things done *to* them by somebody else. The address is kept in
        // properties, which is what a security review actually filters on.
        $this->logger->log(Activity::LOGIN_FAILED, 'Sign-in failed', [
            'email' => is_string($email) ? $email : null,
            'account_exists' => $event->user !== null,
        ]);
    }

    public function onLockout(Lockout $event): void
    {
        $this->logger->log(Activity::LOCKOUT, 'Too many sign-in attempts', [
            'email' => $event->request->input('email'),
        ]);
    }

    public function onRegistered(Registered $event): void
    {
        $this->logger->logFor($event->user, Activity::REGISTERED, 'Created an account');
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->logger->logFor($event->user, Activity::PASSWORD_RESET, 'Reset their password from an emailed link');
    }
}
