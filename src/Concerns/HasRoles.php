<?php

namespace IsProject\Framework\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;

/**
 * Add to your User model to give it roles:
 *
 *     class User extends Authenticatable
 *     {
 *         use HasRoles;
 *     }
 *
 * Authorisation itself still goes through Laravel's own Gate — $user->can(),
 * the "can" directive in Blade, $this->authorize() in controllers — because the
 * service provider registers a Gate::before hook that consults these roles.
 * Nothing here replaces the API students should be learning.
 */
trait HasRoles
{
    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'isproject_role_user', 'user_id', 'role_id');
    }

    /** @param  string|array<int, string>  $names */
    public function hasRole(string|array $names): bool
    {
        $held = $this->roles->pluck('name')->all();

        foreach ((array) $names as $name) {
            if (in_array($name, $held, true)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return app(Access::class)->isSuperAdmin($this);
    }

    /**
     * Whether this user may reach a named route.
     *
     * $user->can('products.index') does the same thing through the Gate; this
     * is here for the times you want to ask without going through it.
     */
    public function hasPermission(string $permission): bool
    {
        return app(Access::class)->has($this, $permission);
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        return app(Access::class)->permissionsFor($this);
    }

    /**
     * Replace this user's roles.
     *
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        $this->unsetRelation('roles');

        Access::flush();
    }
}
