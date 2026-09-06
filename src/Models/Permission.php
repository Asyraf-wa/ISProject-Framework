<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One permissible named route.
 *
 * Rows are written by PermissionRegistry::sync(), never by hand — the routes
 * the application has registered are the source of truth.
 */
class Permission extends Model
{
    protected $table = 'isproject_permissions';

    protected $fillable = ['name'];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'isproject_permission_role');
    }
}
