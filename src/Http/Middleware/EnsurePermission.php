<?php

namespace IsProject\Framework\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use IsProject\Framework\Support\Access;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the role matrix by matching the current route's name against the
 * permission of the same name.
 *
 *     Route::resource('products', ProductController::class)
 *         ->middleware(['auth', 'isproject.permission']);
 *
 * Opt-in per route group rather than global: an application has routes that
 * must stay open — the sign-in screen, a public landing page — and quietly
 * closing those would be a worse default than making the guard explicit.
 * `isproject.middleware` in config puts it on everything the generator writes.
 */
class EnsurePermission
{
    public function __construct(private readonly Access $access) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (! $user) {
            // Authentication is the "auth" middleware's job. Answering 403 here
            // would send a guest to a dead end instead of the sign-in screen.
            return $next($request);
        }

        $permission ??= $request->route()?->getName();

        // An unnamed route cannot be addressed by the matrix at all.
        if (! $permission) {
            return $next($request);
        }

        // Nobody has created a role yet, so there is nothing to enforce and
        // enforcing would deny everything. This lets the middleware be added to
        // a route group before RBAC is set up, which is the order people work in.
        if (! $this->access->isConfigured()) {
            return $next($request);
        }

        if ($this->access->has($user, $permission)) {
            return $next($request);
        }

        // A route the matrix has never heard of — added since the last sync.
        // Default is to let it through: a student who adds a route and forgets
        // to press "Rescan" should not get a 403 they have no way to diagnose,
        // and the roles screen shows a standing warning listing exactly these.
        // Set access.unknown_routes to 'deny' to close it instead.
        if (! in_array($permission, $this->access->knownPermissions(), true)) {
            return config('isproject.access.unknown_routes', 'allow') === 'allow'
                ? $next($request)
                : $this->deny($permission);
        }

        return $this->deny($permission);
    }

    private function deny(string $permission): never
    {
        abort(403, "You do not have permission for [{$permission}].");
    }
}
