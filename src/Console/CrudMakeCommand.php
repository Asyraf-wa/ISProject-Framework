<?php

namespace IsProject\Framework\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IsProject\Framework\Support\FieldDefinition;
use IsProject\Framework\Support\SchemaInspector;
use IsProject\Framework\Support\StubRenderer;

/**
 * Read one existing table, write the whole stack.
 *
 *   php artisan isproject:crud Product
 *   php artisan isproject:crud Product --table=tbl_products --force
 *   php artisan isproject:crud Product --only=views,controller
 *
 * Every fragment below is built without leading indentation and indented once,
 * at substitution time, to match the column the placeholder sits in.
 */
class CrudMakeCommand extends Command
{
    protected $signature = 'isproject:crud
        {model : Model class name, e.g. Product}
        {--table= : Table to introspect (default: plural snake case of the model)}
        {--only= : Comma separated subset of model,controller,requests,views,factory,policy,routes}
        {--except= : Comma separated targets to skip}
        {--connection= : Database connection to introspect}
        {--force : Overwrite files that already exist}';

    protected $description = 'Generate a CRUD stack (model, controller, requests, Blade views, factory, policy, routes) from an existing database table';

    private SchemaInspector $schema;

    private StubRenderer $renderer;

    /** @var array<int, array{0: string, 1: string}> */
    private array $written = [];

    public function handle(Filesystem $files, StubRenderer $renderer): int
    {
        $this->renderer = $renderer;
        $this->schema = new SchemaInspector($this->option('connection'));

        $model = Str::studly(Str::singular($this->argument('model')));
        $table = $this->option('table') ?: Str::snake(Str::pluralStudly($model));

        if (! $this->schema->hasTable($table)) {
            $this->components->error("Table [{$table}] does not exist. Write and run the migration first.");
            $this->line('  Available tables: '.implode(', ', $this->schema->tables()));

            return self::FAILURE;
        }

        $fields = $this->schema->fields($table);

        if ($fields->isEmpty()) {
            $this->components->warn("Table [{$table}] has no generatable columns — they are all in ignored_columns.");
        }

        $replacements = $this->replacements($model, $table, $fields);

        $this->components->info("Generating CRUD for [{$model}] from table [{$table}]");

        foreach ($this->targets() as $target) {
            match ($target) {
                'model' => $this->writeModel($files, $replacements),
                'controller' => $this->writeController($files, $replacements),
                'requests' => $this->writeRequests($files, $replacements),
                'views' => $this->writeViews($files, $replacements),
                'report' => $this->writeReport($files, $replacements),
                'factory' => $this->writeFactory($files, $replacements),
                'policy' => $this->writePolicy($files, $replacements),
                'routes' => $this->writeRoutes($files, $replacements),
                default => $this->components->warn("Unknown target [{$target}], skipped."),
            };
        }

        $this->newLine();
        $this->table(['Status', 'File'], $this->written);
        $this->newLine();
        $this->components->info("Done. Log in, then visit /{$replacements['routeUri']}");

        $this->menuHint($replacements);

        return self::SUCCESS;
    }

    /**
     * Which artefacts to emit: --only wins over config, then --except removes.
     *
     * @return array<int, string>
     */
    private function targets(): array
    {
        $targets = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : (array) config('isproject.generate', []);

        if ($except = $this->option('except')) {
            $targets = array_diff($targets, array_map('trim', explode(',', (string) $except)));
        }

        return array_values(array_filter($targets));
    }

