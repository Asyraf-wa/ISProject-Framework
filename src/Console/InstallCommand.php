<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;

/**
 * One-shot setup for a fresh application.
 *
 * Assets and config publish by default — the layout needs the stylesheet, and
 * the menu lives in the config. Views and stubs are opt-in on purpose: a
 * published copy is frozen, so an app that publishes everything stops picking
 * up fixes to the package. Publish them when you actually want to diverge.
 */
class InstallCommand extends Command
{
    protected $signature = 'isproject:install
        {--views : Also publish the layout and partials, to customise them}
        {--stubs : Also publish the generator stubs, to reshape generated code}
        {--all : Publish everything}
        {--force : Overwrite files that have already been published}';

    protected $description = 'Publish the IsProject assets and config, and optionally the views and generator stubs';

    public function handle(): int
    {
        $this->components->info('Installing the IsProject framework');

        // Assets first: without them the layout renders unstyled.
        $this->publish('isproject-assets');
        $this->publish('isproject-config');

        if ($this->option('views') || $this->option('all')) {
            $this->publish('isproject-views');
        }

        if ($this->option('stubs') || $this->option('all')) {
            $this->publish('isproject-stubs');
        }

        $this->newLine();
        $this->components->info('Next steps');
        $this->components->bulletList([
            'Create the tables:   php artisan migrate',
            'Serve the uploads:   php artisan storage:link   (needed for the logo and favicon)',
            'Give User roles:     use IsProject\Framework\Concerns\HasRoles; in app/Models/User.php',
            'Build the matrix:    php artisan isproject:permissions --admin=Administrator --user=you@example.com',
            'Name your system:    visit /settings',
            'Write a migration:   php artisan make:migration create_products_table',
            'Generate the CRUD:   php artisan isproject:crud Product',
            'Or do the lot:       php artisan isproject:crud-all',
        ]);

        $optional = [];

        if (! $this->option('views') && ! $this->option('all')) {
            $optional[] = '--views to copy the layout into resources/views/vendor/isproject';
        }

        if (! $this->option('stubs') && ! $this->option('all')) {
            $optional[] = '--stubs to copy the generator templates into '.config('isproject.stub_path');
        }

        if ($optional !== []) {
            $this->newLine();
            $this->components->info('Re-run with:');
            $this->components->bulletList($optional);
        }

        $this->newLine();
        $this->components->warn('After upgrading the package: php artisan vendor:publish --tag=isproject-assets --force');

        // config/isproject.php is yours once published, so an upgrade cannot
        // touch it. New top-level keys still arrive through mergeConfigFrom,
        // but the menu is a key you already own — a new module will not appear
        // in your sidebar until you add its entry.
        $this->components->warn('A published config keeps its own "menu": add entries for new modules yourself.');

        return self::SUCCESS;
    }

    private function publish(string $tag): void
    {
        $this->callSilently('vendor:publish', array_filter([
            '--tag' => $tag,
            '--force' => $this->option('force'),
        ]));

        $this->components->task("published {$tag}", fn () => true);
    }
}
