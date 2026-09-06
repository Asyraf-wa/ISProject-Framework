<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use IsProject\Framework\Support\SchemaInspector;

/**
 * Run the CRUD generator across every table in the connection, skipping
 * framework plumbing listed in config ignored_tables.
 *
 *   php artisan isproject:crud-all
 *   php artisan isproject:crud-all --skip=users,sessions --force
 */
class CrudAllCommand extends Command
{
    protected $signature = 'isproject:crud-all
        {--skip= : Comma separated tables to leave alone (on top of config ignored_tables)}
        {--tables= : Comma separated allow-list; generate only these tables}
        {--only= : Comma separated targets passed through to isproject:crud}
        {--connection= : Database connection to introspect}
        {--force : Overwrite files that already exist}';

    protected $description = 'Generate a CRUD stack for every table in the database';

    public function handle(): int
    {
        $schema = new SchemaInspector($this->option('connection'));
        $tables = collect($schema->tables());

        if ($allow = $this->option('tables')) {
            $allowed = array_map('trim', explode(',', (string) $allow));
            $tables = $tables->filter(fn (string $table) => in_array($table, $allowed, true));
        }

        if ($skip = $this->option('skip')) {
            $skipped = array_map('trim', explode(',', (string) $skip));
            $tables = $tables->reject(fn (string $table) => in_array($table, $skipped, true));
        }

        if ($tables->isEmpty()) {
            $this->components->warn('No tables left to generate. Check --tables/--skip and config isproject.ignored_tables.');

            return self::SUCCESS;
        }

        $this->components->info('Generating CRUD for: '.$tables->implode(', '));
        $this->newLine();

        $failed = [];

        foreach ($tables as $table) {
            $model = Str::studly(Str::singular($table));

            $status = $this->call('isproject:crud', array_filter([
                'model' => $model,
                '--table' => $table,
                '--only' => $this->option('only'),
                '--connection' => $this->option('connection'),
                '--force' => $this->option('force'),
            ]));

            if ($status !== self::SUCCESS) {
                $failed[] = $table;
            }
        }

        if ($failed !== []) {
            $this->components->error('Failed for: '.implode(', ', $failed));

            return self::FAILURE;
        }

        $this->components->info('All tables generated. Review the generated policies before using this anywhere real.');

        return self::SUCCESS;
    }
}
