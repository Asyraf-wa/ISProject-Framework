<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use IsProject\Framework\Support\Access;

class Role extends Model
{
    protected $table = 'isproject_roles';

    protected $fillable = ['name', 'description', 'is_super_admin'];

    protected function casts(): array
    {
        return ['is_super_admin' => 'boolean'];
    }

    /**
     * Any change to a role changes what somebody may do, and permissions are
     * cached per request and in the cache store. Bumping the version here means
     * no caller has to remember to.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Access::flush());
        static::deleted(fn () => Access::flush());
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'isproject_permission_role');
    }

    /** @return BelongsToMany<Model, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            'isproject_role_user',
            'role_id',
            'user_id',
        );
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        return $this->permissions->pluck('name')->all();
    }
}
