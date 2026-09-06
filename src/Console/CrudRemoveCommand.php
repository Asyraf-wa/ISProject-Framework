<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Removes the files `isproject:crud` generated for one model.
 *
 *   php artisan isproject:crud-remove Product --dry-run
 *   php artisan isproject:crud-remove Product
 *
 * Deletes code, never data. The table, its rows and its migration are left
 * exactly as they are — dropping a table is a different decision, taken with a
 * migration, not as a side effect of tidying up some PHP files.
 *
 * The files it removes are the ones the generator writes, worked out from the
 * same config paths. Anything a student added of their own accord elsewhere is
 * not touched, and the command says what it did rather than reporting a total.
 */
class CrudRemoveCommand extends Command
{
    protected $signature = 'isproject:crud-remove
        {model : Model class name, e.g. Product}
        {--dry-run : List what would go, delete nothing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Delete the files generated for a CRUD module (never the database table)';

    public function handle(Filesystem $files): int
    {
        $argument = trim((string) $this->argument('model'));

        // Checked *before* Str::studly(), not after. Studly quietly strips
        // slashes and dots, so "../../etc/passwd" would arrive here as a
        // harmless "EtcPasswd" and the command would report success having
        // done nothing — which tells whoever typed it precisely nothing.
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $argument)) {
            $this->components->error('The model name must be a single word of letters and digits, e.g. PurchaseOrder.');

            return self::FAILURE;
        }

        $model = Str::studly(Str::singular($argument));

        $targets = $this->targets($files, $model);
        $present = array_filter($targets, fn (array $t) => $t['exists']);

        if ($present === []) {
            $this->components->warn("Nothing generated for [{$model}] was found.");

            return self::SUCCESS;
        }

        $this->components->info(($this->option('dry-run') ? 'Would remove' : 'Removing')." files for [{$model}]");
        $this->table(['Type', 'Path'], array_map(
            fn (array $t) => [$t['label'], $t['relative']],
            $present,
        ));

        if ($this->option('dry-run')) {
            $this->components->info('Nothing was deleted. Drop --dry-run to go ahead.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete these files? The {$model} table and its data are not touched.")) {
            $this->components->info('Left alone.');

            return self::SUCCESS;
        }

        foreach ($present as $target) {
            $target['directory']
                ? $files->deleteDirectory($target['path'])
                : $files->delete($target['path']);

            $this->components->twoColumnDetail("<fg=red>removed</> {$target['relative']}", $target['label']);
        }

        $this->removeRoutes($files, $model);

        $this->newLine();
        $this->components->info("The {$this->tableName($model)} table and everything in it is untouched.");
        $this->components->warn('If you pasted a sidebar entry into config/isproject.php, remove it there — the menu hides entries whose route no longer exists, so nothing breaks either way.');

        return self::SUCCESS;
    }

    /**
     * Everything the generator writes for this model, whether or not it is
     * there. Built from the same config paths, so the two cannot drift.
     *
     * @return array<int, array{label: string, path: string, relative: string, directory: bool, exists: bool}>
     */
    private function targets(Filesystem $files, string $model): array
    {
        $folder = Str::kebab(Str::pluralStudly($model));

        $paths = [
            ['Model', config('isproject.paths.model')."/{$model}.php", false],
            ['Controller', config('isproject.paths.controller')."/{$model}Controller.php", false],
            ['Form request', config('isproject.paths.request')."/Store{$model}Request.php", false],
            ['Form request', config('isproject.paths.request')."/Update{$model}Request.php", false],
            ['Policy', config('isproject.paths.policy')."/{$model}Policy.php", false],
            ['Factory', config('isproject.paths.factory')."/{$model}Factory.php", false],
            ['Views', config('isproject.paths.views')."/{$folder}", true],
        ];

        return array_map(function (array $entry) use ($files) {
            [$label, $relative, $directory] = $entry;

            $path = base_path($relative);

            return [
                'label' => $label,
                'path' => $path,
                'relative' => $relative,
                'directory' => $directory,
                'exists' => $directory ? $files->isDirectory($path) : $files->exists($path),
            ];
        }, $paths);
    }

    /**
     * Drop the route lines that point at the controller being deleted.
     *
     * Matched on the controller class rather than on the route names, because a
     * route referencing a controller that no longer exists is broken whoever
     * wrote it. Only complete single-line statements are removed: a multi-line
     * route definition would be corrupted by deleting one line of it, so those
     * are reported and left for a human.
     */
    private function removeRoutes(Filesystem $files, string $model): void
    {
        $routesFile = config('isproject.routes_file');

        if (! $routesFile || ! $files->exists(base_path($routesFile))) {
            return;
        }

        $path = base_path($routesFile);
        $needle = $model.'Controller::class';

        $kept = [];
        $removed = 0;
        $skipped = 0;

        foreach (explode("\n", $files->get($path)) as $line) {
            if (! str_contains($line, $needle)) {
                $kept[] = $line;

                continue;
            }

            if (str_ends_with(rtrim($line), ';')) {
                $removed++;

                continue;
            }

            $kept[] = $line;
            $skipped++;
        }

        if ($removed > 0) {
            $files->put($path, rtrim(implode("\n", $kept))."\n");
            $this->components->twoColumnDetail("<fg=red>removed</> {$routesFile}", "{$removed} route ".Str::plural('line', $removed));
        }

        if ($skipped > 0) {
            $this->components->warn("{$skipped} route ".Str::plural('line', $skipped)." in {$routesFile} span several lines and were left for you to check.");
        }
    }

    /**
     * The table name this model would have used, for the closing message.
     *
     * Not called table(): Command::table() renders the list of files above, and
     * shadowing it would break that.
     */
    private function tableName(string $model): string
    {
        return Str::snake(Str::pluralStudly($model));
    }
}
