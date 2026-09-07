<?php

namespace IsProject\Framework;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use IsProject\Framework\Console\ActivityPruneCommand;
use IsProject\Framework\Console\ArchivableCommand;
use IsProject\Framework\Console\AuditPruneCommand;
use IsProject\Framework\Console\CrudAllCommand;
use IsProject\Framework\Console\CrudMakeCommand;
use IsProject\Framework\Console\CrudRemoveCommand;
use IsProject\Framework\Console\InstallCommand;
use IsProject\Framework\Console\PermissionSyncCommand;
use IsProject\Framework\Console\UserMakeCommand;
use IsProject\Framework\Http\Middleware\EnsureGeneratorIsEnabled;
use IsProject\Framework\Http\Middleware\EnsurePermission;
use IsProject\Framework\Listeners\LogAuthenticationActivity;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\ActivityLogger;
use IsProject\Framework\Support\AuditRecorder;
use IsProject\Framework\Support\AuthOptions;
use IsProject\Framework\Support\Avatars;
use IsProject\Framework\Support\Manual;
use IsProject\Framework\Support\Menu;
use IsProject\Framework\Support\MenuManager;
use IsProject\Framework\Support\PermissionRegistry;
use IsProject\Framework\Support\SchemaInspector;
use IsProject\Framework\Support\Settings;
use IsProject\Framework\Support\SettingsSchema;
use IsProject\Framework\Support\StubRenderer;
use Throwable;

class IsProjectServiceProvider extends ServiceProvider
{
    /** Root of the installed package, used to locate stubs, views and assets. */
    private function base(string $path = ''): string
    {
        return dirname(__DIR__).($path ? DIRECTORY_SEPARATOR.ltrim($path, '/\\') : '');
    }

