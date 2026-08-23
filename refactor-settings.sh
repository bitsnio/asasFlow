#!/bin/bash
# =============================================================================
# ASASFLOW Custom Cache Feature Installation Script
# =============================================================================
# Run from your package root: bash install-cache-feature.sh
# =============================================================================

set -e

echo "🏗️  Installing ASASFLOW Cache Feature..."

# =============================================================================
# 1. CREATE DIRECTORY STRUCTURE
# =============================================================================

echo "📁 Creating directory structure..."

mkdir -p src/Features/Cache
mkdir -p src/Features/Cache/Contracts
mkdir -p src/Features/Cache/Models
mkdir -p src/Features/Cache/Repositories
mkdir -p src/Features/Cache/Services
mkdir -p src/Features/Cache/Observers
mkdir -p src/Features/Cache/Http
mkdir -p src/Features/Cache/Http/Middleware
mkdir -p src/Features/Cache/Http/Controllers
mkdir -p src/Features/Cache/Facades
mkdir -p src/Features/Cache/routes
mkdir -p src/Features/Cache/Console
mkdir -p src/Features/Cache/Console/Commands
mkdir -p src/Features/Cache/Console/Commands/Stubs
mkdir -p src/Features/Tenancy
mkdir -p src/Features/Tenancy/Contracts
mkdir -p src/Features/Tenancy/Models
mkdir -p src/Features/Tenancy/Services
mkdir -p src/Features/Tenancy/Http
mkdir -p src/Features/Tenancy/Http/Middleware
mkdir -p src/Generators/Cache
mkdir -p database/migrations
mkdir -p config

echo "✅ Directories created"

# =============================================================================
# 2. CACHE CONTRACTS
# =============================================================================

cat > src/Features/Cache/Contracts/CacheableModule.php << 'CONTRACT'
<?php

namespace AsasFlow\Features\Cache\Contracts;

interface CacheableModule
{
    /**
     * Get cache tags for this module.
     * Returns array like ['module:users', 'module:users:list']
     */
    public static function getCacheTags(): array;

    /**
     * Get cache tags to invalidate when this model changes.
     */
    public static function getInvalidationTags(): array;

    /**
     * Cache TTL in seconds. Null = forever.
     */
    public static function getCacheTtl(): ?int;

    /**
     * Whether this model is tenant-aware.
     */
    public static function isTenantAware(): bool;
}
CONTRACT

echo "✅ Cache contracts created"

# =============================================================================
# 3. CACHE MODELS
# =============================================================================

cat > src/Features/Cache/Models/CacheRegistry.php << 'MODEL'
<?php

namespace AsasFlow\Features\Cache\Models;

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
MODEL

echo "✅ Cache models created"

# =============================================================================
# 4. CACHE SERVICES
# =============================================================================

cat > src/Features/Cache/Services/ModuleCacheManager.php << 'SERVICE'
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
SERVICE

cat > src/Features/Cache/Services/CacheObserverManager.php << 'SERVICE'
<?php

namespace AsasFlow\Features\Cache\Services;

use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Log;