    /**
     * Every placeholder the stubs can reference.
     *
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array<string, string|int>
     */
    private function replacements(string $model, string $table, Collection $fields): array
    {
        $variable = Str::camel($model);
        $plural = Str::pluralStudly($model);
        $folder = Str::kebab($plural);
        $prefix = trim((string) config('isproject.route_prefix', ''), '/');
        $softDeletes = $this->schema->hasSoftDeletes($table);
        $archivable = $this->schema->hasArchive($table);
        $timestamps = $this->schema->hasTimestamps($table);

        // Route::resource derives its wildcard from the URI, not the model:
        // "purchase-orders" yields {purchase_order}, not {purchaseOrder}.
        $routeParameter = str_replace('-', '_', Str::singular($folder));

        $r = [
            'model' => $model,
            'modelVariable' => $variable,
            'modelPlural' => $plural,
            'modelVariablePlural' => Str::camel($plural),
            'table' => $table,
            'primaryKey' => $this->schema->primaryKey($table),
            'viewFolder' => $folder,
            'routeName' => $folder,
            'routeParameter' => $routeParameter,
            'routeUri' => $prefix ? $prefix.'/'.$folder : $folder,
            'title' => Str::headline($plural),
            'titleSingular' => Str::headline($model),
            'layout' => (string) config('isproject.layout'),
            'perPage' => (int) config('isproject.per_page', 15),

            'modelNamespace' => (string) config('isproject.namespaces.model'),
            'controllerNamespace' => (string) config('isproject.namespaces.controller'),
            'requestNamespace' => (string) config('isproject.namespaces.request'),
            'policyNamespace' => (string) config('isproject.namespaces.policy'),
            'factoryNamespace' => (string) config('isproject.namespaces.factory'),

            'modelImports' => $this->buildModelImports($fields, $softDeletes, $archivable),
            'softDeletesTrait' => $softDeletes ? ', SoftDeletes' : '',
            'archivableTrait' => $archivable ? ', Archivable' : '',
            'auditableTrait' => $this->auditsGeneratedModels() ? ', Auditable' : '',

            // Filled in below: the archive fragments are written in terms of the
            // other placeholders, and the renderer makes a single pass.
            'archiveActions' => '',
            'archiveRowActions' => '',
            'archiveTabs' => '',
            'archivePolicyMethods' => '',

            'archiveStates' => $archivable && $softDeletes ? "'archived', 'deleted'" : ($archivable ? "'archived'" : "'deleted'"),
            'archiveDefaultState' => $archivable ? 'archived' : 'deleted',
            // Parenthesised: everything chained after this — the search scope,
            // the ordering, the paginate — has to apply to whichever branch the
            // ternary picked, not only to the second one.
            'archiveScope' => match (true) {
                $archivable && $softDeletes => "(\$state === 'deleted'\n            ? {$model}::onlyTrashed()\n            : {$model}::onlyArchived())",
                $archivable => "{$model}::onlyArchived()",
                default => "{$model}::onlyTrashed()",
            },
            'restoreScopes' => match (true) {
                $archivable && $softDeletes => '->withArchived()->withTrashed()',
                $archivable => '->withArchived()',
                default => '->withTrashed()',
            },
            'restoreBody' => match (true) {
                $archivable && $softDeletes => "        \${$variable}->trashed() ? \${$variable}->restore() : \${$variable}->unarchive();",
                $archivable => "        \${$variable}->unarchive();",
                default => "        \${$variable}->restore();",
            },
            'timestamps' => $timestamps ? '' : "\n    public \$timestamps = false;\n",
            'defaultOrder' => $timestamps ? '->latest()' : "->orderByDesc('".$this->schema->primaryKey($table)."')",

            'fillable' => $this->indent($this->buildFillable($fields), 8),
            'casts' => $this->indent($this->buildCasts($fields), 12),
            'relations' => $this->buildRelations($fields),
            'rulesStore' => $this->indent($this->buildRules($fields, $table, null), 12),
            'rulesUpdate' => $this->indent($this->buildRules($fields, $table, "\$this->route('{$routeParameter}')"), 12),
            'attributeLabels' => $this->indent($this->buildAttributeLabels($fields), 12),
            'formOptions' => $this->indent($this->buildFormOptions($fields), 12),
            'factoryDefinition' => $this->indent($this->buildFactoryDefinition($fields), 12),
            'searchScope' => $this->buildSearchScope($fields, $table),
            'sortable' => $this->buildSortable($fields, $table),
            'eagerLoad' => $this->buildEagerLoad($fields),

            'reportDateColumn' => $this->reportDateColumn($table, $fields),
            'reportFilters' => $this->buildReportFilters($table, $fields),
            'reportFilterInputs' => $this->indent($this->buildReportFilterInputs($table, $fields), 16),
            'reportSummary' => $this->indent($this->buildReportSummary($fields), 12),
            'reportHeaders' => $this->indent($this->buildReportHeaders($fields), 28),
            'reportCells' => $this->indent($this->buildReportCells($fields, $variable), 32),
            'csvHeaders' => $this->buildCsvHeaders($fields),
            'csvRow' => $this->buildCsvRow($fields, $variable),

            'indexHeaders' => $this->indent($this->buildIndexHeaders($fields), 28),
            'indexCells' => $this->indent($this->buildIndexCells($fields, $variable), 32),
            'formFields' => $this->indent($this->buildFormFields($fields, $variable), 4),
            'guideRequired' => $this->indent($this->buildGuideRequired($fields), 8),
            'guideNotes' => $this->indent($this->buildGuideNotes($fields), 8),
            'showRows' => $this->indent($this->buildShowRows($fields, $variable), 24),
        ];

        return $this->withArchiveFragments($r, $archivable, $softDeletes);
    }

    /**
     * Add the archive fragments once every other placeholder is known.
     *
     * They are rendered from their own stubs and reference the rest of the
     * replacements, and StubRenderer makes a single pass — so building them
     * inside the array above would leave %%titleSingular%% sitting in generated
     * code. Doing it here means they are expanded against a finished array.
     *
     * @param  array<string, string|int>  $r
     * @return array<string, string|int>
     */
    private function withArchiveFragments(array $r, bool $archivable, bool $softDeletes): array
    {
        if (! $archivable && ! $softDeletes) {
            return $r;
        }

        $actions = ['archive/screen.stub'];

        if ($archivable) {
            $actions[] = 'archive/archive.stub';
        }

        $actions[] = 'archive/restore.stub';

        if ($softDeletes) {
            $actions[] = 'archive/purge.stub';
        }

        $r['archiveActions'] = implode('', array_map(
            fn (string $stub) => $this->renderer->render("crud/{$stub}", $r),
            $actions,
        ));

        $r['archivePolicyMethods'] = $this->buildArchivePolicyMethods($r, $archivable, $softDeletes);
        $r['archiveTabs'] = $this->indent($this->buildArchiveTabs($r, $archivable, $softDeletes), 24);
        $r['archiveRowActions'] = $this->indent($this->buildArchiveRowActions($r, $archivable), 36);

        return $r;
    }

    /** Archive button for one row of the index table. */
    private function buildArchiveRowActions(array $r, bool $archivable): string
    {
        if (! $archivable) {
            return '';
        }

        return <<<BLADE
            <form method="POST" action="{{ route('{$r['routeName']}.archive', \${$r['modelVariable']}) }}">
                @csrf
                <button type="submit" class="dropdown-item">
                    <x-isproject::icon name="inbox" /> Archive
                </button>
            </form>
            BLADE;
    }

    /** Tab strip for the archive screen; only worth rendering when there are two. */
    private function buildArchiveTabs(array $r, bool $archivable, bool $softDeletes): string
    {
        if (! $archivable || ! $softDeletes) {
            return '';
        }

        return <<<BLADE
            <ul class="nav nav-pills is-archive-tabs">
                <li class="nav-item">
                    <a class="nav-link @if (\$state === 'archived') active @endif"
                       href="{{ route('{$r['routeName']}.archived', ['state' => 'archived']) }}">Archived</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if (\$state === 'deleted') active @endif"
                       href="{{ route('{$r['routeName']}.archived', ['state' => 'deleted']) }}">Deleted</a>
                </li>
            </ul>
            BLADE;
    }

