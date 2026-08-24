<?php

namespace Bitsnio\AsasFlow\Features\Tenancy\Contracts;

interface TenantAware
{
    /**
     * Get the tenant identifier for this resource.
     */
    public function getTenantId(): ?string;

    /**
     * Scope queries to current tenant.
     */
    public function scopeForCurrentTenant($query);
}
