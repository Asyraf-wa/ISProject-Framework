<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use IsProject\Framework\Support\FieldDefinition;
use IsProject\Framework\Support\SchemaInspector;

/**
 * Web front end for `isproject:crud` — lists the database tables and generates
 * a module from a button, for developers who are not comfortable at the terminal.
 *
 * This writes PHP files into the application, so the route is registered only
 * when config('isproject.generator') allows it — local environment by default.
 * See EnsureGeneratorIsEnabled for the second line of defence.
 */
class GeneratorController extends Controller
{
    /** Targets a developer may tick, in the order they are shown. */
    private const TARGETS = [
        'model' => 'Model',
        'controller' => 'Controller',
        'requests' => 'Form requests',
        'views' => 'CRUD screens + search',
        'report' => 'Report + CSV export',
        'factory' => 'Factory',
        'policy' => 'Policy',
        'routes' => 'Routes',
    ];

    public function index(Request $request, SchemaInspector $schema, Filesystem $files): View
    {
        $tables = collect($schema->tables())
            ->map(fn (string $table) => $this->describe($table, $schema, $files))
            ->sortBy('table')
            ->values();

        return view('isproject::generator.index', [
            'tables' => $tables,
            'targets' => self::TARGETS,
            'defaults' => (array) config('isproject.generate', []),
            'connection' => config('database.default'),
        ]);
    }

