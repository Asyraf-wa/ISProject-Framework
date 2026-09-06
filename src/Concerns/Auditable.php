<?php

namespace IsProject\Framework\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use IsProject\Framework\Models\Audit;
use IsProject\Framework\Support\AuditRecorder;

/**
 * Records every change to this model.
 *
 *     class Product extends Model
 *     {
 *         use Auditable;
 *     }
 *
 * Generated models get this already — see config('isproject.audit').
 *
 * It listens to Eloquent's model events, which means it sees what goes through
 * Eloquent and nothing else. A mass update written as
 * Product::query()->update([...]) fires no model events and is not recorded;
 * neither is raw SQL. That is a property of Eloquent, not a bug here, and it is
 * why the audit trail is a record of what the application did rather than a
 * guarantee about what the database contains.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        $recorder = fn () => app(AuditRecorder::class);

        static::created(fn ($model) => $recorder()->record(Audit::CREATED, $model));
        static::updated(fn ($model) => $recorder()->record(Audit::UPDATED, $model));
        static::deleted(fn ($model) => $recorder()->record(Audit::DELETED, $model));

        // restored() only exists on models that soft delete; registering it
        // unconditionally would throw on the ones that do not.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn ($model) => $recorder()->record(Audit::RESTORED, $model));
        }
    }

    /** @return MorphMany<Audit, $this> */
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable')->latest();
    }

    /**
     * This record's history, newest first.
     *
     * @return Builder<Audit>
     */
    public function auditTrail(): Builder
    {
        return Audit::query()->forSubject($this)->latest();
    }
}
