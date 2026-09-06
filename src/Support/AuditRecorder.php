<?php

namespace IsProject\Framework\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use IsProject\Framework\Models\Audit;
use Throwable;

/**
 * Turns a model event into an audit row.
 *
 * Kept out of the trait so the decisions that matter — what counts as a change,
 * what must never be written down, who did it — live in one testable place
 * rather than in a trait mixed into other people's models.
 */
class AuditRecorder
{
    /** Suspends recording for the duration of a callback. */
    private bool $enabled = true;

    /**
     * Run something without auditing it.
     *
     *     app(AuditRecorder::class)->withoutAuditing(fn () => $seeder->run());
     *
     * Seeding, imports and back-fills would otherwise write thousands of rows
     * describing changes nobody made.
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $previous = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $previous;
        }
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function enabled(): bool
    {
        return $this->enabled && (bool) config('isproject.audit.enabled', true);
    }

    /**
     * Record one event, unless there is nothing worth recording.
     *
     * Never throws: an audit trail is a record of the application's work, not a
     * part of it. A failure to write the log must not roll back the save the
     * user actually asked for — but it must not pass silently either, so it is
     * reported to the application's own error handler.
     */
    public function record(string $event, Model $model): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            [$old, $new] = $this->values($event, $model);

            // An "update" that changed nothing but a timestamp is noise.
            if ($event === Audit::UPDATED && $old === [] && $new === []) {
                return;
            }

            Audit::query()->create([
                'event' => $event,
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => (string) $model->getKey(),
                'auditable_label' => $this->label($model),
                'user_id' => Auth::id(),
                'user_label' => $this->actor(),
                'old_values' => $old,
                'new_values' => $new,
                'url' => $this->url(),
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 480, ''),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The two sides of the change, filtered and redacted.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function values(string $event, Model $model): array
    {
        return match ($event) {
            Audit::CREATED => [[], $this->clean($model, $model->getAttributes())],
            Audit::RESTORED => [[], $this->clean($model, $model->getAttributes())],
            Audit::DELETED => [$this->clean($model, $model->getAttributes()), []],
            default => $this->changed($model),
        };
    }

    /**
     * For an update, only the attributes that actually moved — and both of
     * their values, because "price changed" is far less use than "19.90 → 24.00".
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function changed(Model $model): array
    {
        $dirty = $this->clean($model, $model->getChanges());
        $original = [];

        foreach (array_keys($dirty) as $key) {
            $original[$key] = $model->getOriginal($key);
        }

        return [$this->clean($model, $original), $dirty];
    }

    /**
     * Drop the attributes that must not be written down, and the ones that are
     * only noise.
     *
     * Redaction is the important half. A User model with this trait would
     * otherwise copy its password hash and remember token into a table built
     * for people to browse.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function clean(Model $model, array $attributes): array
    {
        $ignored = array_merge(
            (array) config('isproject.audit.ignored_attributes', []),
            method_exists($model, 'auditIgnores') ? $model->auditIgnores() : [],
            property_exists($model, 'auditExclude') ? (array) $model->auditExclude : [],
        );

        $redacted = (array) config('isproject.audit.redacted_attributes', []);
        $clean = [];

        foreach ($attributes as $key => $value) {
            if ($this->matches($key, $ignored)) {
                continue;
            }

            // Kept as a key with a marker rather than dropped: "the password
            // was changed" is exactly the sort of thing an audit trail is for.
            $clean[$key] = $this->matches($key, $redacted) ? '••••••••' : $this->scalar($value);
        }

        return $clean;
    }

    /** @param  array<int, string>  $patterns */
    private function matches(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /** Keep the JSON column readable: no objects, no unbounded blobs. */
    private function scalar(mixed $value): mixed
    {
        $value = match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_array($value) || is_object($value) => json_encode($value),
            default => $value,
        };

        return is_string($value) ? Str::limit($value, 500) : $value;
    }

    /**
     * A short description of the record, so a listing can name what changed
     * without loading — or failing to load — a row that has since been deleted.
     */
    private function label(Model $model): ?string
    {
        if (method_exists($model, 'auditLabel')) {
            return Str::limit((string) $model->auditLabel(), 190, '');
        }

        foreach ((array) config('isproject.audit.label_attributes', []) as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 190, '');
            }
        }

        return null;
    }

    /** Who did it, as they were named at the time. */
    private function actor(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            // No session: a console command, a queued job, a seeder.
            return app()->runningInConsole() ? 'Console' : null;
        }

        return Str::limit((string) ($user->name ?? $user->email ?? "User #{$user->getAuthIdentifier()}"), 190, '');
    }

    private function url(): ?string
    {
        if (app()->runningInConsole()) {
            return 'artisan '.implode(' ', array_slice((array) ($_SERVER['argv'] ?? []), 1));
        }

        return Str::limit((string) request()?->fullUrl(), 980, '');
    }
}
