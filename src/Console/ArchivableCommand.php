<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use IsProject\Framework\Support\SchemaInspector;

/**
 * Writes the migration that adds archived_at to a table.
 *
 * Writes it; does not run it. The generator's rule everywhere else is that it
 * produces code and you run it, and that matters more here than anywhere: if a
 * column appeared without a migration, a colleague cloning the repo and running
 * migrate:fresh would get a different table, and the archive screen would work
 * on one machine and not the other.
 */
class ArchivableCommand extends Command
{
    protected $signature = 'isproject:archivable
        {table : Table to add archiving to, e.g. books}
        {--connection= : Database connection to check}
        {--force : Write the migration even if the column seems to exist}';

    protected $description = 'Create a migration adding an archived_at column to a table';

    public function handle(Filesystem $files): int
    {
        $table = Str::snake(trim($this->argument('table')));
        $schema = new SchemaInspector($this->option('connection'));

        if (! $schema->hasTable($table)) {
            $this->components->error("Table [{$table}] does not exist.");
            $this->line('  Available tables: '.implode(', ', $schema->tables()));

            return self::FAILURE;
        }

        if ($schema->hasArchive($table) && ! $this->option('force')) {
            $this->components->warn("Table [{$table}] already has an archived_at column.");
            $this->components->info('Run: php artisan isproject:crud '.Str::studly(Str::singular($table)).' --force');

            return self::SUCCESS;
        }

        $path = $this->write($files, $table);

        $this->components->info('Migration created: '.str_replace(base_path().'/', '', $path));
        $this->newLine();

        $this->components->info('Next steps');
        $this->components->bulletList([
            'Run it:              php artisan migrate',
            'Regenerate the CRUD: php artisan isproject:crud '.Str::studly(Str::singular($table)).' --force',
        ]);

        return self::SUCCESS;
    }

    private function write(Filesystem $files, string $table): string
    {
        $directory = base_path('database/migrations');
        $files->ensureDirectoryExists($directory);

        $path = $directory.'/'.date('Y_m_d_His')."_add_archived_at_to_{$table}_table.php";

        $files->put($path, <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    // Nullable: null means active, a timestamp means archived,
                    // and the value is when it happened. Indexed because every
                    // ordinary query on this table now filters on it.
                    \$table->archivable();
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    \$table->dropArchivable();
                });
            }
        };

        PHP);

        return $path;
    }
}