class CacheObserverManager
{
    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Invalidate cache on model created.
     */
    public function handleCreated($model, array $tags): void
    {
        $tenantId = $this->getTenantId($model);
        
        $this->cacheManager->invalidateTags($tags, $tenantId);
        
        Log::debug('[ASASFLOW] Cache invalidated on create', [
            'model' => get_class($model),
            'tags' => $tags,
            'tenant' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache on model updated.
     */
    public function handleUpdated($model, array $tags, array $relationshipTags = []): void
    {
        $tenantId = $this->getTenantId($model);
        
        $allTags = array_merge($tags, $relationshipTags);
        
        $this->cacheManager->invalidateTags($allTags, $tenantId);
        
        Log::debug('[ASASFLOW] Cache invalidated on update', [
            'model' => get_class($model),
            'tags' => $allTags,
            'tenant' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache on model deleted.
     */
    public function handleDeleted($model, array $tags): void
    {
        $tenantId = $this->getTenantId($model);
        
        $this->cacheManager->invalidateTags($tags, $tenantId);
        
        Log::debug('[ASASFLOW] Cache invalidated on delete', [
            'model' => get_class($model),
            'tags' => $tags,
            'tenant' => $tenantId,
        ]);
    }

    /**
     * Get tenant ID from model or context.
     */
    protected function getTenantId($model): ?string
    {
        // Multi-DB strategy: tenant from context
        if (config('asasflow.tenancy.database_strategy') === 'separate') {
            return TenantContext::getTenantId();
        }

        // Single-DB strategy: tenant_id on model
        if (isset($model->tenant_id)) {
            return $model->tenant_id;
        }

        return TenantContext::getTenantId();
    }
}
SERVICE

echo "✅ Cache services created"

# =============================================================================
# 5. CACHE MIDDLEWARE
# =============================================================================

cat > src/Features/Cache/Http/Middleware/ModuleRouteCache.php << 'MIDDLEWARE'
<?php

namespace AsasFlow\Features\Cache\Http\Middleware;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleRouteCache
{
    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Handle incoming request with cache.
     */
    public function handle(Request $request, Closure $next, string $tags = '', ?int $ttl = null): Response
    {
        // Skip caching for non-GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Skip if cache disabled
        if (!config('asasflow.cache.enabled', true)) {
            return $next($request);
        }

        // Skip for authenticated requests if configured
        if ($request->user() && !config('asasflow.cache.cache_authenticated', false)) {
            return $next($request);
        }

        $cacheKey = $this->resolveCacheKey($request);
        $tagsArray = array_filter(explode(',', $tags));
        
        // Auto-inject module tag
        $module = $this->resolveModule($request);
        if ($module) {
            $tagsArray[] = "module:{$module}";
        }

        // Auto-inject tenant tag
        if (TenantContext::isTenantInitialized()) {
            $tagsArray[] = 'tenant:' . TenantContext::getTenantId();
        }

        return $this->cacheManager->rememberRoute(
            $cacheKey,
            $tagsArray,
            fn() => $next($request),
            $ttl
        );
    }

    /**
     * Resolve cache key from request.
     */
    protected function resolveCacheKey(Request $request): string
    {
        $tenantPrefix = TenantContext::isTenantInitialized() 
            ? TenantContext::getTenantSlug() . ':' 
            : '';

        return sprintf(
            '%sroute:%s:%s',
            $tenantPrefix,
            $request->route()->getName() ?? $request->path(),
            md5($request->fullUrl() . serialize($request->all()))
        );
    }

    /**
     * Resolve module name from route.
     */
    protected function resolveModule(Request $request): ?string
    {
        $routeName = $request->route()->getName();
        
        if (!$routeName) {
            return null;
        }

        // Extract module from route name: module.users.index -> users
        if (str_starts_with($routeName, 'module.')) {
            $parts = explode('.', $routeName);
            return $parts[1] ?? null;
        }

        // Check route action for module
        $action = $request->route()->getAction();
        return $action['module'] ?? null;
    }
}
MIDDLEWARE

cat > src/Features/Cache/Http/Middleware/CacheHeaders.php << 'MIDDLEWARE'
<?php

namespace AsasFlow\Features\Cache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheHeaders
{
    /**
     * Add cache headers to response.
     */
    public function handle(Request $request, Closure $next, string $visibility = 'private', ?int $maxAge = null): Response
    {
        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        $maxAge ??= config('asasflow.cache.http_max_age', 0);

        if ($visibility === 'public') {
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
        } else {
            $response->headers->set('Cache-Control', "private, max-age={$maxAge}");
        }

        // Add cache tags header for debugging (if enabled)
        if (config('asasflow.cache.debug_headers', false) && app()->isLocal()) {
            $response->headers->set('X-ASASFLOW-Cache', 'HIT');
        }

        return $response;
    }
}
MIDDLEWARE

echo "✅ Cache middleware created"

# =============================================================================
# 6. CACHE CONTROLLERS
# =============================================================================

cat > src/Features/Cache/Http/Controllers/CacheManagementController.php << 'CONTROLLER'
<?php

namespace AsasFlow\Features\Cache\Http\Controllers;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CacheManagementController
{
    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Flush all cache.
     */
    public function flush(Request $request): JsonResponse
    {
        $this->cacheManager->flushAll();

        return response()->json([
            'success' => true,
            'message' => 'All cache flushed successfully',
        ]);
    }

    /**
     * Flush module cache.
     */
    public function flushModule(Request $request, string $module): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        
        $this->cacheManager->invalidateModule($module, $tenantId);

        return response()->json([
            'success' => true,
            'message' => "Cache flushed for module: {$module}",
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Warm module cache.
     */
    public function warmModule(Request $request, string $module): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        
        $this->cacheManager->warmModuleCache($module, $tenantId);

        return response()->json([
            'success' => true,
            'message' => "Cache warming initiated for module: {$module}",
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get cache status.
     */
    public function status(Request $request): JsonResponse
    {
        $store = config('asasflow.cache.store', config('cache.default'));
        $driver = config("cache.stores.{$store}.driver", 'file');

        return response()->json([
            'enabled' => config('asasflow.cache.enabled', true),
            'store' => $store,
            'driver' => $driver,
            'supports_tags' => $this->cacheManager->supportsTags(),
            'strict_isolation' => config('asasflow.cache.strict_isolation', true),
            'current_tenant' => TenantContext::getTenantSlug(),
            'prefix' => config('asasflow.cache.prefix', 'asasflow'),
        ]);
    }
}
CONTROLLER

echo "✅ Cache controllers created"

# =============================================================================
# 7. CACHE ROUTES
# =============================================================================

cat > src/Features/Cache/routes/api.php << 'ROUTES'
<?php

use AsasFlow\Features\Cache\Http\Controllers\CacheManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('asasflow/cache')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        
        Route::get('/status', [CacheManagementController::class, 'status'])
            ->name('asasflow.cache.status');
        
        Route::post('/flush', [CacheManagementController::class, 'flush'])
            ->name('asasflow.cache.flush');
        
        Route::post('/flush/{module}', [CacheManagementController::class, 'flushModule'])
            ->name('asasflow.cache.flush.module');
        
        Route::post('/warm/{module}', [CacheManagementController::class, 'warmModule'])
            ->name('asasflow.cache.warm.module');
    });
ROUTES

echo "✅ Cache routes created"

# =============================================================================
# 8. CACHE FACADE
# =============================================================================

cat > src/Features/Cache/Facades/ModuleCache.php << 'FACADE'
<?php

namespace AsasFlow\Features\Cache\Facades;

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
FACADE

echo "✅ Cache facade created"

# =============================================================================
# 9. CACHE CONSOLE COMMANDS
# =============================================================================

cat > src/Features/Cache/Console/Commands/CacheFlushCommand.php << 'COMMAND'
<?php

namespace AsasFlow\Features\Cache\Console\Commands;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Console\Command;

class CacheFlushCommand extends Command
{
    protected $signature = 'asasflow:cache:flush
                            {module? : Module name to flush (optional)}
                            {--tenant= : Tenant ID for scoped flush}
                            {--all : Flush all cache}';

    protected $description = 'Flush ASASFLOW module cache';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            $this->cacheManager->flushAll();
            $this->info('✅ All ASASFLOW cache flushed');
            return 0;
        }

        $module = $this->argument('module');
        
        if (!$module) {
            $this->error('Please provide a module name or use --all');
            return 1;
        }

        $tenantId = $this->option('tenant');
        
        $this->cacheManager->invalidateModule($module, $tenantId);
        
        $this->info("✅ Cache flushed for module: {$module}");
        
        if ($tenantId) {
            $this->info("   Tenant: {$tenantId}");
        }

        return 0;
    }
}
COMMAND

cat > src/Features/Cache/Console/Commands/CacheWarmCommand.php << 'COMMAND'
<?php

namespace AsasFlow\Features\Cache\Console\Commands;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Console\Command;

class CacheWarmCommand extends Command
{
    protected $signature = 'asasflow:cache:warm
                            {module : Module name to warm}
                            {--tenant= : Tenant ID for scoped warming}';

    protected $description = 'Warm ASASFLOW module cache';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $tenantId = $this->option('tenant');

        $this->info("🔥 Warming cache for module: {$module}...");
        
        $this->cacheManager->warmModuleCache($module, $tenantId);
        
        $this->info('✅ Cache warming initiated');
        
        return 0;
    }
}
COMMAND

cat > src/Features/Cache/Console/Commands/CacheStatusCommand.php << 'COMMAND'
<?php

namespace AsasFlow\Features\Cache\Console\Commands;

use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Console\Command;

class CacheStatusCommand extends Command
{
    protected $signature = 'asasflow:cache:status';

    protected $description = 'Show ASASFLOW cache status';

    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    public function handle(): int
    {
        $store = config('asasflow.cache.store', config('cache.default'));
        $driver = config("cache.stores.{$store}.driver", 'file');

        $this->info('=== ASASFLOW Cache Status ===');
        $this->table(
            ['Property', 'Value'],
            [
                ['Enabled', config('asasflow.cache.enabled', true) ? '✅ Yes' : '❌ No'],
                ['Store', $store],
                ['Driver', $driver],
                ['Tag Support', $this->cacheManager->supportsTags() ? '✅ Yes' : '❌ No'],
                ['Prefix', config('asasflow.cache.prefix', 'asasflow')],
                ['Default TTL', config('asasflow.cache.default_ttl', 3600) . 's'],
                ['Strict Isolation', config('asasflow.cache.strict_isolation', true) ? '✅ Yes' : '❌ No'],
                ['Fallback Registry', config('asasflow.cache.fallback_registry', true) ? '✅ Yes' : '❌ No'],
            ]
        );

        return 0;
    }
}
COMMAND

echo "✅ Cache console commands created"

# =============================================================================
# 10. CACHE OBSERVER STUBS
# =============================================================================

cat > src/Features/Cache/Console/Commands/Stubs/cache-observer.stub << 'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use AsasFlow\Features\Cache\Services\CacheObserverManager;
use AsasFlow\Features\Cache\Services\ModuleCacheManager;

class {{ class }}
{
    protected ModuleCacheManager $cacheManager;
    protected CacheObserverManager $observerManager;

    public function __construct(
        ModuleCacheManager $cacheManager,
        CacheObserverManager $observerManager
    ) {
        $this->cacheManager = $cacheManager;
        $this->observerManager = $observerManager;
    }

    /**
     * Handle the {{ model }} "created" event.
     */
    public function created({{ model }} $model): void
    {
        $this->observerManager->handleCreated($model, [
            'module:{{ module_slug }}',
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }

    /**
     * Handle the {{ model }} "updated" event.
     */
    public function updated({{ model }} $model): void
    {
        $relationshipTags = [];

        // Add relationship invalidation tags based on dirty fields
        {{ relationship_tags }}

        $this->observerManager->handleUpdated($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
        ], $relationshipTags);
    }

    /**
     * Handle the {{ model }} "deleted" event.
     */
    public function deleted({{ model }} $model): void
    {
        $this->observerManager->handleDeleted($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }

    /**
     * Handle the {{ model }} "forceDeleted" event.
     */
    public function forceDeleted({{ model }} $model): void
    {
        $this->deleted($model);
    }

    /**
     * Handle the {{ model }} "restored" event.
     */
    public function restored({{ model }} $model): void
    {
        $this->created($model);
    }
}
STUB

echo "✅ Cache observer stubs created"

# =============================================================================
# 11. TENANCY SERVICES
# =============================================================================

cat > src/Features/Tenancy/Contracts/TenantAware.php << 'CONTRACT'
<?php

namespace AsasFlow\Features\Tenancy\Contracts;

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
CONTRACT

cat > src/Features/Tenancy/Models/Tenant.php << 'MODEL'
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
MODEL

cat > src/Features/Tenancy/Models/TenantDomain.php << 'MODEL'
<?php

namespace AsasFlow\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
MODEL

cat > src/Features/Tenancy/Services/TenantContext.php << 'SERVICE'
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
SERVICE

cat > src/Features/Tenancy/Http/Middleware/InitializeTenancy.php << 'MIDDLEWARE'
<?php

namespace AsasFlow\Features\Tenancy\Http\Middleware;

use AsasFlow\Features\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    /**
     * Handle request with tenant initialization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for central routes
        if ($this->isCentralRoute($request)) {
            return $next($request);
        }

        // Skip if tenancy disabled
        if (!config('asasflow.tenancy.enabled', false)) {
            return $next($request);
        }

        $tenant = TenantContext::initialize();

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found or inactive',
                'domain' => $request->getHost(),
            ], 404);
        }

        // Attach tenant to request
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        return $next($request);
    }

    /**
     * Check if route is central (landlord).
     */
    protected function isCentralRoute(Request $request): bool
    {
        $centralRoutes = config('asasflow.tenancy.central_routes', [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ]);

        $routeName = $request->route()?->getName() ?? '';
        
        foreach ($centralRoutes as $pattern) {
            if ($request->is($pattern) || str_starts_with($routeName, str_replace('/*', '', $pattern))) {
                return true;
            }
        }

        // Check central domains
        $centralDomains = config('asasflow.tenancy.central_domains', []);
        if (in_array($request->getHost(), $centralDomains)) {
            // Only central routes allowed on central domains
            return !$request->is('api/module/*');
        }

        return false;
    }
}
MIDDLEWARE

echo "✅ Tenancy services created"

# =============================================================================
# 12. GENERATORS
# =============================================================================

cat > src/Generators/Cache/CacheObserverGenerator.php << 'GENERATOR'
<?php

namespace AsasFlow\Generators\Cache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CacheObserverGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Generate cache observer for a module.
     */
    public function generate(string $module, string $model, array $config = []): string
    {
        $moduleSlug = Str::snake($module);
        $modelClass = Str::studly($model);
        $observerClass = "{$modelClass}CacheObserver";
        
        $namespace = "Modules\\{$module}\\Observers";
        $modelNamespace = "Modules\\{$module}\\Models";
        
        $path = base_path("Modules/{$module}/Observers/{$observerClass}.php");
        
        // Build relationship tags
        $relationshipTags = $this->buildRelationshipTags($config['relationships'] ?? [], $moduleSlug);
        
        $stub = $this->getStub();
        
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ module_slug }}',
                '{{ relationship_tags }}',
            ],
            [
                $namespace,
                $modelNamespace,
                $observerClass,
                $modelClass,
                $moduleSlug,
                $relationshipTags,
            ],
            $stub
        );
        
        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        
        return $path;
    }

    /**
     * Build relationship invalidation tags.
     */
    protected function buildRelationshipTags(array $relationships, string $moduleSlug): string
    {
        if (empty($relationships)) {
            return '// No relationships configured';
        }

        $lines = [];
        foreach ($relationships as $relation => $config) {
            $foreignKey = $config['foreign_key'] ?? Str::snake($relation) . '_id';
            $relatedModule = $config['module'] ?? Str::plural($relation);
            
            $lines[] = "        if (\\$model->isDirty('{$foreignKey}')) {";
            $lines[] = "            \\$relationshipTags[] = \"module:{$relatedModule}:{\\$model->getOriginal('{$foreignKey}')}:{$moduleSlug}\";";
            $lines[] = "            \\$relationshipTags[] = \"module:{$relatedModule}:{\\$model->{$foreignKey}}:{$moduleSlug}\";";
            $lines[] = "        }";
        }

        return implode("\n", $lines);
    }

    /**
     * Get observer stub.
     */
    protected function getStub(): string
    {
        $stubPath = __DIR__ . '/../../Features/Cache/Console/Commands/Stubs/cache-observer.stub';
        
        if ($this->files->exists($stubPath)) {
            return $this->files->get($stubPath);
        }

        // Fallback stub
        return $this->getFallbackStub();
    }

    /**
     * Get fallback stub content.
     */
    protected function getFallbackStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use AsasFlow\Features\Cache\Services\CacheObserverManager;
use AsasFlow\Features\Cache\Services\ModuleCacheManager;

class {{ class }}
{
    protected ModuleCacheManager $cacheManager;
    protected CacheObserverManager $observerManager;

    public function __construct(
        ModuleCacheManager $cacheManager,
        CacheObserverManager $observerManager
    ) {
        $this->cacheManager = $cacheManager;
        $this->observerManager = $observerManager;
    }

    public function created({{ model }} $model): void
    {
        $this->observerManager->handleCreated($model, [
            'module:{{ module_slug }}',
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }

    public function updated({{ model }} $model): void
    {
        $this->observerManager->handleUpdated($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
        ]);
    }

    public function deleted({{ model }} $model): void
    {
        $this->observerManager->handleDeleted($model, [
            'module:{{ module_slug }}',
            "module:{{ module_slug }}:{$model->id}",
            'module:{{ module_slug }}:list',
            'module:{{ module_slug }}:count',
        ]);
    }
}
STUB;
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
GENERATOR

echo "✅ Generators created"

# =============================================================================
# 13. MIGRATIONS
# =============================================================================

cat > database/migrations/2026_08_23_000000_create_asasflow_tenants_table.php << 'MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asasflow_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('database')->nullable();
            $table->json('settings')->nullable();
            $table->string('plan')->default('basic');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asasflow_tenants');
    }
};
MIGRATION

cat > database/migrations/2026_08_23_000001_create_asasflow_tenant_domains_table.php << 'MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asasflow_tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('asasflow_tenants')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asasflow_tenant_domains');
    }
};
MIGRATION

