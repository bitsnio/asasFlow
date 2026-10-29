<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Models;

use Bitsnio\AsasFlow\Features\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use SoftDeletes, BelongsToTenant;

    protected $attributes = [
        'guard_name' => 'api',
        'is_active' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'guard_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
