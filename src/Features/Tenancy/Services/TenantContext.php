<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Tenancy\Services;

use Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant;
use Bitsnio\AsasFlow\Features\Tenancy\Models\TenantDomain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantContext
{
    protected static ?Model $currentTenant = null;
    protected static int|string|null $currentSiteId = null;
    protected static bool $initialized = false;
    protected static ?string $originalPrefix = null;

    /**
     * Initialize tenant (and optional site) from request.
     */
    public static function initialize(?string $identifier = null): ?Model
    {
        if (self::$initialized && !$identifier) {
            return self::$currentTenant;
        }

        $tenant = self::resolveTenant($identifier);

        if ($tenant) {
            self::setTenant($tenant);
            self::resolveAndSetSite();
        }

        self::$initialized = true;

        return $tenant;
    }

    /**
     * Resolve tenant from various sources.
     */
    public static function resolveTenant(?string $identifier = null): ?Model
    {
        if ($identifier) {
            return self::findTenant($identifier);
        }

        if ($header = request()->header('X-Tenant-ID')) {
            return self::findTenant($header);
        }

        if ($slug = request()->header('X-Tenant-Slug')) {
            return self::findTenant($slug);
        }

        $domain = request()->getHost();
        $ttl = (int) config('asasflow.tenancy.cache_resolution_ttl', 3600);

        return Cache::remember("asasflow:tenant:domain:{$domain}", $ttl, function () use ($domain) {
            $domainRecord = TenantDomain::where('domain', $domain)
                ->where('is_verified', true)
                ->with('tenant')
                ->first();

            return $domainRecord?->tenant;
        });
    }

    /**
     * Resolve site ID from request header or authenticated user.
     */
    public static function resolveAndSetSite(): void
    {
        if (!config('asasflow.tenancy.site_enabled', true)) {
            return;
        }

        $siteId = request()->header('X-Site-ID')
            ?? request()->input('site_id')
            ?? auth()->user()?->site_id
            ?? null;

        if ($siteId !== null && $siteId !== '') {
            self::setSiteId($siteId);
        }
    }

    /**
     * Find tenant by ID or slug using configured tenant model.
     */
    public static function findTenant(string $identifier): ?Model
    {
        $modelClass = config('asasflow.tenancy.tenant_model', Tenant::class);
        $ttl = (int) config('asasflow.tenancy.cache_resolution_ttl', 3600);

        return Cache::remember("asasflow:tenant:id:{$identifier}", $ttl, function () use ($modelClass, $identifier) {
            return $modelClass::query()
                ->where('is_active', true)
                ->where(function ($query) use ($identifier) {
                    $query->where('slug', $identifier)
                        ->orWhere('id', $identifier);
                })
                ->first();
        });
    }

    /**
     * Set current tenant.
     */
    public static function setTenant(Model $tenant): void
    {
        self::$currentTenant = $tenant;

        if (self::$originalPrefix === null) {
            self::$originalPrefix = (string) config('cache.prefix');
        }

        Config::set('cache.prefix', "t{$tenant->getKey()}_" . self::$originalPrefix);

        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            self::switchDatabase($tenant);
        }

        app()->instance('current.tenant', $tenant);
        app()->instance($tenant::class, $tenant);
    }

    public static function setSiteId(int|string|null $siteId): void
    {
        self::$currentSiteId = $siteId;
    }

    public static function getCurrentTenant(): ?Model
    {
        return self::$currentTenant ?? (app()->bound('current.tenant') ? app('current.tenant') : null);
    }

    public static function getTenantId(): int|string|null
    {
        return self::getCurrentTenant()?->getKey();
    }

    public static function getSiteId(): int|string|null
    {
        return self::$currentSiteId;
    }

    public static function getTenantSlug(): ?string
    {
        return self::getCurrentTenant()?->getAttribute('slug');
    }

    public static function isTenantInitialized(): bool
    {
        return self::$currentTenant !== null;
    }

    public static function reset(): void
    {
        self::$currentTenant = null;
        self::$currentSiteId = null;
        self::$initialized = false;

        if (self::$originalPrefix !== null) {
            Config::set('cache.prefix', self::$originalPrefix);
        }

        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            Config::set('database.default', config('asasflow.tenancy.central_connection', 'mysql'));
            DB::purge('tenant');
        }
    }

    public static function runForTenant(Model|string $tenant, callable $callback, int|string|null $siteId = null): mixed
    {
        $previousTenant = self::$currentTenant;
        $previousSiteId = self::$currentSiteId;

        if (is_string($tenant)) {
            $resolved = self::findTenant($tenant);
            if (!$resolved) {
                throw new RuntimeException("Tenant not found: {$tenant}");
            }
            $tenant = $resolved;
        }

        try {
            self::setTenant($tenant);
            if ($siteId !== null) {
                self::setSiteId($siteId);
            }

            return $callback();
        } finally {
            if ($previousTenant) {
                self::setTenant($previousTenant);
                self::setSiteId($previousSiteId);
            } else {
                self::reset();
            }
        }
    }

    protected static function switchDatabase(Model $tenant): void
    {
        $connection = config('asasflow.tenancy.tenant_connection', 'tenant');
        $dbName = method_exists($tenant, 'getDatabaseName')
            ? $tenant->getDatabaseName()
            : ($tenant->getAttribute('database') ?? "tenant_{$tenant->getAttribute('slug')}");

        Config::set("database.connections.{$connection}.database", $dbName);

        DB::purge($connection);
        DB::reconnect($connection);

        Config::set('database.default', $connection);
    }
}
