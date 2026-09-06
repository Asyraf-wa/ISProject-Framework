<?php

namespace IsProject\Framework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $table = 'isproject_profiles';

    protected $primaryKey = 'user_id';

    protected $keyType = 'int';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = ['user_id', 'avatar'];

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
