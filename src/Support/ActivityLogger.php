<?php

namespace IsProject\Framework\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use IsProject\Framework\Models\Activity;
use Throwable;

/**
 * Writes the activity log.
 *
 * Two rules borrowed from the audit recorder, for the same reasons.
 *
 * It never throws. A log that can take down the thing it is watching is worse
 * than no log — a missing table or a full disk must not turn somebody's sign-in
 * into a 500. Failures are reported and swallowed.
 *
 * It never stores a credential. The only sensitive value that comes near this
 * class is the password on a failed sign-in, and it is dropped rather than
 * redacted: unlike a changed field in an audit row, there is no version of it
 * worth keeping.
 */
class ActivityLogger
{
    /** Suspends logging, for seeders and imports. */
    private bool $suspended = false;

    public function enabled(): bool
    {
        return (bool) config('isproject.activity.enabled', true);
    }

    /**
     * Record something somebody did.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, string $description, array $properties = [], ?Model $subject = null): ?Activity
    {
        if ($this->suspended || ! $this->enabled() || $this->ignored($event)) {
            return null;
        }

        try {
            return Activity::query()->create([
                'event' => Str::limit($event, 40, ''),
                'description' => Str::limit($description, 255, ''),
                'user_id' => $this->userId(),
                'user_label' => $this->userLabel(),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $this->clean($properties) ?: null,
                'url' => Str::limit($this->url(), 1000, ''),
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Log against a specific person rather than whoever is signed in. */
    public function logFor(mixed $user, string $event, string $description, array $properties = []): ?Activity
    {
        $activity = $this->log($event, $description, $properties);

        if ($activity && $user) {
            $activity->forceFill([
                'user_id' => $this->identifierOf($user),
                'user_label' => $this->describe($user),
            ])->save();
        }

        return $activity;
    }

    /**
     * Run something without writing activity rows.
     *
     * A seeder that creates two hundred accounts would otherwise bury the
     * handful of entries a person actually made.
     */
    public function withoutLogging(callable $callback): mixed
    {
        $previous = $this->suspended;
        $this->suspended = true;

        try {
            return $callback();
        } finally {
            $this->suspended = $previous;
        }
    }

    /** Events config asks us not to record — a noisy one can be silenced. */
    private function ignored(string $event): bool
    {
        foreach ((array) config('isproject.activity.ignored_events', []) as $pattern) {
            if (Str::is($pattern, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip anything that should never reach the table, whatever a caller
     * passes. The list mirrors the audit trail's, minus the redaction: an
     * activity row has no "before" value that makes a masked secret useful.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function clean(array $properties): array
    {
        $secrets = (array) config('isproject.activity.ignored_properties', [
            'password', 'password_confirmation', 'current_password',
            'token', 'secret', 'api_key', 'remember_token',
        ]);

        foreach (array_keys($properties) as $key) {
            foreach ($secrets as $pattern) {
                if (Str::is($pattern, (string) $key)) {
                    unset($properties[$key]);

                    break;
                }
            }
        }

        return $properties;
    }

    private function userId(): mixed
    {
        return $this->identifierOf(auth()->user());
    }

    private function userLabel(): ?string
    {
        return $this->describe(auth()->user());
    }

    private function identifierOf(mixed $user): mixed
    {
        if (! $user) {
            return null;
        }

        return method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : ($user->id ?? null);
    }

    private function describe(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        $label = trim((string) ($user->name ?? '')) ?: (string) ($user->email ?? '');

        return $label !== '' ? Str::limit($label, 255, '') : null;
    }

    /**
     * The path, not the full URL: a query string on a sign-in or a password
     * reset can carry a token, and this table is read by people.
     */
    private function url(): ?string
    {
        $request = request();

        return $request ? $request->method().' /'.ltrim($request->path(), '/') : null;
    }
}