    /** Policy methods for whichever states the table supports. */
    private function buildArchivePolicyMethods(array $r, bool $archivable, bool $softDeletes): string
    {
        $methods = [];

        if ($archivable) {
            $methods[] = "    public function archive(User \$user, {$r['model']} \${$r['modelVariable']}): bool\n    {\n        return true;\n    }";
        }

        if ($softDeletes) {
            $methods[] = "    /** Permanent deletion — the first of these worth tightening. */\n    public function purge(User \$user, {$r['model']} \${$r['modelVariable']}): bool\n    {\n        return true;\n    }";
        }

        return $methods === [] ? '' : "\n\n".implode("\n\n", $methods);
    }

    // ------------------------------------------------------------ PHP fragments

    /**
     * Whether generated models record their own changes.
     *
     * On by default: a module generated by this command brings its audit trail
     * with it, in the same way it brings its permissions. One config key turns
     * it off, and deleting the trait from a model turns it off for that model.
     */
    private function auditsGeneratedModels(): bool
    {
        return (bool) config('isproject.audit.generated_models', true);
    }

    private function buildModelImports(Collection $fields, bool $softDeletes, bool $archivable = false): string
    {
        $imports = [
            'Illuminate\Database\Eloquent\Factories\HasFactory',
            'Illuminate\Database\Eloquent\Model',
        ];

        if ($fields->contains(fn (FieldDefinition $field) => $field->isForeignKey())) {
            $imports[] = 'Illuminate\Database\Eloquent\Relations\BelongsTo';
        }

        if ($softDeletes) {
            $imports[] = 'Illuminate\Database\Eloquent\SoftDeletes';
        }

        if ($archivable) {
            $imports[] = 'IsProject\Framework\Concerns\Archivable';
        }

        if ($this->auditsGeneratedModels()) {
            $imports[] = 'IsProject\Framework\Concerns\Auditable';
        }

        sort($imports);

        return collect($imports)->map(fn (string $import) => "use {$import};")->implode("\n");
    }

    private function buildFillable(Collection $fields): string
    {
        return $fields->map(fn (FieldDefinition $field) => "'{$field->name}',")->implode("\n");
    }

    private function buildCasts(Collection $fields): string
    {
        $casts = $fields
            ->filter(fn (FieldDefinition $field) => $field->cast() !== null)
            ->map(fn (FieldDefinition $field) => "'{$field->name}' => '{$field->cast()}',")
            ->implode("\n");

        return $casts !== '' ? $casts : '//';
    }

    /** A belongsTo method for every single-column foreign key on the table. */
    private function buildRelations(Collection $fields): string
    {
        $methods = $fields
            ->filter(fn (FieldDefinition $field) => $field->isForeignKey())
            ->map(function (FieldDefinition $field) {
                $block = strtr(<<<'PHP'
                    public function @METHOD@(): BelongsTo
                    {
                        return $this->belongsTo(@RELATED@::class, '@COLUMN@', '@OWNER@');
                    }
                    PHP, [
                    '@METHOD@' => $field->relationName(),
                    '@RELATED@' => $field->relatedModel(),
                    '@COLUMN@' => $field->name,
                    '@OWNER@' => $field->foreignColumn,
                ]);

                return $this->indent($block, 4);
            })
            ->implode("\n\n");

        return $methods !== '' ? "\n".$methods."\n" : '';
    }

    private function buildRules(Collection $fields, string $table, ?string $ignore): string
    {
        return $this->editableFields($fields)
            ->map(function (FieldDefinition $field) use ($table, $ignore) {
                $rules = str_replace('%%table%%', $table, implode(', ', $field->rules($ignore)));

                return "'{$field->name}' => [{$rules}],";
            })
            ->implode("\n");
    }

    /** Friendly names so messages read "category" rather than "category id". */
    private function buildAttributeLabels(Collection $fields): string
    {
        $labels = $this->editableFields($fields)
            ->map(fn (FieldDefinition $field) => "'{$field->name}' => '".Str::lower($field->label())."',")
            ->implode("\n");

        return $labels !== '' ? $labels : '//';
    }

