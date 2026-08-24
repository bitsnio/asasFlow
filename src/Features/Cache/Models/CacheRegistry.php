<?php

namespace Bitsnio\AsasFlow\Features\Cache\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacheRegistry extends Model
{
    protected $table = 'asasflow_cache_registry';

    protected $fillable = [
        'tenant_id',
        'module',
        'cache_key',
        'tags',
        'expires_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            config('asasflow.tenancy.tenant_model', \AsasFlow\Features\Tenancy\Models\Tenant::class),
            'tenant_id'
        );
    }

    /**
     * Scope by tenant.
     */
    public function scopeForTenant($query, ?string $tenantId = null)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }

        if (\AsasFlow\Features\Tenancy\Services\TenantContext::isTenantInitialized()) {
            return $query->where('tenant_id', \AsasFlow\Features\Tenancy\Services\TenantContext::getTenantId());
        }

        return $query->whereNull('tenant_id');
    }

    /**
     * Scope by module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope expired entries.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
