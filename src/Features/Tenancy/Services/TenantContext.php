<?php

namespace AsasFlow\Features\Tenancy\Services;

use AsasFlow\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected static ?Tenant $currentTenant = null;
    protected static bool $initialized = false;
    protected static ?string $originalPrefix = null;

    /**
     * Initialize tenant from request.
     */
    public static function initialize(?string $identifier = null): ?Tenant
    {
        if (self::$initialized && !$identifier) {
            return self::$currentTenant;
        }

        $tenant = self::resolveTenant($identifier);
        
        if ($tenant) {
            self::setTenant($tenant);
        }

        self::$initialized = true;
        return $tenant;
    }

    /**
     * Resolve tenant from various sources.
     */
    public static function resolveTenant(?string $identifier = null): ?Tenant
    {
        // 1. Explicit identifier
        if ($identifier) {
            return self::findTenant($identifier);
        }

        // 2. X-Tenant-ID header
        if ($header = request()->header('X-Tenant-ID')) {
            return self::findTenant($header);
        }

        // 3. X-Tenant-Slug header
        if ($slug = request()->header('X-Tenant-Slug')) {
            return self::findTenant($slug);
        }

        // 4. Domain-based resolution
        $domain = request()->getHost();
        return Cache::remember("asasflow:tenant:domain:{$domain}", 3600, function () use ($domain) {
            $domainRecord = TenantDomain::where('domain', $domain)
                ->where('is_verified', true)
                ->with('tenant')
                ->first();

            return $domainRecord?->tenant;
        });
    }

    /**
     * Find tenant by ID or slug.
     */
    public static function findTenant(string $identifier): ?Tenant
    {
        return Cache::remember("asasflow:tenant:id:{$identifier}", 3600, function () use ($identifier) {
            return Tenant::where('slug', $identifier)
                ->orWhere('id', $identifier)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Set current tenant.
     */
    public static function setTenant(Tenant $tenant): void
    {
        self::$currentTenant = $tenant;

        // Store original cache prefix
        if (self::$originalPrefix === null) {
            self::$originalPrefix = config('cache.prefix');
        }

        // Set tenant-scoped cache prefix
        Config::set('cache.prefix', "t{$tenant->id}_" . self::$originalPrefix);

        // Switch database if using separate strategy
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            self::switchDatabase($tenant);
        }

        // Bind to container
        app()->instance('current.tenant', $tenant);
        app()->instance(Tenant::class, $tenant);
    }

    /**
     * Get current tenant.
     */
    public static function getCurrentTenant(): ?Tenant
    {
        return self::$currentTenant ?? app('current.tenant', null);
    }

    /**
     * Get tenant ID.
     */
    public static function getTenantId(): ?string
    {
        return self::getCurrentTenant()?->id;
    }

    /**
     * Get tenant slug.
     */
    public static function getTenantSlug(): ?string
    {
        return self::getCurrentTenant()?->slug;
    }

    /**
     * Check if tenant is initialized.
     */
    public static function isTenantInitialized(): bool
    {
        return self::$currentTenant !== null;
    }

    /**
     * Reset tenant context.
     */
    public static function reset(): void
    {
        self::$currentTenant = null;
        self::$initialized = false;
        
        if (self::$originalPrefix !== null) {
            Config::set('cache.prefix', self::$originalPrefix);
        }

        // Reset database connection
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            Config::set('database.default', config('asasflow.tenancy.central_connection', 'mysql'));
            DB::purge('tenant');
        }
    }

    /**
     * Execute callback within tenant context.
     */
    public static function runForTenant(Tenant|string $tenant, callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;
        
        if (is_string($tenant)) {
            $tenant = self::findTenant($tenant);
        }

        if (!$tenant) {
            throw new \RuntimeException("Tenant not found: {$tenant}");
        }

        try {
            self::setTenant($tenant);
            return $callback();
        } finally {
            if ($previousTenant) {
                self::setTenant($previousTenant);
            } else {
                self::reset();
            }
        }
    }

    /**
     * Switch database connection for tenant.
     */
    protected static function switchDatabase(Tenant $tenant): void
    {
        $connection = config('asasflow.tenancy.tenant_connection', 'tenant');
        
        Config::set("database.connections.{$connection}.database", $tenant->getDatabaseName());
        
        DB::purge($connection);
        DB::reconnect($connection);
        
        Config::set('database.default', $connection);
    }
}
