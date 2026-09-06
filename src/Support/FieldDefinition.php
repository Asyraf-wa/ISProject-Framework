<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Str;

/**
 * A single database column, enriched with everything the generators need to
 * decide how it should be cast, validated, and rendered.
 *
 * Instances are built by SchemaInspector from the driver-agnostic metadata
 * returned by Schema::getColumns(), so the same column definition drives the
 * model, the form requests, and the Blade views.
 */
class FieldDefinition
{
    /** Logical types the generators reason about, mapped from driver types. */
    public const TYPE_INTEGER = 'integer';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_DATE = 'date';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_TIME = 'time';

    public const TYPE_JSON = 'json';

    public const TYPE_ENUM = 'enum';

    public const TYPE_UUID = 'uuid';

    public const TYPE_BINARY = 'binary';

    public function __construct(
        public readonly string $name,
        public readonly string $logicalType,
        public readonly string $rawType,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly bool $autoIncrement = false,
        public readonly ?string $comment = null,
        public readonly ?int $length = null,
        public readonly bool $unique = false,
        public readonly ?string $foreignTable = null,
        public readonly ?string $foreignColumn = null,
        /** @var array<int, string> */
        public readonly array $enumValues = [],
    ) {}

    /**
     * Human label used in table headers, form labels and validation messages.
     * "category_id" reads better as "Category" than "Category Id".
     */
    public function label(): string
    {
        return Str::headline($this->isForeignKey() ? Str::beforeLast($this->name, '_id') : $this->name);
    }

    public function isForeignKey(): bool
    {
        return $this->foreignTable !== null;
    }

    /** Eloquent relation method name for a foreign key, e.g. category_id -> category. */
    public function relationName(): ?string
    {
        return $this->isForeignKey()
            ? Str::camel(Str::beforeLast($this->name, '_id'))
            : null;
    }

    /** Related model class name, e.g. category_id -> Category. */
    public function relatedModel(): ?string
    {
        return $this->isForeignKey()
            ? Str::studly(Str::singular($this->foreignTable))
            : null;
    }

    /** Value for the model's $casts array, or null when no cast is needed. */
    public function cast(): ?string
    {
        return match ($this->logicalType) {
            self::TYPE_INTEGER => 'integer',
            self::TYPE_DECIMAL => 'decimal:2',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_DATE => 'date',
            self::TYPE_DATETIME => 'datetime',
            self::TYPE_JSON => 'array',
            default => null,
        };
    }

    /** Which Blade partial the form builder should render for this column. */
    public function input(): string
    {
        return match (true) {
            $this->isForeignKey() => 'select',
            $this->logicalType === self::TYPE_ENUM => 'select',
            $this->logicalType === self::TYPE_BOOLEAN => 'checkbox',
            $this->logicalType === self::TYPE_TEXT => 'textarea',
            $this->logicalType === self::TYPE_JSON => 'textarea',
            $this->logicalType === self::TYPE_DATE => 'date',
            $this->logicalType === self::TYPE_DATETIME => 'datetime-local',
            $this->logicalType === self::TYPE_TIME => 'time',
            $this->logicalType === self::TYPE_INTEGER => 'number',
            $this->logicalType === self::TYPE_DECIMAL => 'number',
            Str::contains($this->name, 'email') => 'email',
            Str::contains($this->name, ['url', 'website', 'link']) => 'url',
            default => 'text',
        };
    }

    /**
     * Whether this column belongs on a generated form. JSON and binary columns
     * cannot round-trip through a plain input, so they are shown read-only on
     * the detail screen and left out of the form and its validation rules.
     */
    public function isFormEditable(): bool
    {
        return ! in_array($this->logicalType, [self::TYPE_JSON, self::TYPE_BINARY], true);
    }

    /**
     * Plain-English constraints for the guidance panel beside the form — the
     * same limits the validation rules enforce, said in words a student can act
     * on before submitting.
     *
     * Returns null when the column carries no constraint worth explaining; a
     * text field with no length limit needs no instructions.
     *
     * @return array<int, string>
     */
    public function hints(): array
    {
        // A column comment is the lecturer's own words. Nothing generated
        // beats that, so it stands alone.
        if ($this->comment !== null && trim($this->comment) !== '') {
            return [trim($this->comment)];
        }

        $hints = [];

        if ($this->isForeignKey()) {
            $hints[] = 'Pick an existing '.Str::lower($this->label()).'; the list only offers records that exist.';
        } elseif ($this->logicalType === self::TYPE_ENUM && $this->enumValues !== []) {
            $hints[] = 'One of: '.collect($this->enumValues)->map(fn (string $v) => Str::headline($v))->implode(', ').'.';
        } elseif ($this->logicalType === self::TYPE_DECIMAL) {
            $hints[] = 'A number, two decimal places, e.g. 19.90.';
        } elseif ($this->logicalType === self::TYPE_INTEGER) {
            $hints[] = 'Whole numbers only — no decimal point.';
        } elseif ($this->logicalType === self::TYPE_STRING && $this->length !== null) {
            $hints[] = "At most {$this->length} characters.";
        }

        if ($this->unique) {
            $hints[] = 'Must not already be used by another record.';
        }

        if ($this->input() === 'email') {
            $hints[] = 'Must look like an email address.';
        }

        return $hints;
    }

