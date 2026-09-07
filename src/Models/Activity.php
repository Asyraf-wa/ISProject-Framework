<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One thing somebody did.
 *
 * Events are plain strings rather than an enum so an application can log its
 * own — `activity('invoice.exported', ...)` needs no change here. The constants
 * below are only the ones the framework raises itself.
 */
class Activity extends Model
{
    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const LOGIN_FAILED = 'login_failed';

    public const LOCKOUT = 'lockout';

    public const REGISTERED = 'registered';

    public const PASSWORD_RESET = 'password_reset';

    public const PASSWORD_CHANGED = 'password_changed';

    public const PROFILE_UPDATED = 'profile_updated';

    public const GOOGLE_LINKED = 'google_linked';

    public const GOOGLE_UNLINKED = 'google_unlinked';

    /** Wording for the events the framework raises, and how to colour them. */
    private const KNOWN = [
        self::LOGIN => ['Signed in', 'success'],
        self::LOGOUT => ['Signed out', 'secondary'],
        self::LOGIN_FAILED => ['Sign-in failed', 'warning'],
        self::LOCKOUT => ['Locked out', 'danger'],
        self::REGISTERED => ['Registered', 'primary'],
        self::PASSWORD_RESET => ['Password reset', 'warning'],
        self::PASSWORD_CHANGED => ['Password changed', 'warning'],
        self::PROFILE_UPDATED => ['Profile updated', 'secondary'],
        self::GOOGLE_LINKED => ['Google account linked', 'primary'],
        self::GOOGLE_UNLINKED => ['Google account disconnected', 'secondary'],
    ];

    /** Events worth watching on a security review, shown first in the filter. */
    public const SECURITY = [self::LOGIN_FAILED, self::LOCKOUT, self::PASSWORD_RESET, self::PASSWORD_CHANGED];

    protected $table = 'isproject_activities';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::KNOWN[$this->event][0] ?? Str::headline($this->event);
    }

    public function tone(): string
    {
        return self::KNOWN[$this->event][1] ?? 'secondary';
    }

    /** Whether this is one of the events a security review cares about. */
    public function isSecurityEvent(): bool
    {
        return in_array($this->event, self::SECURITY, true);
    }

    /**
     * Every event present in the table, for the filter, with the framework's
     * own listed even when nothing has raised them yet.
     *
     * @return array<string, string>
     */
    public static function eventOptions(): array
    {
        $events = self::query()->distinct()->orderBy('event')->pluck('event')->all();
        $options = [];

        foreach (array_unique(array_merge(array_keys(self::KNOWN), $events)) as $event) {
            $options[$event] = self::KNOWN[$event][0] ?? Str::headline($event);
        }

        asort($options);

        return $options;
    }
}
