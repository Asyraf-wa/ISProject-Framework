<?php

namespace IsProject\Framework\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use IsProject\Framework\Models\Audit;
use IsProject\Framework\Support\AuditRecorder;

/**
 * Puts a record away without deleting it.
 *
 *     class Book extends Model
 *     {
 *         use Archivable;
 *     }
 *
 * Needs one nullable timestamp column; `php artisan isproject:archivable books`
 * writes the migration for it.
 *
 * Archiving is a *business* state — the semester finished, the book left the
 * catalogue — and is deliberately not the same thing as deleting. A model can
 * use this and SoftDeletes together; a deleted record is out of every list,
 * including the archive.
 */
trait Archivable
{
    public static function bootArchivable(): void
    {
        // Archived rows are out of every ordinary query. They are one click
        // away on the archive screen, and withArchived() brings them back into
        // any query that wants them.
        static::addGlobalScope(new ArchivedScope);
    }

    public function initializeArchivable(): void
    {
        $this->casts[$this->getArchivedAtColumn()] = 'datetime';
    }

    public function getArchivedAtColumn(): string
    {
        return defined(static::class.'::ARCHIVED_AT') ? static::ARCHIVED_AT : 'archived_at';
    }

    public function isArchived(): bool
    {
        return $this->{$this->getArchivedAtColumn()} !== null;
    }

    /** Put this record away. Returns false if it was already archived. */
    public function archive(): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        $this->forceFill([$this->getArchivedAtColumn() => $this->freshTimestamp()])->save();

        // Recorded explicitly rather than left to the "updated" event: the
        // column is in audit.ignored_attributes, so that update logs nothing,
        // and "archived" says what happened far better than a timestamp diff.
        $this->recordAudit(Audit::ARCHIVED);

        return true;
    }

    /** Bring it back. Returns false if it was not archived. */
    public function unarchive(): bool
    {
        if (! $this->isArchived()) {
            return false;
        }

        $this->forceFill([$this->getArchivedAtColumn() => null])->save();

        $this->recordAudit(Audit::UNARCHIVED);

        return true;
    }

    /** @param  Builder<static>  $query */
    public function scopeWithArchived(Builder $query): void
    {
        $query->withoutGlobalScope(ArchivedScope::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeOnlyArchived(Builder $query): void
    {
        $query->withoutGlobalScope(ArchivedScope::class)
            ->whereNotNull($this->getArchivedAtColumn());
    }

    /**
     * Route model binding has to see archived records.
     *
     * Without this, every link out of the archive screen is a 404: the global
     * scope hides the row, so Laravel cannot resolve /books/12 for a book that
     * has been archived — including the Restore button meant to fix it.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withArchived()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null): ?Model
    {
        return $this->withArchived()
            ->withTrashed()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    private function recordAudit(string $event): void
    {
        if (in_array(Auditable::class, class_uses_recursive(static::class), true)) {
            app(AuditRecorder::class)->record($event, $this);
        }
    }
}

/**
 * Hides archived rows from ordinary queries.
 *
 * A named class rather than a closure so it can be removed by name —
 * withoutGlobalScope(ArchivedScope::class) — which is what withArchived() and
 * onlyArchived() rely on.
 */
class ArchivedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn($model->getArchivedAtColumn()));
    }
}