    /**
     * The column's default rendered as a PHP literal, so a new record's form
     * starts with the value the database would have used. Returns null for SQL
     * expressions such as CURRENT_TIMESTAMP, which cannot be inlined.
     */
    public function defaultLiteral(): ?string
    {
        if ($this->default === null) {
            return null;
        }

        // Postgres appends a type cast: 'draft'::character varying
        $value = trim(preg_replace('/::[a-z ]+$/i', '', trim((string) $this->default)));
        $unquoted = trim($value, "'\"");

        $isExpression = $value === $unquoted
            && (str_contains($value, '(') || preg_match('/^[A-Z_]+$/', $value) === 1);

        if ($isExpression) {
            return null;
        }

        return match ($this->logicalType) {
            self::TYPE_BOOLEAN => in_array(strtolower($unquoted), ['1', 'true', 't'], true) ? '1' : '0',
            self::TYPE_INTEGER, self::TYPE_DECIMAL => is_numeric($unquoted) ? $unquoted : null,
            self::TYPE_DATE, self::TYPE_DATETIME, self::TYPE_TIME => null,
            default => "'".addslashes($unquoted)."'",
        };
    }

    /** Keyword search only makes sense against text-ish columns. */
    public function isSearchable(): bool
    {
        return in_array($this->logicalType, (array) config('isproject.searchable_types', ['string', 'text']), true)
            && ! $this->isForeignKey();
    }

    /** Long text and blobs make index tables unreadable; keep them off the list screen. */
    public function showOnIndex(): bool
    {
        return ! in_array($this->logicalType, [self::TYPE_TEXT, self::TYPE_JSON, self::TYPE_BINARY], true);
    }

    /**
     * Validation rules as a list of PHP expressions, ready to be joined into a
     * generated rules() array. $ignoreId produces the update variant of a
     * unique rule so a record does not collide with itself.
     */
    public function rules(?string $ignoreExpression = null): array
    {
        // NOT NULL means required even when the column has a default: the
        // generated form pre-fills that default, so the value is always posted.
        $rules = [$this->nullable ? "'nullable'" : "'required'"];

        $rules[] = match ($this->logicalType) {
            self::TYPE_INTEGER => "'integer'",
            self::TYPE_DECIMAL => "'numeric'",
            self::TYPE_BOOLEAN => "'boolean'",
            self::TYPE_DATE, self::TYPE_DATETIME => "'date'",
            self::TYPE_JSON => "'array'",
            self::TYPE_UUID => "'uuid'",
            default => "'string'",
        };

        if ($this->input() === 'email') {
            $rules[] = "'email:rfc'";
        }

        if ($this->input() === 'url') {
            $rules[] = "'url'";
        }

        if ($this->length && in_array($this->logicalType, [self::TYPE_STRING, self::TYPE_UUID], true)) {
            $rules[] = "'max:{$this->length}'";
        }

        if ($this->enumValues !== []) {
            $rules[] = "'in:".implode(',', $this->enumValues)."'";
        }

        if ($this->isForeignKey()) {
            $rules[] = "'exists:{$this->foreignTable},{$this->foreignColumn}'";
        }

        if ($this->unique) {
            $rules[] = $ignoreExpression
                ? "Rule::unique('%%table%%', '{$this->name}')->ignore({$ignoreExpression})"
                : "'unique:%%table%%,{$this->name}'";
        }

        return $rules;
    }

    /** Faker expression used in the generated factory. */
    public function fake(): string
    {
        if ($this->isForeignKey()) {
            return '\\App\\Models\\'.$this->relatedModel().'::factory()';
        }

        if ($this->enumValues !== []) {
            return 'fake()->randomElement('.json_encode($this->enumValues).')';
        }

        return match (true) {
            $this->input() === 'email' => 'fake()->safeEmail()',
            $this->input() === 'url' => 'fake()->url()',
            Str::contains($this->name, 'name') => 'fake()->name()',
            Str::contains($this->name, 'phone') => 'fake()->phoneNumber()',
            Str::contains($this->name, ['address', 'street']) => 'fake()->address()',
            Str::contains($this->name, ['price', 'amount', 'total']) => 'fake()->randomFloat(2, 1, 1000)',
            $this->logicalType === self::TYPE_BOOLEAN => 'fake()->boolean()',
            $this->logicalType === self::TYPE_INTEGER => 'fake()->numberBetween(1, 100)',
            $this->logicalType === self::TYPE_DECIMAL => 'fake()->randomFloat(2, 1, 1000)',
            $this->logicalType === self::TYPE_TEXT => 'fake()->paragraph()',
            $this->logicalType === self::TYPE_DATE => 'fake()->date()',
            $this->logicalType === self::TYPE_DATETIME => 'fake()->dateTime()',
            $this->logicalType === self::TYPE_JSON => '[]',
            default => 'fake()->words(3, true)',
        };
    }
}
