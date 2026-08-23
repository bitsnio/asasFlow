<?php

namespace AsasFlow\Features\Cache\Services;

use AsasFlow\Features\Cache\Models\CacheRegistry;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ModuleCacheManager
{
    protected string $prefix;
    protected string $store;
    protected bool $strictIsolation;

    public function __construct()
    {
        $this->prefix = config('asasflow.cache.prefix', 'asasflow');
        $this->store = config('asasflow.cache.store', config('cache.default', 'redis'));
        $this->strictIsolation = config('asasflow.cache.strict_isolation', true);
    }

    /**
     * Cache a route response with module tags.
     */
    public function rememberRoute(
        string $cacheKey,
        array $tags,
        \Closure $callback,
        ?int $ttl = null
    ): mixed {
        $ttl ??= config('asasflow.cache.default_ttl', 3600);
        $key = $this->buildKey($cacheKey);
        
        // Auto-inject tenant tags if enabled
        $tags = $this->getTenantTags($tags);

        if ($this->supportsTags()) {
            $result = Cache::store($this->store)
                ->tags($this->prefixTags($tags))
                ->remember($key, $ttl, $callback);
        } else {
            $result = Cache::store($this->store)->remember($key, $ttl, $callback);
            $this->registerKey($key, $tags, $ttl);
        }

        return $result;
    }

    /**
     * Remember a value with tags.
     */
    public function remember(
        string $key,
        array $tags,
        \Closure $callback,
        ?int $ttl = null
    ): mixed {
        return $this->rememberRoute($key, $tags, $callback, $ttl);
    }

    /**
     * Get value from cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->buildKey($key);
        return Cache::store($this->store)->get($key, $default);
    }

    /**
     * Put value in cache.
     */
    public function put(string $key, mixed $value, array $tags = [], ?int $ttl = null): bool
    {
        $ttl ??= config('asasflow.cache.default_ttl', 3600);
        $key = $this->buildKey($key);
        
        $tags = $this->getTenantTags($tags);

        if ($this->supportsTags()) {
            Cache::store($this->store)
                ->tags($this->prefixTags($tags))
                ->put($key, $value, $ttl);
        } else {
            Cache::store($this->store)->put($key, $value, $ttl);
            $this->registerKey($key, $tags, $ttl);
        }

        return true;
    }

    /**
     * Invalidate cache by tags.
     */
    public function invalidateTags(array $tags, ?string $tenantId = null): void
    {
        if (empty($tags)) return;

        // Apply tenant scoping
        if ($tenantId) {
            $tags = array_map(fn($t) => "t:{$tenantId}:{$t}", $tags);
        } elseif (TenantContext::isTenantInitialized()) {
            $tags = $this->getTenantTags($tags);
        }

        $prefixedTags = $this->prefixTags($tags);

        if ($this->supportsTags()) {
            try {
                Cache::store($this->store)->tags($prefixedTags)->flush();
                Log::info('[ASASFLOW] Cache invalidated by tags', ['tags' => $prefixedTags]);
            } catch (\Exception $e) {
                Log::error('[ASASFLOW] Cache invalidation failed', [
                    'tags' => $prefixedTags,
                    'error' => $e->getMessage()
                ]);
                $this->fallbackInvalidation($tags);
            }
        } else {
            $this->fallbackInvalidation($tags);
        }
    }

    /**
     * Invalidate specific route cache.
     */
    public function invalidateRoute(string $routeName, array $params = [], ?string $tenantId = null): void
    {
        $key = $this->buildRouteKey($routeName, $params, $tenantId);
        Cache::store($this->store)->forget($key);
    }

    /**
     * Invalidate all cache for a module.
     */
    public function invalidateModule(string $module, ?string $tenantId = null): void
    {
        $this->invalidateTags(["module:{$module}", "module:{$module}:*"], $tenantId);
    }

    /**
     * Invalidate ALL cache for a specific tenant.
     */
    public function invalidateTenantCache(string $tenantId): void
    {
        $this->invalidateTags(['module:*'], $tenantId);
        
        // Clear tenant resolution cache
        Cache::forget("{$this->prefix}:tenant:id:{$tenantId}");
        
        // Clear registry records
        CacheRegistry::forTenant($tenantId)->delete();
    }

    /**
     * Invalidate module cache across ALL tenants (for global updates).
     */
    public function invalidateGlobalModuleCache(string $module): void
    {
        if (!$this->supportsTags()) {
            $this->redisPatternDelete("{$this->prefix}:t:*:module:{$module}*");
            return;
        }

        // For Redis, use pattern delete
        if (config("cache.stores.{$this->store}.driver") === 'redis') {
            $this->redisPatternDelete("{$this->prefix}:t:*:module:{$module}*");
        }
    }

    /**
     * Warm cache for a module.
     */
    public function warmModuleCache(string $module, ?string $tenantId = null): void
    {
        if (config('asasflow.cache.warm_via_queue', true)) {
            \AsasFlow\Features\Cache\Jobs\WarmModuleCache::dispatch($module, $tenantId);
            return;
        }

        // Direct warming (for CLI)
        $this->performCacheWarm($module, $tenantId);
    }

    /**
     * Perform actual cache warming.
     */
    public function performCacheWarm(string $module, ?string $tenantId = null): void
    {
        // To be implemented per module needs
        // Typically fetches common endpoints and caches them
        Log::info("[ASASFLOW] Warming cache for module: {$module}");
    }

    /**
     * Flush all asasflow cache.
     */
    public function flushAll(): void
    {
        if ($this->supportsTags()) {
            Cache::store($this->store)->tags(["{$this->prefix}:global"])->flush();
        }

        // Clear registry
        CacheRegistry::query()->delete();
        
        Log::info('[ASASFLOW] All cache flushed');
    }

    /**
     * Check if cache driver supports tags.
     */
    public function supportsTags(): bool
    {
        $driver = config("cache.stores.{$this->store}.driver", 'file');
        return in_array($driver, ['redis', 'memcached']);
    }

    /**
     * Get tenant-scoped tags.
     */
    public function getTenantTags(array $tags): array
    {
        if (!$this->strictIsolation || !TenantContext::isTenantInitialized()) {
            return $tags;
        }

        $tenantId = TenantContext::getTenantId();
        return array_map(fn($tag) => "t:{$tenantId}:{$tag}", $tags);
    }

    /**
     * Prefix tags with package namespace.
     */
    protected function prefixTags(array $tags): array
    {
        return array_map(fn($tag) => "{$this->prefix}:{$tag}", $tags);
    }

    /**
     * Build cache key.
     */
    protected function buildKey(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }

    /**
     * Build route cache key.
     */
    protected function buildRouteKey(string $routeName, array $params, ?string $tenantId = null): string
    {
        $prefix = $tenantId ? "t:{$tenantId}:" : '';
        $paramString = empty($params) ? '' : ':' . md5(serialize($params));
        return $this->buildKey("{$prefix}route:{$routeName}{$paramString}");
    }

    /**
     * Register key in fallback registry (for non-tag drivers).
     */
    protected function registerKey(string $key, array $tags, int $ttl): void
    {
        if (!config('asasflow.cache.fallback_registry', true)) {
            return;
        }

        try {
            CacheRegistry::create([
                'tenant_id' => TenantContext::getTenantId(),
                'module' => $this->extractModuleFromTags($tags),
                'cache_key' => $key,
                'tags' => $tags,
                'expires_at' => now()->addSeconds($ttl),
            ]);
        } catch (\Exception $e) {
            Log::warning('[ASASFLOW] Failed to register cache key', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Extract module name from tags.
     */
    protected function extractModuleFromTags(array $tags): ?string
    {
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'module:')) {
                return explode(':', $tag)[1] ?? null;
            }
        }
        return null;
    }

    /**
     * Fallback invalidation for non-tag drivers.
     */
    protected function fallbackInvalidation(array $tags): void
    {
        $modules = [];
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'module:')) {
                $modules[] = explode(':', $tag)[1] ?? null;
            }
        }

        $query = CacheRegistry::query();
        
        if (!empty($modules)) {
            $query->whereIn('module', array_filter($modules));
        }

        // Apply tenant scope
        if (TenantContext::isTenantInitialized()) {
            $query->forTenant(TenantContext::getTenantId());
        }

        $entries = $query->get();

        foreach ($entries as $entry) {
            Cache::store($this->store)->forget($entry->cache_key);
            $entry->delete();
        }
    }

    /**
     * Redis pattern delete helper.
     */
    protected function redisPatternDelete(string $pattern): void
    {
        try {
            $connection = config("cache.stores.{$this->store}.connection", 'default');
            $redis = Redis::connection($connection);
            $iterator = null;
            
            do {
                $keys = $redis->scan($iterator, ['match' => $pattern, 'count' => 1000]);
                
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($iterator !== 0);
        } catch (\Exception $e) {
            Log::error('[ASASFLOW] Redis pattern delete failed', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }
    }
}