cat > database/migrations/2026_08_23_000002_create_asasflow_cache_registry_table.php << 'MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asasflow_cache_registry', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('module')->index();
            $table->string('cache_key')->index();
            $table->json('tags')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'module']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asasflow_cache_registry');
    }
};
MIGRATION

echo "✅ Migrations created"

# =============================================================================
# 14. CONFIGURATION
# =============================================================================

cat > config/asasflow.php << 'CONFIG'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Enable/disable caching
        'enabled' => env('ASASFLOW_CACHE_ENABLED', true),

        // Cache store to use (from cache.php stores)
        'store' => env('ASASFLOW_CACHE_STORE', config('cache.default', 'redis')),

        // Key prefix for all asasflow cache keys
        'prefix' => env('ASASFLOW_CACHE_PREFIX', 'asasflow'),

        // Default TTL in seconds
        'default_ttl' => env('ASASFLOW_CACHE_TTL', 3600),

        // Strict tenant isolation (prefix all keys with tenant ID)
        'strict_isolation' => env('ASASFLOW_CACHE_STRICT_ISOLATION', true),

        // Cache authenticated requests
        'cache_authenticated' => env('ASASFLOW_CACHE_AUTH', false),

        // Use queue for cache warming
        'warm_via_queue' => env('ASASFLOW_CACHE_QUEUE', true),

        // Maintain key registry for non-tag drivers (file/database)
        'fallback_registry' => true,

        // Add debug headers to responses
        'debug_headers' => env('ASASFLOW_CACHE_DEBUG', false),

        // HTTP cache headers max-age
        'http_max_age' => env('ASASFLOW_CACHE_HTTP_MAX_AGE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id column) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Central domains (landlord access)
        'central_domains' => explode(',', env('ASASFLOW_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),

        // Routes that skip tenant resolution
        'central_routes' => [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ],

        // Tenant model class
        'tenant_model' => \AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

        // Auto-create tenant database on tenant creation
        'auto_create_db' => true,

        // Auto-run tenant migrations
        'auto_migrate' => true,

        // Cache tenant resolution
        'cache_resolution' => true,
        'cache_resolution_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'path' => base_path('Modules'),
        'cache_discovery' => true,
    ],
];
CONFIG

echo "✅ Configuration created"

# =============================================================================
# 15. SERVICE PROVIDER
# =============================================================================

cat > src/Features/Cache/CacheServiceProvider.php << 'PROVIDER'
<?php

namespace AsasFlow\Features\Cache;

use AsasFlow\Features\Cache\Console\Commands\CacheFlushCommand;
use AsasFlow\Features\Cache\Console\Commands\CacheStatusCommand;
use AsasFlow\Features\Cache\Console\Commands\CacheWarmCommand;
use AsasFlow\Features\Cache\Facades\ModuleCache;
use AsasFlow\Features\Cache\Http\Middleware\CacheHeaders;
use AsasFlow\Features\Cache\Http\Middleware\ModuleRouteCache;
use AsasFlow\Features\Cache\Services\CacheObserverManager;
use AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register cache manager
        $this->app->singleton(ModuleCacheManager::class, function ($app) {
            return new ModuleCacheManager();
        });

        // Register observer manager
        $this->app->singleton(CacheObserverManager::class, function ($app) {
            return new CacheObserverManager($app->make(ModuleCacheManager::class));
        });

        // Register facade accessor
        $this->app->singleton('asasflow.cache', function ($app) {
            return $app->make(ModuleCacheManager::class);
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/asasflow.php',
            'asasflow'
        );
    }

    public function boot(): void
    {
        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('asasflow.cache', ModuleRouteCache::class);
        $router->aliasMiddleware('asasflow.cache.headers', CacheHeaders::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheFlushCommand::class,
                CacheWarmCommand::class,
                CacheStatusCommand::class,
            ]);
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/asasflow.php' => config_path('asasflow.php'),
        ], 'asasflow-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'asasflow-migrations');
    }
}
PROVIDER

