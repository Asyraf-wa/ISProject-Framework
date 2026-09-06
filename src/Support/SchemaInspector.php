<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reads an existing table and turns it into FieldDefinition objects.
 *
 * The database is the single source of truth, so the workflow stays
 * "write the migration, migrate, then generate".
 *
 * Schema::getColumns() / getIndexes() / getForeignKeys() are native since
 * Laravel 11 — no Doctrine DBAL dependency required.
 */
class SchemaInspector
{
    public function __construct(private readonly ?string $connection = null) {}

    private function schema()
    {
        return Schema::connection($this->connection);
    }

    public function hasTable(string $table): bool
    {
        return $this->schema()->hasTable($table);
    }

    /**
     * Every table in the connection, minus the framework plumbing listed in
     * config('isproject.ignored_tables').
     *
     * @return array<int, string>
     */
    public function tables(): array
    {
        $ignored = (array) config('isproject.ignored_tables', []);

        return collect($this->schema()->getTableListing())
            // Postgres may hand back schema-qualified names such as public.users.
            ->map(fn (string $table) => Str::afterLast($table, '.'))
            // Matched with Str::is(), so one "isproject_*" entry covers every
            // table this package owns, including ones it has not added yet.
            ->reject(fn (string $table) => Str::is($ignored, $table))
            ->values()
            ->all();
    }

    /**
     * Columns of $table as FieldDefinition objects.
     *
     * @param  bool  $includeIgnored  Keep id/timestamps/password — used when the
     *                                caller needs the full picture rather than the editable subset.
     * @return Collection<int, FieldDefinition>
     */
    public function fields(string $table, bool $includeIgnored = false): Collection
    {
        $ignored = $includeIgnored ? [] : (array) config('isproject.ignored_columns', []);
        $uniques = $this->uniqueColumns($table);
        $foreignKeys = $this->foreignKeyMap($table);

        return collect($this->schema()->getColumns($table))
            ->reject(fn (array $column) => in_array($column['name'], $ignored, true))
            ->reject(fn (array $column) => ($column['auto_increment'] ?? false) && ! $includeIgnored)
            ->map(function (array $column) use ($uniques, $foreignKeys) {
                $rawType = (string) ($column['type'] ?? $column['type_name']);
                $foreign = $foreignKeys[$column['name']] ?? null;

                return new FieldDefinition(
                    name: $column['name'],
                    logicalType: $this->logicalType((string) $column['type_name'], $rawType),
                    rawType: $rawType,
                    nullable: (bool) ($column['nullable'] ?? false),
                    default: $column['default'] ?? null,
                    autoIncrement: (bool) ($column['auto_increment'] ?? false),
                    comment: $column['comment'] ?? null,
                    length: $this->length($rawType),
                    unique: in_array($column['name'], $uniques, true),
                    foreignTable: $foreign['table'] ?? null,
                    foreignColumn: $foreign['column'] ?? null,
                    enumValues: $this->enumValues($rawType),
                );
            })
            ->values();
    }

    /** Does the table carry a deleted_at column? Drives the SoftDeletes trait. */
    public function hasSoftDeletes(string $table): bool
    {
        return $this->hasColumn($table, 'deleted_at');
    }

    /**
     * Does the table carry an archived_at column? Drives the Archivable trait
     * and the archive screen, the same way deleted_at drives SoftDeletes.
     */
    public function hasArchive(string $table): bool
    {
        return $this->hasColumn($table, 'archived_at');
    }

    public function hasTimestamps(string $table): bool
    {
        return $this->hasColumn($table, 'created_at');
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->schema()->hasColumn($table, $column);
    }

    /** Primary key column name, defaulting to id when the table has no declared PK. */
    public function primaryKey(string $table): string
    {
        foreach ($this->schema()->getIndexes($table) as $index) {
            if ($index['primary'] ?? false) {
                return $index['columns'][0] ?? 'id';
            }
        }

        return 'id';
    }

    /**
     * Columns covered by a single-column unique index. Composite uniques are
     * skipped deliberately — they need a rule the generator cannot infer.
     *
     * @return array<int, string>
     */
    private function uniqueColumns(string $table): array
    {
        return collect($this->schema()->getIndexes($table))
            ->filter(fn (array $index) => ($index['unique'] ?? false) && ! ($index['primary'] ?? false))
            ->filter(fn (array $index) => count($index['columns'] ?? []) === 1)
            ->map(fn (array $index) => $index['columns'][0])
            ->values()
            ->all();
    }

    /**
     * Map of local column => referenced table/column, for single-column keys.
     *
     * @return array<string, array{table: string, column: string}>
     */
    private function foreignKeyMap(string $table): array
    {
        $map = [];

        foreach ($this->schema()->getForeignKeys($table) as $key) {
            if (count($key['columns'] ?? []) !== 1) {
                continue;
            }

            $map[$key['columns'][0]] = [
                'table' => Str::afterLast((string) $key['foreign_table'], '.'),
                'column' => $key['foreign_columns'][0] ?? 'id',
            ];
        }

        return $map;
    }

    /**
     * Collapse driver-specific type names onto the logical types the
     * generators understand. Covers MySQL/MariaDB, PostgreSQL and SQLite.
     */
    private function logicalType(string $typeName, string $rawType): string
    {
        $typeName = strtolower($typeName);

        // MySQL has no native boolean: tinyint(1) is the convention.
        if ($typeName === 'tinyint' && Str::contains($rawType, '(1)')) {
            return FieldDefinition::TYPE_BOOLEAN;
        }

        return match (true) {
            in_array($typeName, ['bool', 'boolean'], true) => FieldDefinition::TYPE_BOOLEAN,
            in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int2', 'int4', 'int8', 'serial', 'bigserial'], true) => FieldDefinition::TYPE_INTEGER,
            in_array($typeName, ['decimal', 'numeric', 'float', 'double', 'real', 'money'], true) => FieldDefinition::TYPE_DECIMAL,
            in_array($typeName, ['date'], true) => FieldDefinition::TYPE_DATE,
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz'], true) => FieldDefinition::TYPE_DATETIME,
            in_array($typeName, ['time', 'timetz'], true) => FieldDefinition::TYPE_TIME,
            in_array($typeName, ['json', 'jsonb'], true) => FieldDefinition::TYPE_JSON,
            in_array($typeName, ['enum', 'set'], true) => FieldDefinition::TYPE_ENUM,
            in_array($typeName, ['uuid'], true) => FieldDefinition::TYPE_UUID,
            in_array($typeName, ['blob', 'bytea', 'binary', 'varbinary'], true) => FieldDefinition::TYPE_BINARY,
            in_array($typeName, ['text', 'mediumtext', 'longtext', 'tinytext'], true) => FieldDefinition::TYPE_TEXT,
            default => FieldDefinition::TYPE_STRING,
        };
    }

    /** Extract 255 from varchar(255); null when the type carries no length. */
    private function length(string $rawType): ?int
    {
        return preg_match('/\((\d+)(?:,\s*\d+)?\)/', $rawType, $matches)
            ? (int) $matches[1]
            : null;
    }

    /**
     * Pull the allowed values out of enum('draft','published').
     *
     * @return array<int, string>
     */
    private function enumValues(string $rawType): array
    {
        if (! preg_match('/^(?:enum|set)\((.*)\)$/i', $rawType, $matches)) {
            return [];
        }

        return collect(explode(',', $matches[1]))
            ->map(fn (string $value) => trim(trim($value), "'\""))
            ->filter()
            ->values()
            ->all();
    }
}
