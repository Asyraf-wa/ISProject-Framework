<?php

namespace IsProject\Framework\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reads and writes site configuration held in the isproject_settings table.
 *
 * Every page in the shell asks for the site name and favicon, so the whole
 * table is loaded once and cached; writing flushes it. Reads never throw: the
 * layout renders before the first migration has run, during `migrate:fresh`,
 * and on a machine whose cache table is missing, and none of those should be a
 * 500 on the student's screen. When storage is unreachable the configured
 * defaults are used, which is exactly what a fresh install should show.
 */
class Settings
{
    public const TABLE = 'isproject_settings';

    public const CACHE_KEY = 'isproject.settings';

    /**
     * Stored rows for this request. Only successful reads are kept: a failed
     * one must not pin an empty set for the rest of the request, or a lookup
     * made before the table exists would suppress every later read.
     */
    private ?array $rows = null;

    public function __construct(private readonly SettingsSchema $schema) {}

    /**
     * Every setting, cast, with defaults filled in for anything unsaved.
     *
     * Defaults are recomputed on each call rather than memoised with the rows.
     * They can depend on config ("@config:app.name"), and this object is a
     * singleton that the service provider touches during boot — caching them
     * would freeze whatever config happened to hold at that moment.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = $this->schema->defaults();

        foreach ($this->stored() as $key => $raw) {
            // A stored empty string means "cleared", and should not silently
            // reinstate the default — but a missing row should.
            $values[$key] = $this->schema->cast($key, $raw);
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        // Treat blank as absent so a cleared text field falls back rather than
        // rendering an empty site name in the browser tab.
        return $value === null || $value === '' ? $default : $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Write values and flush the cache.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            DB::table(self::TABLE)->updateOrInsert(
                ['key' => $key],
                ['value' => $this->serialise($value), 'updated_at' => $now],
            );
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        DB::table(self::TABLE)->where('key', $key)->delete();

        $this->flush();
    }

    /** Drop both the request-local copy and the shared cache entry. */
    public function flush(): void
    {
        $this->rows = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // A missing cache store is not a reason to fail a save.
        }
    }

    /**
     * Raw rows, cached forever. Any storage failure yields an empty set so the
     * caller falls back to defaults.
     *
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            return $this->rows = Cache::rememberForever(self::CACHE_KEY, fn () => $this->fetch());
        } catch (Throwable) {
            // The cache store itself may be unavailable (a database cache
            // driver before migrations run). Go straight to the table.
            try {
                return $this->rows = $this->fetch();
            } catch (Throwable) {
                // Deliberately not memoised: the table may simply not exist
                // yet, and a later read in the same request should try again.
                return [];
            }
        }
    }

    /** @return array<string, string|null> */
    private function fetch(): array
    {
        return DB::table(self::TABLE)->pluck('value', 'key')->all();
    }

    private function serialise(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
