<?php

namespace IsProject\Framework\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Second line of defence for the generator page.
 *
 * The routes are only registered when the generator is enabled, so this should
 * never fire in practice — but a page that writes PHP files into the
 * application is worth checking twice. A cached route file, or a config change
 * after `route:cache`, could otherwise leave the endpoint reachable.
 *
 * It 404s rather than 403s: a disabled generator should not advertise itself.
 */
class EnsureGeneratorIsEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! static::enabled()) {
            throw new NotFoundHttpException;
        }

        if ($ability = config('isproject.generator.gate')) {
            Gate::authorize($ability);
        }

        return $next($request);
    }

    /**
     * Enabled explicitly by config, otherwise only in the local environment.
     * Never enabled when the app is in production, whatever the config says —
     * that combination is always a mistake.
     */
    public static function enabled(): bool
    {
        $configured = config('isproject.generator.enabled');

        if (app()->environment('production')) {
            return false;
        }

        if ($configured === null) {
            return app()->environment('local');
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }
}
