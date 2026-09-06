<?php

namespace IsProject\Framework\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answers "may this user reach this route", and keeps the answer cheap.
 *
 * Permission lookups happen on every guarded request and again for every
 * {{ @can() }} in a menu, so each user's set is resolved once per request and
 * held in the cache store between requests.
 *
 * Invalidation is by version counter rather than by key. Editing one role can
 * change what hundreds of users may do; bumping a single integer retires all
 * of their entries at once, and there is no list of affected users to walk.
 */
class Access
{
    public const VERSION_KEY = 'isproject.rbac.version';

    /** Resolved permission sets for this request, keyed by user id. */
    private array $memo = [];

    /**
     * Every permission name this user holds, through any of their roles.
     *
     * @return array<int, string>
     */
    public function permissionsFor(Authenticatable $user): array
    {
        $id = $user->getAuthIdentifier();

        if (isset($this->memo[$id])) {
            return $this->memo[$id];
        }

        return $this->memo[$id] = $this->remember("user.{$id}", fn () => $this->fetchPermissions($id));
    }

    public function has(Authenticatable $user, string $permission): bool
    {
        return $this->isSuperAdmin($user)
            || in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * A super admin passes everything without holding a single permission row.
     *
     * Without this, an administrator who saved a role matrix with the wrong
     * boxes ticked would lose access to the screen that lets them fix it.
     */
    public function isSuperAdmin(Authenticatable $user): bool
    {
        $id = $user->getAuthIdentifier();

        return (bool) $this->remember("super.{$id}", fn () => DB::table('isproject_role_user')
            ->join('isproject_roles', 'isproject_roles.id', '=', 'isproject_role_user.role_id')
            ->where('isproject_role_user.user_id', $id)
            ->where('isproject_roles.is_super_admin', true)
            ->exists());
    }

    /**
     * Whether anyone has set RBAC up yet.
     *
     * With no roles in the system there is nothing to enforce, and enforcing
     * anyway would deny every request — the middleware could be added to a
     * route group before a single role exists, which is the natural order to
     * do it in. One role is enough to mean "this is configured now".
     */
    public function isConfigured(): bool
    {
        return (bool) $this->remember('configured', fn () => DB::table('isproject_roles')->exists());
    }

    /**
     * Names that RBAC is responsible for. Used by the Gate hook to tell a
     * permission apart from an ordinary policy ability, so `can('update', $post)`
     * still reaches the policy.
     *
     * @return array<int, string>
     */
    public function knownPermissions(): array
    {
        return $this->remember('permissions', fn () => DB::table('isproject_permissions')->pluck('name')->all());
    }

    /** Retire every cached answer. Cheap: one integer changes. */
    public static function flush(): void
    {
        try {
            Cache::increment(self::VERSION_KEY);
        } catch (Throwable) {
            // A cache store that cannot count is not a reason to fail a save;
            // the worst case is a stale read until the next write succeeds.
        }
    }

    /**
     * Read through the cache under the current version, degrading to a direct
     * query when the cache or the tables are not there — the shell renders
     * before the first migration, and a 500 helps nobody.
     */
    private function remember(string $key, callable $callback): mixed
    {
        try {
            $version = Cache::get(self::VERSION_KEY, 1);

            return Cache::remember("isproject.rbac.v{$version}.{$key}", 300, $callback);
        } catch (Throwable) {
            try {
                return $callback();
            } catch (Throwable) {
                return [];
            }
        }
    }

    /** @return array<int, string> */
    private function fetchPermissions(mixed $userId): array
    {
        return DB::table('isproject_role_user')
            ->join('isproject_permission_role', 'isproject_permission_role.role_id', '=', 'isproject_role_user.role_id')
            ->join('isproject_permissions', 'isproject_permissions.id', '=', 'isproject_permission_role.permission_id')
            ->where('isproject_role_user.user_id', $userId)
            ->distinct()
            ->pluck('isproject_permissions.name')
            ->all();
    }
}