    public function store(Request $request, SchemaInspector $schema): RedirectResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'string'],
            'model' => ['nullable', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['string', 'in:'.implode(',', array_keys(self::TARGETS))],
            'force' => ['nullable', 'boolean'],
        ], [
            'model.regex' => 'The model name must be a single StudlyCase word, e.g. PurchaseOrder.',
            'targets.required' => 'Choose at least one thing to generate.',
        ]);

        // Never take the table name on trust: it reaches an artisan command and
        // a schema lookup, so it must be one this connection actually has.
        if (! in_array($validated['table'], $schema->tables(), true)) {
            return back()->withErrors(['table' => 'That table is not available for generation.']);
        }

        // validate() omits keys the request never sent, so the field may be
        // absent entirely rather than empty — the form leaves it optional.
        $model = ($validated['model'] ?? '') ?: Str::studly(Str::singular($validated['table']));

        $exit = Artisan::call('isproject:crud', [
            'model' => $model,
            '--table' => $validated['table'],
            '--only' => implode(',', $validated['targets']),
            '--force' => (bool) ($validated['force'] ?? false),
        ]);

        $output = trim(Artisan::output());

        if ($exit !== 0) {
            return back()->with('error', "Generation failed for {$model}.")->with('generatorOutput', $output);
        }

        return redirect()
            ->route('isproject.generator.index')
            ->with('success', "Generated {$model} from {$validated['table']}.")
            ->with('generatorOutput', $output)
            ->with('generatedRoute', Str::kebab(Str::pluralStudly($model)));
    }

    /**
     * Delete the files generated for a module.
     *
     * Deletes code, never data: the table and its rows are untouched, and there
     * is no route here that would drop one. Dropping a table is a decision taken
     * in a migration, not a side effect of tidying up.
     *
     * Guarded twice over, because unlike generating, this can destroy work
     * somebody has edited: the model must be one that exists on this
     * connection, and the request has to carry the typed model name back.
     */
    public function destroy(Request $request, SchemaInspector $schema): RedirectResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]*$/'],
            'confirm' => ['required', 'string'],
        ], [
            'model.regex' => 'The model name must be a single StudlyCase word.',
            'confirm.required' => 'Type the model name to confirm.',
        ]);

        $model = $validated['model'];

        // Typed, not ticked. A checkbox is muscle memory; a name is a decision.
        if (! hash_equals($model, trim($validated['confirm']))) {
            return back()->withErrors(['confirm' => "Type {$model} exactly to confirm removal."]);
        }

        // Only modules that belong to a table this connection actually has, so
        // the name can never be an arbitrary path fragment.
        $known = collect($schema->tables())
            ->map(fn (string $table) => Str::studly(Str::singular($table)))
            ->contains($model);

        if (! $known) {
            return back()->withErrors(['model' => 'There is no table for that model on this connection.']);
        }

        $exit = Artisan::call('isproject:crud-remove', ['model' => $model, '--force' => true]);
        $output = trim(Artisan::output());

        if ($exit !== 0) {
            return back()->with('error', "Could not remove {$model}.")->with('generatorOutput', $output);
        }

        return redirect()
            ->route('isproject.generator.index')
            ->with('success', "Removed the generated files for {$model}. The table and its data are untouched.")
            ->with('generatorOutput', $output);
    }

    /**
     * Write the migration that adds archived_at to a table.
     *
     * Writes it; does not run it — the same rule the command follows. A button
     * in a browser that writes a file is one thing; a button that changes the
     * database structure is another, and migrations have to stay the record of
     * how the schema got that way.
     */
    public function archivable(Request $request, SchemaInspector $schema): RedirectResponse
    {
        $validated = $request->validate(['table' => ['required', 'string']]);

        if (! in_array($validated['table'], $schema->tables(), true)) {
            return back()->withErrors(['table' => 'That table is not available.']);
        }

        $exit = Artisan::call('isproject:archivable', ['table' => $validated['table']]);
        $output = trim(Artisan::output());

        if ($exit !== 0) {
            return back()->with('error', "Could not prepare {$validated['table']} for archiving.")
                ->with('generatorOutput', $output);
        }

        return back()
            ->with('success', "Migration written for {$validated['table']}. Run: php artisan migrate")
            ->with('generatorOutput', $output);
    }

    /**
     * Everything the listing shows for one table: its shape, and which parts of
     * the module already exist on disk.
     *
     * @return array<string, mixed>
     */
    private function describe(string $table, SchemaInspector $schema, Filesystem $files): array
    {
        $model = Str::studly(Str::singular($table));
        $fields = $schema->fields($table);
        $folder = Str::kebab(Str::pluralStudly($model));

        $existing = array_filter([
            'model' => $files->exists(base_path(config('isproject.paths.model')."/{$model}.php")),
            'controller' => $files->exists(base_path(config('isproject.paths.controller')."/{$model}Controller.php")),
            'views' => $files->isDirectory(base_path(config('isproject.paths.views').'/'.$folder)),
            'report' => $files->exists(base_path(config('isproject.paths.views').'/'.$folder.'/report.blade.php')),
        ]);

        return [
            'table' => $table,
            'model' => $model,
            'route' => $folder,
            'columns' => $fields->count(),
            'rows' => $this->rowCount($table),
            'relations' => $fields->filter(fn (FieldDefinition $f) => $f->isForeignKey())
                ->map(fn (FieldDefinition $f) => $f->foreignTable)->unique()->values()->all(),
            'searchable' => $fields->filter(fn (FieldDefinition $f) => $f->isSearchable())->count(),
            'soft_deletes' => $schema->hasSoftDeletes($table),
            'archivable' => $schema->hasArchive($table),
            'existing' => array_keys($existing),

            // Shown before the remove button, so nobody has to guess what a
            // deletion covers.
            'removable' => $this->removable($files, $model, $folder),

            'preview' => $fields->take(6)->map(fn (FieldDefinition $f) => [
                'name' => $f->name,
                'type' => $f->logicalType,
                'nullable' => $f->nullable,
            ])->all(),
        ];
    }

    /**
     * The generated files that exist for this module, relative to the project.
     *
     * Derived from the same config paths `isproject:crud-remove` uses, so the
     * list on screen is the list that gets deleted.
     *
     * @return array<int, string>
     */
    private function removable(Filesystem $files, string $model, string $folder): array
    {
        $candidates = [
            config('isproject.paths.model')."/{$model}.php",
            config('isproject.paths.controller')."/{$model}Controller.php",
            config('isproject.paths.request')."/Store{$model}Request.php",
            config('isproject.paths.request')."/Update{$model}Request.php",
            config('isproject.paths.policy')."/{$model}Policy.php",
            config('isproject.paths.factory')."/{$model}Factory.php",
        ];

        $present = array_values(array_filter(
            $candidates,
            fn (string $path) => $files->exists(base_path($path)),
        ));

        if ($files->isDirectory(base_path(config('isproject.paths.views').'/'.$folder))) {
            $present[] = config('isproject.paths.views').'/'.$folder.'/';
        }

        return $present;
    }

    /** A table can be huge or missing; neither should break the listing. */
    private function rowCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
