<?php

namespace AsasFlow\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'domain',
        'database',
        'settings',
        'is_active',
        'plan',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function getDatabaseName(): string
    {
        return $this->database ?? "tenant_{$this->slug}";
    }
}