    /** The ->when(...) keyword search clause, or nothing if no text columns. */
    private function buildSearchScope(Collection $fields, string $table): string
    {
        $searchable = $fields->filter(fn (FieldDefinition $field) => $field->isSearchable())->values();

        if ($searchable->isEmpty()) {
            return '';
        }

        $clauses = $searchable
            ->map(function (FieldDefinition $field, int $index) use ($table) {
                $method = $index === 0 ? 'where' : 'orWhere';

                // Qualified with the table name: sorting by a related column
                // joins that table in, and a bare "name" would then be
                // ambiguous between the two.
                return "        \$query->{$method}('{$table}.{$field->name}', 'like', \"%{\$search}%\");";
            })
            ->implode("\n");

        $block = strtr(<<<'PHP'
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
            @CLAUSES@
                });
            })
            PHP, ['@CLAUSES@' => $clauses]);

        return "\n".$this->indent($block, 12);
    }

    private function buildEagerLoad(Collection $fields): string
    {
        $relations = $fields
            ->filter(fn (FieldDefinition $field) => $field->isForeignKey())
            ->map(fn (FieldDefinition $field) => "'".$field->relationName()."'")
            ->implode(', ');

        return $relations !== '' ? "\n            ->with([{$relations}])" : '';
    }

    /**
     * Select options for each related model, keyed by the plural relation name.
     * The label column is the first human-readable column of the related table.
     */
    private function buildFormOptions(Collection $fields): string
    {
        $options = $fields
            ->filter(fn (FieldDefinition $field) => $field->isForeignKey())
            ->map(function (FieldDefinition $field) {
                $label = $this->labelColumnFor($field->foreignTable, $field->foreignColumn);
                $key = Str::camel(Str::plural($field->relationName()));
                $model = config('isproject.namespaces.model').'\\'.$field->relatedModel();

                return "'{$key}' => \\{$model}::orderBy('{$label}')->pluck('{$label}', '{$field->foreignColumn}'),";
            })
            ->implode("\n");

        return $options !== '' ? $options : '//';
    }

    private function buildFactoryDefinition(Collection $fields): string
    {
        $definition = $fields
            ->map(fn (FieldDefinition $field) => "'{$field->name}' => {$field->fake()},")
            ->implode("\n");

        return $definition !== '' ? $definition : '//';
    }

    // --------------------------------------------------------------- Report

    /**
     * The column a date-range filter should run against: created_at when the
     * table has timestamps, otherwise the first date column, else none.
     */
    private function reportDateColumn(string $table, Collection $fields): string
    {
        if ($this->schema->hasColumn($table, 'created_at')) {
            return 'created_at';
        }

        $date = $fields->first(fn (FieldDefinition $field) => in_array(
            $field->logicalType,
            [FieldDefinition::TYPE_DATE, FieldDefinition::TYPE_DATETIME],
            true
        ));

        return $date?->name ?? '';
    }

    /** ->when() clauses for the date range and each low-cardinality column. */
    private function buildReportFilters(string $table, Collection $fields): string
    {
        $clauses = [];

        if ($date = $this->reportDateColumn($table, $fields)) {
            $clauses[] = "->when(\$request->date('from'), fn (Builder \$q, \$from) => \$q->whereDate('{$date}', '>=', \$from))";
            $clauses[] = "->when(\$request->date('to'), fn (Builder \$q, \$to) => \$q->whereDate('{$date}', '<=', \$to))";
        }

        foreach ($this->filterableFields($fields) as $field) {
            $clauses[] = "->when(\$request->filled('{$field->name}'), fn (Builder \$q) => \$q->where('{$field->name}', \$request->query('{$field->name}')))";
        }

        return $clauses === [] ? '' : "\n".$this->indent(implode("\n", $clauses), 12);
    }

    /** Form controls for those same filters. */
    private function buildReportFilterInputs(string $table, Collection $fields): string
    {
        $inputs = [];

        if ($this->reportDateColumn($table, $fields)) {
            foreach ([['from', 'From'], ['to', 'To']] as [$name, $label]) {
                $inputs[] = strtr(<<<'BLADE'
                    <div class="col-6 col-sm-3 col-lg-2">
                        <label class="form-label" for="@NAME@">@LABEL@@MARK@</label>
                        <input type="date" id="@NAME@" name="@NAME@" class="form-control" value="{{ request('@NAME@') }}">
                    </div>
                    BLADE, ['@NAME@' => $name, '@LABEL@' => $label]);
            }
        }

        foreach ($this->filterableFields($fields) as $field) {
            $options = $field->isForeignKey()
                ? strtr(<<<'BLADE'
                            @foreach ($@COLLECTION@ as $value => $optionLabel)
                                <option value="{{ $value }}" @selected(request('@NAME@') == $value)>{{ $optionLabel }}</option>
                            @endforeach
                    BLADE, [
                    '@COLLECTION@' => Str::camel(Str::plural((string) $field->relationName())),
                    '@NAME@' => $field->name,
                ])
                : collect($field->enumValues ?: ['1', '0'])
                    ->map(fn (string $value) => '        <option value="'.e($value).'" @selected(request(\''.$field->name.'\') === \''.$value.'\')>'
                        .e($field->logicalType === FieldDefinition::TYPE_BOOLEAN ? ($value === '1' ? 'Yes' : 'No') : Str::headline($value)).'</option>')
                    ->implode("\n");

            $inputs[] = strtr(<<<'BLADE'
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="filter-@NAME@">@LABEL@</label>
                    <select class="form-select" id="filter-@NAME@" name="@NAME@">
                        <option value="">All</option>
                @OPTIONS@
                    </select>
                </div>
                BLADE, ['@NAME@' => $field->name, '@LABEL@' => e($field->label()), '@OPTIONS@' => $options]);
        }

        return implode("\n\n", $inputs);
    }

    /**
     * Columns worth offering as a dropdown filter: enums, booleans and foreign
     * keys. Free text is already covered by the keyword search.
     *
     * @return Collection<int, FieldDefinition>
     */
    private function filterableFields(Collection $fields): Collection
    {
        return $fields->filter(fn (FieldDefinition $field) => $field->isForeignKey()
            || $field->logicalType === FieldDefinition::TYPE_ENUM
            || $field->logicalType === FieldDefinition::TYPE_BOOLEAN)->values();
    }

    /** Totals and averages for the numeric columns, shown as summary tiles. */
    private function buildReportSummary(Collection $fields): string
    {
        return $fields
            ->filter(fn (FieldDefinition $field) => in_array(
                $field->logicalType,
                [FieldDefinition::TYPE_INTEGER, FieldDefinition::TYPE_DECIMAL],
                true
            ) && ! $field->isForeignKey())
            ->take(3)
            ->map(function (FieldDefinition $field) {
                $label = 'Total '.Str::lower($field->label());

                return "'{$label}' => number_format((clone \$query)->sum('{$field->name}'), 2),";
            })
            ->implode("\n");
    }

    private function buildReportHeaders(Collection $fields): string
    {
        return $fields
            ->filter(fn (FieldDefinition $field) => $field->showOnIndex())
            ->map(fn (FieldDefinition $field) => '<th>'.e($field->label()).'</th>')
            ->implode("\n");
    }

    private function buildReportCells(Collection $fields, string $variable): string
    {
        return $fields
            ->filter(fn (FieldDefinition $field) => $field->showOnIndex())
            ->map(fn (FieldDefinition $field) => '<td>'.$this->displayExpression($field, $variable).'</td>')
            ->implode("\n");
    }

    /** Every column goes into the CSV, including the ones the table omits. */
    private function buildCsvHeaders(Collection $fields): string
    {
        return $fields
            ->map(fn (FieldDefinition $field) => "'".addslashes($field->label())."'")
            ->implode(', ');
    }

    private function buildCsvRow(Collection $fields, string $variable): string
    {
        return $fields
            ->map(function (FieldDefinition $field) use ($variable) {
                if ($field->isForeignKey()) {
                    $label = $this->labelColumnFor($field->foreignTable, $field->foreignColumn);

                    return "\${$variable}->{$field->relationName()}?->{$label}";
                }

                return match ($field->logicalType) {
                    FieldDefinition::TYPE_BOOLEAN => "\${$variable}->{$field->name} ? 'Yes' : 'No'",
                    FieldDefinition::TYPE_DATE => "\${$variable}->{$field->name}?->format('Y-m-d')",
                    FieldDefinition::TYPE_DATETIME => "\${$variable}->{$field->name}?->format('Y-m-d H:i')",
                    FieldDefinition::TYPE_JSON => "json_encode(\${$variable}->{$field->name})",
                    default => "\${$variable}->{$field->name}",
                };
            })
            ->implode(', ');
    }

    // ---------------------------------------------------------- Blade fragments

    /**
     * Column headings, as sort links.
     *
     * Every displayed column is sortable, including foreign keys — those sort
     * by the related record's label rather than by the id, so "Category"
     * orders the way the column reads.
     */
    private function buildIndexHeaders(Collection $fields): string
    {
        return $this->indexFields($fields)
            ->map(fn (FieldDefinition $field) => sprintf(
                '<x-isproject::sort-header column="%s" label="%s" />',
                $this->sortKey($field),
                e($field->label()),
            ))
            ->implode("\n");
    }

    /**
     * The map the controller hands to applySort().
     *
     * A whitelist, not a convenience: an order-by clause is concatenated into
     * SQL rather than bound, so the request may only pick from names the
     * application wrote down here.
     */
    private function buildSortable(Collection $fields, string $table): string
    {
        $entries = $this->indexFields($fields)->map(function (FieldDefinition $field) use ($table) {
            $key = $this->sortKey($field);

            if (! $field->isForeignKey()) {
                return "    '{$key}' => '{$table}.{$field->name}',";
            }

            $related = $field->foreignTable;
            $label = $this->relatedLabelColumn($related);

            // [join table, its key, our foreign key, the column to order by]
            return "    '{$key}' => ['{$related}', '{$related}.{$field->foreignColumn}', '{$table}.{$field->name}', '{$related}.{$label}'],";
        });

        return $this->indent($entries->implode("\n"), 8);
    }

    /** "category_id" sorts under "category", matching the heading. */
    private function sortKey(FieldDefinition $field): string
    {
        return $field->isForeignKey() ? (string) $field->relationName() : $field->name;
    }

    /**
     * The column on a related table worth ordering by — the one a person reads.
     * Falls back to the primary key when the table has nothing name-like.
     */
    private function relatedLabelColumn(string $table): string
    {
        foreach (['name', 'title', 'label', 'code', 'reference', 'email'] as $candidate) {
            if ($this->schema->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $this->schema->primaryKey($table);
    }

    private function buildIndexCells(Collection $fields, string $variable): string
    {
        return $this->indexFields($fields)
            ->map(fn (FieldDefinition $field) => '<td>'.$this->displayExpression($field, $variable).'</td>')
            ->implode("\n");
    }

    private function buildShowRows(Collection $fields, string $variable): string
    {
        return $fields
            ->map(fn (FieldDefinition $field) => strtr(<<<'BLADE'
                <div>
                    <dt>@LABEL@</dt>
                    <dd>@VALUE@</dd>
                </div>
                BLADE, [
                '@LABEL@' => e($field->label()),
                '@VALUE@' => $this->displayExpression($field, $variable),
            ]))
            ->implode("\n");
    }

    /** How one value is rendered read-only, in the table and on the show page. */
    private function displayExpression(FieldDefinition $field, string $variable): string
    {
        $accessor = "\${$variable}->{$field->name}";

        if ($field->isForeignKey()) {
            $label = $this->labelColumnFor($field->foreignTable, $field->foreignColumn);

            return '{{ $'.$variable.'->'.$field->relationName().'?->'.$label." ?? '—' }}";
        }

        return match ($field->logicalType) {
            FieldDefinition::TYPE_BOOLEAN => '<span class="badge badge-soft-{{ '.$accessor." ? 'success' : 'secondary' }}\">{{ ".$accessor." ? 'Yes' : 'No' }}</span>",
            FieldDefinition::TYPE_DATE => '{{ '.$accessor."?->format('d M Y') ?? '—' }}",
            FieldDefinition::TYPE_DATETIME => '{{ '.$accessor."?->format('d M Y, H:i') ?? '—' }}",
            FieldDefinition::TYPE_JSON => '<code>{{ json_encode('.$accessor.') }}</code>',
            FieldDefinition::TYPE_TEXT => '{{ '.$accessor.' ? Str::limit('.$accessor.", 80) : '—' }}",
            default => '{{ '.$accessor." ?? '—' }}",
        };
    }

    private function buildFormFields(Collection $fields, string $variable): string
    {
        return $this->editableFields($fields)
            ->map(fn (FieldDefinition $field) => $this->formField($field, $variable))
            ->implode("\n\n");
    }

    /**
     * Whether the form shows an asterisk against this field.
     *
     * A NOT NULL boolean is not one of them: it renders as a switch backed by a
     * hidden input, so a value is always posted and there is nothing for the
     * developer to remember to fill in.
     */
    private function marksRequired(FieldDefinition $field): bool
    {
        return ! $field->nullable && $field->input() !== 'checkbox';
    }

    /**
     * The "Required" section of the guidance panel — exactly the fields the form
     * asterisks, so the two never disagree. Returns an empty string when there
     * are none, so the heading never sits above an empty list.
     */
    private function buildGuideRequired(Collection $fields): string
    {
        $required = $this->editableFields($fields)
            ->filter(fn (FieldDefinition $field) => $this->marksRequired($field))
            ->map(fn (FieldDefinition $field) => '    <li>'.e($field->label()).'</li>');

        if ($required->isEmpty()) {
            return '';
        }

        return "\n<h4 class=\"is-guide-heading\">Required</h4>\n<ul class=\"is-guide-tags\">\n"
            .$required->implode("\n")
            ."\n</ul>";
    }

    /**
     * The "Things to watch" section: one entry per column that carries a
     * constraint worth explaining. Columns with nothing to say are skipped
     * rather than padded with filler.
     */
    private function buildGuideNotes(Collection $fields): string
    {
        $notes = $this->editableFields($fields)
            ->map(fn (FieldDefinition $field) => [$field->label(), $field->hints()])
            ->reject(fn (array $note) => $note[1] === [])
            ->map(fn (array $note) => '    <dt>'.e($note[0])."</dt>\n    <dd>".e(implode(' ', $note[1])).'</dd>');

        if ($notes->isEmpty()) {
            return '';
        }

        return "\n<h4 class=\"is-guide-heading\">Things to watch</h4>\n<dl class=\"is-guide-list\">\n"
            .$notes->implode("\n")
            ."\n</dl>";
    }

    /** One Bootstrap 5 form group, shaped by the column's inferred input type. */
    private function formField(FieldDefinition $field, string $variable): string
    {
        $name = $field->name;
        $label = e($field->label());
        $required = $field->nullable ? '' : ' required';

        // The guidance panel tells developers to look for the asterisk, so the
        // labels have to carry one. aria-hidden because the input's own
        // "required" attribute already announces this to a screen reader.
        $mark = $this->marksRequired($field)
            ? ' <span class="is-required-mark" aria-hidden="true">*</span>'
            : '';

        // A new record has no value yet, so fall back to the column's default —
        // that keeps NOT NULL columns with a default satisfiable in one click.
        $fallback = ! $field->nullable && $field->defaultLiteral() !== null
            ? ' ?? '.$field->defaultLiteral()
            : '';

        $old = "old('{$name}', \${$variable}->{$name}{$fallback})";

        if ($field->input() === 'select') {
            $options = $field->isForeignKey()
                ? strtr(<<<'BLADE'
                            @foreach ($@COLLECTION@ as $value => $optionLabel)
                                <option value="{{ $value }}" @selected(@OLD@ == $value)>{{ $optionLabel }}</option>
                            @endforeach
                    BLADE, [
                    '@COLLECTION@' => Str::camel(Str::plural((string) $field->relationName())),
                    '@OLD@' => $old,
                ])
                : collect($field->enumValues)
                    ->map(fn (string $value) => '        <option value="'.e($value).'" @selected('.$old." === '".$value."')>".e(Str::headline($value)).'</option>')
                    ->implode("\n");

            return strtr(<<<'BLADE'
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="@NAME@">@LABEL@@MARK@</label>
                    <select class="form-select @error('@NAME@') is-invalid @enderror" id="@NAME@" name="@NAME@"@REQUIRED@>
                        <option value="">— Select @LABEL@ —</option>
                @OPTIONS@
                    </select>
                    @error('@NAME@')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                BLADE, ['@NAME@' => $name, '@LABEL@' => $label, '@OPTIONS@' => $options, '@REQUIRED@' => $required, '@MARK@' => $mark]);
        }

        if ($field->input() === 'checkbox') {
            // The hidden input guarantees a value is posted when the box is unticked.
            return strtr(<<<'BLADE'
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block">@LABEL@</label>
                    <input type="hidden" name="@NAME@" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="@NAME@" name="@NAME@" value="1" @checked(@OLD@)>
                        <label class="form-check-label" for="@NAME@">Enabled</label>
                    </div>
                    @error('@NAME@')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                BLADE, ['@NAME@' => $name, '@LABEL@' => $label, '@OLD@' => $old]);
        }

        if ($field->input() === 'textarea') {
            return strtr(<<<'BLADE'
                <div class="col-12 mb-3">
                    <label class="form-label" for="@NAME@">@LABEL@@MARK@</label>
                    <textarea class="form-control @error('@NAME@') is-invalid @enderror" id="@NAME@" name="@NAME@" rows="4"@REQUIRED@>{{ @OLD@ }}</textarea>
                    @error('@NAME@')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                BLADE, ['@NAME@' => $name, '@LABEL@' => $label, '@OLD@' => $old, '@REQUIRED@' => $required, '@MARK@' => $mark]);
        }

        $extra = $field->logicalType === FieldDefinition::TYPE_DECIMAL ? ' step="0.01"' : '';

        if ($field->length && $field->logicalType === FieldDefinition::TYPE_STRING) {
            $extra .= ' maxlength="'.$field->length.'"';
        }

        // Date inputs need a formatted string, not the Carbon instance the cast returns.
        $value = match ($field->logicalType) {
            FieldDefinition::TYPE_DATE => "old('{$name}', \${$variable}->{$name}?->format('Y-m-d'))",
            FieldDefinition::TYPE_DATETIME => "old('{$name}', \${$variable}->{$name}?->format('Y-m-d\\TH:i'))",
            default => $old,
        };

        return strtr(<<<'BLADE'
            <div class="col-md-6 mb-3">
                <label class="form-label" for="@NAME@">@LABEL@@MARK@</label>
                <input type="@TYPE@" class="form-control @error('@NAME@') is-invalid @enderror" id="@NAME@" name="@NAME@" value="{{ @VALUE@ }}"@EXTRA@@REQUIRED@>
                @error('@NAME@')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            BLADE, [
            '@NAME@' => $name,
            '@LABEL@' => $label,
            '@TYPE@' => $field->input(),
            '@VALUE@' => $value,
            '@EXTRA@' => $extra,
            '@REQUIRED@' => $required,
            '@MARK@' => $mark,
        ]);
    }

    // ------------------------------------------------------------------ Writers

    private function writeModel(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.model')."/{$r['model']}.php"),
            $this->renderer->render('crud/model.stub', $r));
    }

    private function writeController(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.controller')."/{$r['model']}Controller.php"),
            $this->renderer->render('crud/controller.stub', $r));
    }

    private function writeRequests(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.request')."/Store{$r['model']}Request.php"),
            $this->renderer->render('crud/request.store.stub', $r));

        $this->put($files, base_path(config('isproject.paths.request')."/Update{$r['model']}Request.php"),
            $this->renderer->render('crud/request.update.stub', $r));
    }

    private function writeViews(Filesystem $files, array $r): void
    {
        $folder = base_path(config('isproject.paths.views').'/'.$r['viewFolder']);

        // "form" and "guide" are partials shared by create and edit, so they are
        // written with the leading underscore Blade convention uses for includes.
        $views = ['index', 'create', 'edit', 'show', 'form', 'guide'];

        // Only when the table can actually hold one of those states.
        if ($this->schema->hasArchive($r['table']) || $this->schema->hasSoftDeletes($r['table'])) {
            $views[] = 'archived';
        }

        foreach ($views as $view) {
            $name = in_array($view, ['form', 'guide'], true) ? "_{$view}" : $view;

            $this->put($files, "{$folder}/{$name}.blade.php",
                $this->renderer->render("crud/views/{$view}.blade.stub", $r));
        }
    }

    private function writeReport(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.views').'/'.$r['viewFolder'].'/report.blade.php'),
            $this->renderer->render('crud/views/report.blade.stub', $r));
    }

    private function writeFactory(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.factory')."/{$r['model']}Factory.php"),
            $this->renderer->render('crud/factory.stub', $r));
    }

    private function writePolicy(Filesystem $files, array $r): void
    {
        $this->put($files, base_path(config('isproject.paths.policy')."/{$r['model']}Policy.php"),
            $this->renderer->render('crud/policy.stub', $r));
    }

    /**
     * Append the module's routes, skipping any that are already registered.
     * routes/web.php is already inside the "web" group, so only the extra
     * middleware from config is applied here.
     */
    private function writeRoutes(Filesystem $files, array $r): void
    {
        $routesFile = config('isproject.routes_file');

        if (! $routesFile) {
            return;
        }

        $path = base_path($routesFile);

        if (! $files->exists($path)) {
            $this->written[] = ['<fg=yellow>missing</>', $routesFile];

            return;
        }

        $contents = $files->get($path);
        $middleware = (array) config('isproject.middleware', []);
        $suffix = $middleware === [] ? '' : "->middleware(['".implode("', '", $middleware)."'])";
        $controller = '\\'.$r['controllerNamespace'].'\\'.$r['model'].'Controller::class';

        $resourceNeedle = "Route::resource('{$r['routeUri']}'";
        $resourceLine = "Route::resource('{$r['routeUri']}', {$controller}){$suffix};";
        $reportLine = "Route::get('{$r['routeUri']}/report', [{$controller}, 'report'])"
            ."->name('{$r['routeName']}.report'){$suffix};";

        // Archive routes exist only when the table can hold those states; the
        // generator learns that from the presence of archived_at / deleted_at.
        $archiveLines = $this->archiveRouteLines($r, $controller, $suffix);

        $needsReport = in_array('report', $this->targets(), true)
            && ! Str::contains($contents, "'{$r['routeName']}.report'");
        $needsArchive = $archiveLines !== []
            && ! Str::contains($contents, "'{$r['routeName']}.archived'");
        $needsResource = ! Str::contains($contents, $resourceNeedle);

        if (! $needsReport && ! $needsArchive && ! $needsResource) {
            $this->written[] = ['<fg=gray>exists</>', $routesFile.' (routes already registered)'];

            return;
        }

        $above = array_values(array_filter(array_merge(
            [$needsReport ? $reportLine : null],
            $needsArchive ? $archiveLines : [],
        )));

        // These must sit ABOVE the resource: Laravel matches in registration
        // order, so products/{product} would otherwise capture "archived" as an
        // id and 404 on the model binding.
        if ($above !== [] && ! $needsResource) {
            $files->put($path, rtrim($this->insertBefore($contents, $resourceNeedle, implode("\n", $above)))."\n");
            $this->written[] = ['<fg=green>updated</>', $routesFile];

            return;
        }

        $lines = array_values(array_filter(array_merge(
            $above,
            [$needsResource ? $resourceLine : null],
        )));

        // Rewrite rather than append, so repeated generation leaves exactly one
        // blank line between routes however the file happened to end.
        $files->put($path, rtrim($contents)."\n\n".implode("\n", $lines)."\n");

        $this->written[] = ['<fg=green>updated</>', $routesFile];
    }

    /**
     * Route lines for the archive screen and its actions.
     *
     * Four separate routes rather than one, so RBAC — which derives permissions
     * from route names — can grant "may see the archive" without granting "may
     * destroy records permanently".
     *
     * @return array<int, string>
     */
    private function archiveRouteLines(array $r, string $controller, string $suffix): array
    {
        $archivable = $this->schema->hasArchive($r['table']);
        $softDeletes = $this->schema->hasSoftDeletes($r['table']);

        if (! $archivable && ! $softDeletes) {
            return [];
        }

        $lines = [
            "Route::get('{$r['routeUri']}/archived', [{$controller}, 'archived'])"
                ."->name('{$r['routeName']}.archived'){$suffix};",
            "Route::post('{$r['routeUri']}/{{$r['routeParameter']}}/restore', [{$controller}, 'restore'])"
                ."->name('{$r['routeName']}.restore'){$suffix};",
        ];

        if ($archivable) {
            array_splice($lines, 1, 0, [
                "Route::post('{$r['routeUri']}/{{$r['routeParameter']}}/archive', [{$controller}, 'archive'])"
                    ."->name('{$r['routeName']}.archive'){$suffix};",
            ]);
        }

        if ($softDeletes) {
            $lines[] = "Route::delete('{$r['routeUri']}/{{$r['routeParameter']}}/purge', [{$controller}, 'purge'])"
                ."->name('{$r['routeName']}.purge'){$suffix};";
        }

        return $lines;
    }

    /**
     * The generator deliberately does not edit config/isproject.php — a config
     * file is the developer's to own. Print the line to paste instead, unless the
     * module is already listed.
     */
    private function menuHint(array $r): void
    {
        $route = $r['routeName'].'.index';

        if (str_contains(json_encode(config('isproject.menu', [])) ?: '', $route)) {
            return;
        }

        $icon = $this->guessMenuIcon($r['model']);

        $this->components->info('Add it to the sidebar in config/isproject.php:');
        $this->line("    <fg=gray>['label' => '</><fg=cyan>{$r['title']}</><fg=gray>', 'icon' => '</><fg=cyan>{$icon}</><fg=gray>', 'route' => '</><fg=cyan>{$route}</><fg=gray>', 'can' => '</><fg=cyan>{$route}</><fg=gray>'],</>");
        $this->newLine();

        // The permissions only exist once the routes have been scanned.
        $this->components->info('Then make it assignable: php artisan isproject:permissions');
        $this->newLine();
    }

    /** A reasonable default icon, so the pasted line usually needs no editing. */
    private function guessMenuIcon(string $model): string
    {
        return match (true) {
            // Model names that describe people, so the menu gets the people
            // icon rather than a generic one. Nothing to do with who uses this
            // framework — these are table names an application might have.
            (bool) preg_match('/user|member|student|staff|people|person|employee|customer/i', $model) => 'people',
            (bool) preg_match('/task|todo|job/i', $model) => 'check-circle',
            (bool) preg_match('/setting|config|option/i', $model) => 'settings',
            (bool) preg_match('/categor|type|group|tag/i', $model) => 'list',
            default => 'box',
        };
    }

    /**
     * Splice a line in immediately above the first line containing $needle.
     * Line surgery rather than a regex: the lines being inserted are full of
     * backslashes and quotes that would need escaping in a replacement string.
     */
    private function insertBefore(string $contents, string $needle, string $line): string
    {
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $existing) {
            if (str_contains($existing, $needle)) {
                array_splice($lines, $index, 0, [$line]);

                return implode("\n", $lines);
            }
        }

        return rtrim($contents)."\n\n".$line;
    }

    // ------------------------------------------------------------------ Helpers

    private function put(Filesystem $files, string $path, string $contents): void
    {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

        if ($files->exists($path) && ! $this->option('force')) {
            $this->written[] = ['<fg=yellow>skipped</>', $relative.' (exists — pass --force)'];

            return;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);

        $this->written[] = ['<fg=green>created</>', $relative];
    }

    /**
     * The most human-readable column of a related table, used as the label in
     * select boxes and index tables. Falls back to the referenced key.
     */
    private function labelColumnFor(string $table, string $fallback): string
    {
        foreach (['name', 'title', 'label', 'description'] as $candidate) {
            if ($this->schema->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        $first = $this->schema->fields($table)
            ->first(fn (FieldDefinition $field) => $field->logicalType === FieldDefinition::TYPE_STRING);

        return $first?->name ?? $fallback;
    }

    /**
     * Columns a form can round-trip. JSON and binary columns are read-only:
     * they still appear on the detail screen, but not in forms or rules.
     *
     * @return Collection<int, FieldDefinition>
     */
    private function editableFields(Collection $fields): Collection
    {
        return $fields->filter(fn (FieldDefinition $field) => $field->isFormEditable())->values();
    }

    /**
     * Columns worth showing on the list screen, capped so wide tables stay
     * readable. The rest are still visible on the show and edit screens.
     *
     * @return Collection<int, FieldDefinition>
     */
    private function indexFields(Collection $fields): Collection
    {
        return $fields
            ->filter(fn (FieldDefinition $field) => $field->showOnIndex())
            ->take((int) config('isproject.max_index_columns', 6))
            ->values();
    }

    /** Shift a generated block to the column its placeholder occupies. */
    private function indent(string $block, int $spaces): string
    {
        if (trim($block) === '') {
            return '';
        }

        $pad = str_repeat(' ', $spaces);

        return collect(explode("\n", $block))
            ->map(fn (string $line) => trim($line) === '' ? '' : $pad.$line)
            ->implode("\n");
    }
}