    public function register(): void
    {
        // composer.json autoloads this via "files", but that only takes effect
        // once the application regenerates its autoloader — which has not
        // happened yet on the request right after an upgrade. The views call
        // isproject_setting(), and a missing function there is a fatal error on
        // every page, so load it defensively as well.
        if (! function_exists('isproject_setting')) {
            require_once $this->base('src/helpers.php');
        }

        $this->mergeConfigFrom($this->base('config/isproject.php'), 'isproject');

        $this->app->singleton(StubRenderer::class, fn ($app) => new StubRenderer(
            $app->make(Filesystem::class),
            $this->base('stubs'),
        ));

        $this->app->bind(SchemaInspector::class, fn () => new SchemaInspector);
        $this->app->bind(Menu::class, fn ($app) => new Menu($app->make(MenuManager::class)));

        // The sidebar asks once per page; the memo inside makes that one query.
        $this->app->singleton(MenuManager::class);

        // Scans its directories once and memoises the chapter list.
        $this->app->singleton(Manual::class);

        // A singleton because it holds the "suspend logging" flag: without one
        // shared instance, withoutLogging() would only silence the copy it was
        // called on — the same reason the audit recorder is one.
        $this->app->singleton(ActivityLogger::class);

        // Singletons: the shell asks for the site name, logo and favicon
        // several times per request, and both classes cache their work.
        $this->app->singleton(SettingsSchema::class);
        $this->app->singleton(Settings::class);

        // Both memoise per request: Access is consulted on every guarded route
        // and every @can in the menu, and the registry walks the whole route
        // collection to build the matrix.
        $this->app->singleton(Access::class);
        $this->app->singleton(PermissionRegistry::class);

        // A singleton because it holds the "suspend auditing" flag: without
        // one shared instance, withoutAuditing() would only silence the copy
        // it was called on.
        $this->app->singleton(AuditRecorder::class);

        // The topbar asks for the signed-in user's photo on every page and the
        // users list asks once per row; one instance keeps that to one query.
        $this->app->singleton(Avatars::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->base('resources/views'), 'isproject');
        $this->loadMigrationsFrom($this->base('database/migrations'));

        // <x-isproject::icon />, <x-isproject::nav-item /> — anonymous
        // components, so there are no PHP classes for developers to wade through.
        Blade::anonymousComponentNamespace($this->base('resources/views/components'), 'isproject');

        // Our stylesheet is Bootstrap-based, so pagination must match.
        Paginator::useBootstrapFive();

        $this->registerBlueprintMacros();

        // The generator page writes PHP into the application, so its routes are
        // not registered at all unless the environment allows it.
        if (EnsureGeneratorIsEnabled::enabled()) {
            $this->loadRoutesFrom($this->base('routes/generator.php'));
        }

        // Registered before the application's own routes/web.php, so an app
        // that defines its own "login" still wins on the name lookup. Set
        // isproject.auth.enabled to false to keep ours out entirely.
        if (AuthOptions::enabled()) {
            $this->loadRoutesFrom($this->base('routes/auth.php'));
        }

        $this->loadRoutesFrom($this->base('routes/settings.php'));
        $this->loadRoutesFrom($this->base('routes/access.php'));
        $this->loadRoutesFrom($this->base('routes/audit.php'));
        $this->loadRoutesFrom($this->base('routes/activity.php'));
        $this->loadRoutesFrom($this->base('routes/dashboard.php'));
        $this->loadRoutesFrom($this->base('routes/menu.php'));
        $this->loadRoutesFrom($this->base('routes/manual.php'));
        $this->loadRoutesFrom($this->base('routes/pwa.php'));
        $this->loadRoutesFrom($this->base('routes/seo.php'));

        $this->registerAccessControl();

        // Sign-ins, failed sign-ins and lockouts, taken from Laravel's own auth
        // events rather than from our controllers — so the log keeps working
        // for an application that uses Breeze, Fortify or its own login screen.
        if (config('isproject.activity.enabled', true)) {
            Event::subscribe(LogAuthenticationActivity::class);
        }

        $this->applyConfiguredTimezone();

        // Registered unconditionally, not only in console: the generator page
        // reaches these through Artisan::call() during an HTTP request, and
        // runningInConsole() is false there. Registration is deferred by the
        // framework, so this costs nothing on a normal web request.
        $this->commands([
            CrudMakeCommand::class,
            CrudAllCommand::class,
            CrudRemoveCommand::class,
            InstallCommand::class,
            PermissionSyncCommand::class,
            AuditPruneCommand::class,
            ArchivableCommand::class,
            ActivityPruneCommand::class,
            UserMakeCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * $table->archivable() in a migration, mirroring Laravel's own
     * $table->softDeletes(). Indexed because once a table is archivable every
     * ordinary query on it filters on this column.
     */
    private function registerBlueprintMacros(): void
    {
        Blueprint::macro('archivable', function (string $column = 'archived_at') {
            /** @var Blueprint $this */
            return $this->timestamp($column)->nullable()->index();
        });

        Blueprint::macro('dropArchivable', function (string $column = 'archived_at') {
            /** @var Blueprint $this */
            $this->dropIndex([$column]);

            return $this->dropColumn($column);
        });
    }

    /**
     * Wire RBAC into Laravel's own authorisation rather than beside it.
     *
     * Everything developers already know keeps working — $user->can('products.index'),
     * the "can" directive in Blade, $this->authorize() in a controller, and the
     * menu's own 'can' key — because the check happens in a Gate::before hook.
     */
    private function registerAccessControl(): void
    {
        $this->app['router']->aliasMiddleware('isproject.permission', EnsurePermission::class);

        Gate::before(function ($user, string $ability) {
            $access = $this->app->make(Access::class);

            if ($access->isSuperAdmin($user)) {
                return true;
            }

            // Only answer for abilities RBAC actually owns. Anything else falls
            // through untouched — returning false here would shadow every
            // policy in the application.
            if (in_array($ability, $access->knownPermissions(), true)) {
                // Same rule the middleware uses: with no roles defined there is
                // nothing to enforce. Without this, a menu entry carrying
                // 'can' => 'products.index' would hide itself on a fresh
                // install, before anyone had the chance to set RBAC up.
                if (! $access->isConfigured()) {
                    return true;
                }

                // Falls through on a denial rather than returning false, so an
                // application that also defines its own ability of the same
                // name can still grant it.
                return $access->has($user, $ability) ?: null;
            }

            return null;
        });
    }

    /**
     * A timezone chosen on the settings screen has to reach PHP itself, not
     * just config, or Carbon keeps formatting in the old zone.
     *
     * Guarded and swallowed: this runs on every request, including the ones
     * where the settings table does not exist yet (a fresh install, or the
     * migration that creates it). A site that cannot read its own timezone
     * should fall back to config, not return a 500.
     */
    private function applyConfiguredTimezone(): void
    {
        try {
            $timezone = $this->app->make(Settings::class)->get('timezone');

            if (! is_string($timezone) || $timezone === '' || $timezone === config('app.timezone')) {
                return;
            }

            if (! in_array($timezone, timezone_identifiers_list(), true)) {
                return;
            }

            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        } catch (Throwable) {
            // Keep whatever config already set.
        }
    }

    /**
     * Publishing groups developers will actually use:
     *   --tag=isproject-assets  compiled CSS/JS into public/vendor/isproject
     *   --tag=isproject-config  the config file, including the sidebar menu
     *   --tag=isproject-views   the layout and shared partials
     *   --tag=isproject-stubs   generator stubs, to reshape generated code
     */
    private function registerPublishing(): void
    {
        $this->publishes([
            $this->base('resources/dist') => public_path('vendor/isproject'),
        ], ['isproject-assets', 'laravel-assets']);

        $this->publishes([
            $this->base('config/isproject.php') => config_path('isproject.php'),
        ], 'isproject-config');

        $this->publishes([
            $this->base('resources/views') => resource_path('views/vendor/isproject'),
        ], 'isproject-views');

        $this->publishes([
            $this->base('stubs') => base_path((string) config('isproject.stub_path', 'stubs/isproject')),
        ], 'isproject-stubs');
    }
}
