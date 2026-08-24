<?php

namespace Bitsnio\AsasFlow\Features\Cache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed rememberRoute(string $cacheKey, array $tags, \Closure $callback, ?int $ttl = null)
 * @method static mixed remember(string $key, array $tags, \Closure $callback, ?int $ttl = null)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, array $tags = [], ?int $ttl = null)
 * @method static void invalidateTags(array $tags, ?string $tenantId = null)
 * @method static void invalidateRoute(string $routeName, array $params = [], ?string $tenantId = null)
 * @method static void invalidateModule(string $module, ?string $tenantId = null)
 * @method static void invalidateTenantCache(string $tenantId)
 * @method static void invalidateGlobalModuleCache(string $module)
 * @method static void warmModuleCache(string $module, ?string $tenantId = null)
 * @method static void flushAll()
 * @method static bool supportsTags()
 *
 * @see \AsasFlow\Features\Cache\Services\ModuleCacheManager
 */
class ModuleCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asasflow.cache';
    }
}