cat > src/Features/Tenancy/TenancyServiceProvider.php << 'PROVIDER'
<?php

namespace AsasFlow\Features\Tenancy;

use AsasFlow\Features\Tenancy\Http\Middleware\InitializeTenancy;
use AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tenant context is stateless, no singleton needed
    }

    public function boot(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware('asasflow.tenancy', InitializeTenancy::class);

        // Auto-apply tenancy to module routes
        $this->autoApplyTenancy();
    }

    protected function autoApplyTenancy(): void
    {
        if (!config('asasflow.tenancy.enabled', false)) {
            return;
        }

        $router = $this->app['router'];

        // Apply to all module routes
        $router->matched(function ($route, $request) {
            $name = $route->getName() ?? '';
            
            if (str_starts_with($name, 'module.')) {
                $route->middleware('asasflow.tenancy');
            }
        });
    }
}
PROVIDER

echo "✅ Service providers created"

# =============================================================================
# 16. MAIN SERVICE PROVIDER UPDATE
# =============================================================================

cat > src/AsasFlowServiceProvider.php << 'PROVIDER'
<?php

namespace AsasFlow;

use AsasFlow\Features\Cache\CacheServiceProvider;
use AsasFlow\Features\Settings\SettingsServiceProvider;
use AsasFlow\Features\Tenancy\TenancyServiceProvider;
use AsasFlow\Console\ConsoleServiceProvider;
use Illuminate\Support\ServiceProvider;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register feature service providers
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);
        
        // Register tenancy if enabled
        if (config('asasflow.tenancy.enabled', false)) {
            $this->app->register(TenancyServiceProvider::class);
        }

        // Register console commands
        $this->app->register(ConsoleServiceProvider::class);
    }

    public function boot(): void
    {
        // Boot model observers for all modules
        $this->bootModuleObservers();
    }

    protected function bootModuleObservers(): void
    {
        if (!config('asasflow.cache.enabled', true)) {
            return;
        }

        $modulesPath = config('asasflow.modules.path', base_path('Modules'));
        
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);
            $observerPath = $modulePath . '/Observers';
            
            if (!is_dir($observerPath)) {
                continue;
            }

            foreach (glob($observerPath . '/*CacheObserver.php') as $observerFile) {
                $observerClass = $this->resolveObserverClass($module, basename($observerFile, '.php'));
                $modelClass = $this->resolveModelClass($module, $observerFile);

                if ($observerClass && $modelClass && class_exists($modelClass)) {
                    $modelClass::observe($observerClass);
                }
            }
        }
    }

    protected function resolveObserverClass(string $module, string $observerName): ?string
    {
        $class = "Modules\\{$module}\\Observers\\{$observerName}";
        return class_exists($class) ? $class : null;
    }

    protected function resolveModelClass(string $module, string $observerFile): ?string
    {
        // Extract model name from observer filename: UserCacheObserver -> User
        $observerName = basename($observerFile, '.php');
        $modelName = str_replace('CacheObserver', '', $observerName);
        
        $class = "Modules\\{$module}\\Models\\{$modelName}";
        return class_exists($class) ? $class : null;
    }
}
PROVIDER

echo "✅ Main service provider updated"

# =============================================================================
# 17. USAGE DOCUMENTATION
# =============================================================================

cat > CACHE_USAGE.md << 'USAGE'
# ASASFLOW Cache Feature Usage

## Basic Route Caching

```php
// In your module routes file
Route::get('/users', [UserController::class, 'index'])
    ->middleware('asasflow.cache:module:users,users:list')
    ->name('module.users.index');