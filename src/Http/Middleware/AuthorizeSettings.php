<?php

namespace IsProject\Framework\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the site configuration screen with the ability named in
 * config('isproject.settings.gate'), on top of whatever middleware the route
 * group already applies.
 *
 * Unlike the generator, this page is expected to run in production, so it does
 * not hide itself by environment. With no gate configured, the route group's
 * own middleware ("auth" by default) is the only protection — which is fine for
 * a single-author student project and not fine for anything shared. The config
 * comment says so, and so does the README.
 */
class AuthorizeSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $ability = config('isproject.settings.gate');

        // 403 rather than 404: the page is a normal part of the application, so
        // there is nothing to hide about its existence.
        if ($ability && Gate::denies($ability)) {
            abort(403, 'You are not allowed to change the site configuration.');
        }

        return $next($request);
    }
}
