<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A recorded change. Written by AuditRecorder; never edited afterwards — an
 * audit row that can be changed is not evidence of anything.
 */
class Audit extends Model
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const RESTORED = 'restored';

    public const ARCHIVED = 'archived';

    public const UNARCHIVED = 'unarchived';

    protected $table = 'isproject_audits';

    /** Only created_at: a row is written once and never touched again. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'event', 'auditable_type', 'auditable_id', 'auditable_label',
        'user_id', 'user_label', 'old_values', 'new_values',
        'url', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /** "App\Models\Product" reads as "Product" in a listing. */
    public function subjectName(): string
    {
        return Str::headline(class_basename($this->auditable_type));
    }

    /**
     * Attribute names touched by this change, for the summary column.
     *
     * @return array<int, string>
     */
    public function changedKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->old_values ?? []),
            array_keys($this->new_values ?? []),
        )));
    }

    /**
     * Both sides of every attribute in this change, ready to render as a diff.
     *
     * @return array<int, array{key: string, label: string, old: mixed, new: mixed}>
     */
    public function differences(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_map(fn (string $key) => [
            'key' => $key,
            'label' => Str::headline(Str::endsWith($key, '_id') ? Str::beforeLast($key, '_id') : $key),
            'old' => $old[$key] ?? null,
            'new' => $new[$key] ?? null,
        ], $this->changedKeys());
    }

    /** @param  Builder<$this>  $query */
    public function scopeForSubject(Builder $query, Model $model): void
    {
        $query->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', (string) $model->getKey());
    }
}
