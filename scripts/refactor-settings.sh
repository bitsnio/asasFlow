#!/bin/bash
# =============================================================================
# ASASFLOW Cache Feature Installation Script
# =============================================================================
# Run from package root: bash install-cache-feature.sh
# =============================================================================

set -e

echo "🏗️  Installing ASASFLOW Cache Feature..."

# =============================================================================
# 1. CREATE DIRECTORY STRUCTURE
# =============================================================================

echo "📁 Creating directory structure..."

mkdir -p src/Features/Cache/Attributes
mkdir -p src/Features/Cache/Contracts
mkdir -p src/Features/Cache/Events
mkdir -p src/Features/Cache/Http/Middleware
mkdir -p src/Features/Cache/Http/Controllers
mkdir -p src/Features/Cache/Jobs
mkdir -p src/Features/Cache/Observers
mkdir -p src/Features/Cache/Services
mkdir -p src/Features/Cache/Traits
mkdir -p src/Features/Cache/Console/Commands
mkdir -p src/Features/Cache/Console/Stubs
mkdir -p src/Features/Cache/Facades
mkdir -p src/Features/Cache/routes
mkdir -p src/Generators/Cache

echo "✅ Directories created"

# =============================================================================
# 2. CONFIGURATION FILE
# =============================================================================

cat > config/asasflow-cache.php << 'CONFIG'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Toggle
    |--------------------------------------------------------------------------
    | Use Laravel's default cache.enabled or override via env.
    */
    'enabled' => env('ASASFLOW_CACHE_ENABLED', env('CACHE_ENABLED', true)),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | Uses Laravel's cache.default config. Override via env if needed.
    | Supported: apc, array, database, file, memcached, redis, dynamodb, octane
    */
    'store' => env('ASASFLOW_CACHE_STORE', env('CACHE_STORE', config('cache.default', 'redis'))),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'ttl' => env('ASASFLOW_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Strategy
    |--------------------------------------------------------------------------
    */
    'key_strategy' => [
        'driver' => 'url_context',
        'include_query_params' => true,
        'ignore_params' => [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'tracking_id',
            '_ga',
            'fbclid',
        ],
        'include_headers' => [
            'X-Tenant-ID',
            'Accept-Language',
        ],
        'include_user' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tagging
    |--------------------------------------------------------------------------
    */
    'tagging' => [
        'enabled' => true,
        'auto_tag_models' => true,
        'service_prefix' => env('APP_NAME', 'asasflow-service'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stampede Protection
    |--------------------------------------------------------------------------
    */
    'stampede_protection' => [
        'enabled' => true,
        'lock_ttl' => 10,
        'stale_while_revalidate' => true,
        'stale_ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Control Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'enabled' => true,
        'etag' => true,
        'last_modified' => true,
        'cache_control' => 'public, max-age=300',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass Cache
    |--------------------------------------------------------------------------
    */
    'bypass' => [
        'enabled' => env('ASASFLOW_CACHE_BYPASS_ENABLED', false),
        'header' => 'X-Bypass-Cache',
        'api_key' => env('ASASFLOW_CACHE_BYPASS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Events (Microservices)
    |--------------------------------------------------------------------------
    */
    'distributed' => [
        'enabled' => env('ASASFLOW_CACHE_DISTRIBUTED_ENABLED', false),
        'driver' => env('ASASFLOW_CACHE_DISTRIBUTED_DRIVER', 'redis'),
        'channel' => 'asasflow-cache-invalidation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => env('ASASFLOW_CACHE_DASHBOARD_ENABLED', true),
        'route_prefix' => '_cache',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */
    'telemetry' => [
        'enabled' => true,
        'events' => true,
    ],
];
CONFIG

echo "✅ Configuration created"

# =============================================================================
# 3. PHP 8 ATTRIBUTES
# =============================================================================

cat > src/Features/Cache/Attributes/AutoCache.php << 'ATTRIBUTE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class AutoCache
{
    public function __construct(
        public ?int $ttl = null,
        public ?string $tags = null,
        public bool $ignoreQueryParams = false,
        public ?string $keyStrategy = null,
        public bool $stampedeProtection = true,
    ) {}
}
ATTRIBUTE

cat > src/Features/Cache/Attributes/NoCache.php << 'ATTRIBUTE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class NoCache
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}
ATTRIBUTE

echo "✅ Attributes created"

# =============================================================================
# 4. CONTRACTS
# =============================================================================

cat > src/Features/Cache/Contracts/CacheKeyStrategy.php << 'CONTRACT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Contracts;

use Illuminate\Http\Request;

interface CacheKeyStrategy
{
    public function generate(Request $request, array $context = []): string;
}
CONTRACT

cat > src/Features/Cache/Contracts/CacheInvalidationStrategy.php << 'CONTRACT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Contracts;

interface CacheInvalidationStrategy
{
    public function invalidate(string $modelClass, ?string $modelId = null): void;
    public function invalidateTags(array $tags): void;
}
CONTRACT

echo "✅ Contracts created"

# =============================================================================
# 5. EVENTS
# =============================================================================

cat > src/Features/Cache/Events/CacheHit.php << 'EVENT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheHit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $key,
        public ?string $tags = null,
        public float $responseTime = 0,
    ) {}
}
EVENT

cat > src/Features/Cache/Events/CacheMiss.php << 'EVENT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheMiss
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $key,
        public ?string $reason = null,
        public float $responseTime = 0,
    ) {}
}
EVENT

cat > src/Features/Cache/Events/CacheInvalidated.php << 'EVENT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CacheInvalidated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $type,
        public string $target,
        public ?string $modelClass = null,
        public ?string $modelId = null,
    ) {}
}
EVENT

echo "✅ Events created"

# =============================================================================
# 6. SERVICES
# =============================================================================

cat > src/Features/Cache/Services/CacheKeyGenerator.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Contracts\CacheKeyStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CacheKeyGenerator implements CacheKeyStrategy
{
    public function generate(Request $request, array $context = []): string
    {
        $config = config('asasflow-cache.key_strategy');
        $parts = [];

        $parts[] = config('asasflow-cache.tagging.service_prefix', 'asasflow-service');
        $parts[] = strtolower($request->method());
        $parts[] = $request->path();

        if ($config['include_query_params'] ?? true) {
            $query = $this->filterQueryParams($request->query());
            if (!empty($query)) {
                $parts[] = md5(serialize($query));
            }
        }

        foreach ($config['include_headers'] ?? [] as $header) {
            if ($request->hasHeader($header)) {
                $parts[] = strtolower($header) . ':' . $request->header($header);
            }
        }

        if (($config['include_user'] ?? true) && Auth::check()) {
            $parts[] = 'u:' . Auth::id();
        }

        if (!empty($context)) {
            $parts[] = 'ctx:' . md5(serialize($context));
        }

        return implode('|', $parts);
    }

    protected function filterQueryParams(array $params): array
    {
        $ignore = config('asasflow-cache.key_strategy.ignore_params', []);
        return array_diff_key($params, array_flip($ignore));
    }

    public function generateModelTag(string $modelClass, ?string $modelId = null): string
    {
        $tag = 'model:' . str_replace('\\', '_', $modelClass);
        return $modelId ? "{$tag}:{$modelId}" : $tag;
    }

    public function generateRouteTag(string $routeName): string
    {
        return 'route:' . $routeName;
    }
}
SERVICE

cat > src/Features/Cache/Services/CacheResponseSerializer.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheResponseSerializer
{
    public function serialize(SymfonyResponse $response): array
    {
        return [
            'content' => $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'headers' => $this->serializeHeaders($response->headers->all()),
            'cached_at' => now()->toIso8601String(),
            'etag' => $this->generateEtag($response),
        ];
    }

    public function deserialize(array $data): SymfonyResponse
    {
        return new Response(
            $data['content'] ?? '',
            $data['status_code'] ?? 200,
            $data['headers'] ?? []
        );
    }

    public function generateEtag(SymfonyResponse $response): string
    {
        return md5($response->getContent() . $response->getStatusCode());
    }

    public function isNotModified(Request $request, string $etag): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');
        return $ifNoneMatch && $ifNoneMatch === $etag;
    }

    protected function serializeHeaders(array $headers): array
    {
        unset($headers['connection'], $headers['keep-alive'], $headers['transfer-encoding']);
        return $headers;
    }
}
SERVICE

cat > src/Features/Cache/Services/CacheStampedeProtector.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheStampedeProtector
{
    public function acquireLock(string $key): bool
    {
        $lockKey = $this->lockKey($key);
        $ttl = config('asasflow-cache.stampede_protection.lock_ttl', 10);

        return Cache::lock($lockKey, $ttl)->get();
    }

    public function releaseLock(string $key): void
    {
        Cache::lock($this->lockKey($key))->forceRelease();
    }

    public function getStale(string $key): ?array
    {
        if (!config('asasflow-cache.stampede_protection.stale_while_revalidate', true)) {
            return null;
        }

        $staleKey = $this->staleKey($key);
        return Cache::get($staleKey);
    }

    public function storeStale(string $key, array $data, int $ttl): void
    {
        $staleKey = $this->staleKey($key);
        Cache::put($staleKey, $data, now()->addSeconds($ttl));
    }

    public function dispatchRefresh(string $key, callable $callback, int $ttl): void
    {
        \Bitsnio\AsasFlow\Features\Cache\Jobs\RefreshStaleCache::dispatch($key, $callback, $ttl);
    }

    protected function lockKey(string $key): string
    {
        return "lock:cache:{$key}";
    }

    protected function staleKey(string $key): string
    {
        return "stale:{$key}";
    }
}
SERVICE

cat > src/Features/Cache/Services/CacheStatsCollector.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Support\Facades\Cache;

class CacheStatsCollector
{
    protected array $hits = [];
    protected array $misses = [];
    protected array $entries = [];

    public function recordHit(string $key): void
    {
        $this->hits[] = ['key' => $key, 'at' => now()];
        $this->incrementCounter('hits');
    }

    public function recordMiss(string $key): void
    {
        $this->misses[] = ['key' => $key, 'at' => now()];
        $this->incrementCounter('misses');
    }

    public function recordEntry(string $key, array $meta): void
    {
        $this->entries[$key] = array_merge($meta, [
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    public function all(): array
    {
        return [
            'hits' => count($this->hits),
            'misses' => count($this->misses),
            'hit_ratio' => $this->calculateHitRatio(),
            'total_requests' => count($this->hits) + count($this->misses),
            'entries_count' => count($this->entries),
        ];
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    protected function incrementCounter(string $type): void
    {
        $key = "asasflow-cache:stats:{$type}:" . now()->format('Y-m-d-H');
        Cache::increment($key);
    }

    protected function calculateHitRatio(): float
    {
        $total = count($this->hits) + count($this->misses);
        return $total > 0 ? round(count($this->hits) / $total * 100, 2) : 0;
    }
}
SERVICE

cat > src/Features/Cache/Services/CacheManager.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Events\CacheHit;
use Bitsnio\AsasFlow\Features\Cache\Events\CacheMiss;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CacheManager
{
    protected CacheKeyGenerator $keyGenerator;
    protected CacheResponseSerializer $serializer;
    protected CacheStampedeProtector $stampedeProtector;
    protected CacheStatsCollector $stats;

    public function __construct(
        CacheKeyGenerator $keyGenerator,
        CacheResponseSerializer $serializer,
        CacheStampedeProtector $stampedeProtector,
        CacheStatsCollector $stats,
    ) {
        $this->keyGenerator = $keyGenerator;
        $this->serializer = $serializer;
        $this->stampedeProtector = $stampedeProtector;
        $this->stats = $stats;
    }

    public function remember(Request $request, callable $callback, ?int $ttl = null, array $tags = []): Response
    {
        if (!$this->isEnabled()) {
            return $callback();
        }

        if ($this->shouldBypass($request)) {
            return $callback();
        }

        $key = $this->keyGenerator->generate($request);
        $ttl = $ttl ?? config('asasflow-cache.ttl', 300);
        $store = $this->store();

        $cached = $store->get($key);
        if ($cached && $this->isNotModified($request, $cached['etag'] ?? '')) {
            return new Response('', 304);
        }

        if ($cached) {
            $this->stats->recordHit($key);
            event(new CacheHit($key, implode(',', $tags)));

            return $this->serializer->deserialize($cached);
        }

        $this->stats->recordMiss($key);
        event(new CacheMiss($key));

        if (config('asasflow-cache.stampede_protection.enabled', true)) {
            return $this->handleWithStampedeProtection($key, $callback, $ttl, $tags);
        }

        return $this->executeAndCache($key, $callback, $ttl, $tags);
    }

    public function forget(string $pattern): void
    {
        $store = $this->store();

        if ($this->supportsTags()) {
            $store->tags([$pattern])->flush();
        } else {
            $store->forget($pattern);
        }
    }

    public function invalidateByModel(string $modelClass, ?string $modelId = null): void
    {
        $tag = $this->keyGenerator->generateModelTag($modelClass, $modelId);
        $this->invalidateByTag($tag);

        $classTag = $this->keyGenerator->generateModelTag($modelClass);
        $this->invalidateByTag($classTag);

        $this->publishInvalidation('model', $modelClass, $modelId);
    }

    public function invalidateByTag(string $tag): void
    {
        if ($this->supportsTags()) {
            $this->store()->tags([$tag])->flush();
        }

        event(new \Bitsnio\AsasFlow\Features\Cache\Events\CacheInvalidated(
            'tag', $tag
        ));
    }

    public function invalidateByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->invalidateByTag($tag);
        }
    }

    public function flush(): void
    {
        $this->store()->flush();

        event(new \Bitsnio\AsasFlow\Features\Cache\Events\CacheInvalidated(
            'manual', 'all'
        ));
    }

    public function getStats(): array
    {
        return $this->stats->all();
    }

    public function getEntries(): array
    {
        return $this->stats->getEntries();
    }

    protected function handleWithStampedeProtection(string $key, callable $callback, int $ttl, array $tags): Response
    {
        if ($this->stampedeProtector->acquireLock($key)) {
            try {
                $response = $this->executeAndCache($key, $callback, $ttl, $tags);
                $this->stampedeProtector->storeStale($key, $this->serializer->serialize($response), $ttl);
                return $response;
            } finally {
                $this->stampedeProtector->releaseLock($key);
            }
        }

        $stale = $this->stampedeProtector->getStale($key);
        if ($stale) {
            Log::debug("[AsasFlowCache] Serving stale data for {$key}");
            return $this->serializer->deserialize($stale);
        }

        usleep(100000);
        return $this->remember(request(), $callback, $ttl, $tags);
    }

    protected function executeAndCache(string $key, callable $callback, int $ttl, array $tags): Response
    {
        $response = $callback();

        if (!$this->isCacheableResponse($response)) {
            return $response;
        }

        $data = $this->serializer->serialize($response);
        $store = $this->store();

        if ($this->supportsTags() && !empty($tags)) {
            $store->tags($tags)->put($key, $data, $ttl);
        } else {
            $store->put($key, $data, $ttl);
        }

        return $response;
    }

    protected function isCacheableResponse($response): bool
    {
        return $response instanceof Response
            && $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300;
    }

    protected function isNotModified(Request $request, string $etag): bool
    {
        return $this->serializer->isNotModified($request, $etag);
    }

    protected function shouldBypass(Request $request): bool
    {
        if (!config('asasflow-cache.bypass.enabled', false)) {
            return false;
        }

        $header = config('asasflow-cache.bypass.header', 'X-Bypass-Cache');
        if (!$request->hasHeader($header)) {
            return false;
        }

        $apiKey = config('asasflow-cache.bypass.api_key');
        if ($apiKey && $request->header($header) !== $apiKey) {
            return false;
        }

        return true;
    }

    protected function isEnabled(): bool
    {
        return config('asasflow-cache.enabled', true);
    }

    protected function store()
    {
        return Cache::store(config('asasflow-cache.store', config('cache.default', 'redis')));
    }

    protected function supportsTags(): bool
    {
        $driver = config('cache.stores.' . config('asasflow-cache.store', config('cache.default')) . '.driver', 'file');
        return in_array($driver, ['redis', 'memcached']);
    }

    protected function publishInvalidation(string $type, string $modelClass, ?string $modelId): void
    {
        if (!config('asasflow-cache.distributed.enabled', false)) {
            return;
        }

        $channel = config('asasflow-cache.distributed.channel');
        $payload = [
            'service' => config('asasflow-cache.tagging.service_prefix'),
            'type' => $type,
            'model' => $modelClass,
            'id' => $modelId,
            'timestamp' => now()->toIso8601String(),
        ];

        $driver = config('asasflow-cache.distributed.driver', 'redis');

        match ($driver) {
            'redis' => \Illuminate\Support\Facades\Redis::publish($channel, json_encode($payload)),
            default => Log::info("[AsasFlowCache] Distributed invalidation via {$driver}", $payload),
        };
    }
}
SERVICE

cat > src/Features/Cache/Services/CacheInvalidator.php << 'SERVICE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Contracts\CacheInvalidationStrategy;
use Bitsnio\AsasFlow\Features\Cache\Events\CacheInvalidated;
use Illuminate\Support\Facades\Log;

class CacheInvalidator implements CacheInvalidationStrategy
{
    protected CacheKeyGenerator $keyGenerator;
    protected CacheManager $cacheManager;

    public function __construct(
        CacheKeyGenerator $keyGenerator,
        CacheManager $cacheManager,
    ) {
        $this->keyGenerator = $keyGenerator;
        $this->cacheManager = $cacheManager;
    }

    public function invalidate(string $modelClass, ?string $modelId = null): void
    {
        $this->cacheManager->invalidateByModel($modelClass, $modelId);

        event(new CacheInvalidated('model', $modelClass, $modelClass, $modelId));

        Log::info("[AsasFlowCache] Invalidated cache for {$modelClass}" . ($modelId ? ":{$modelId}" : ''));
    }

    public function invalidateTags(array $tags): void
    {
        $this->cacheManager->invalidateByTags($tags);

        foreach ($tags as $tag) {
            event(new CacheInvalidated('tag', $tag));
        }
    }

    public function invalidateByRoute(string $routeName): void
    {
        $tag = $this->keyGenerator->generateRouteTag($routeName);
        $this->invalidateTags([$tag]);
    }

    public function flush(): void
    {
        $this->cacheManager->flush();
    }

    public function handleDistributedMessage(array $payload): void
    {
        if ($payload['service'] === config('asasflow-cache.tagging.service_prefix')) {
            return;
        }

        $this->invalidate($payload['model'], $payload['id'] ?? null);

        Log::info("[AsasFlowCache] Received distributed invalidation", $payload);
    }
}
SERVICE

echo "✅ Services created"

# =============================================================================
# 7. MIDDLEWARE
# =============================================================================

cat > src/Features/Cache/Http/Middleware/AutoCacheMiddleware.php << 'MIDDLEWARE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Bitsnio\AsasFlow\Features\Cache\Attributes\AutoCache;
use Bitsnio\AsasFlow\Features\Cache\Attributes\NoCache;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

class AutoCacheMiddleware
{
    protected CacheManager $cacheManager;
    protected CacheKeyGenerator $keyGenerator;

    public function __construct(
        CacheManager $cacheManager,
        CacheKeyGenerator $keyGenerator,
    ) {
        $this->cacheManager = $cacheManager;
        $this->keyGenerator = $keyGenerator;
    }

    public function handle(Request $request, Closure $next, ?string $ttl = null, ?string $tags = null): Response
    {
        if (!config('asasflow-cache.enabled', true)) {
            return $next($request);
        }

        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $routeConfig = $this->getRouteCacheConfig($request);
        if ($routeConfig['skip']) {
            return $next($request);
        }

        $ttl = $routeConfig['ttl'] ?? ($ttl ? (int) $ttl : null);
        $tags = $this->resolveTags($request, $routeConfig['tags'] ?? $tags);

        return $this->cacheManager->remember($request, function () use ($next, $request) {
            return $next($request);
        }, $ttl, $tags);
    }

    protected function getRouteCacheConfig(Request $request): array
    {
        $route = $request->route();

        if (!$route) {
            return ['ttl' => null, 'tags' => null, 'skip' => false];
        }

        $controller = $route->getControllerClass();
        $method = $route->getActionMethod();

        if (!$controller || !$method) {
            return ['ttl' => null, 'tags' => null, 'skip' => false];
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);

            foreach ($reflection->getAttributes(NoCache::class) as $attr) {
                return ['ttl' => null, 'tags' => null, 'skip' => true];
            }

            $classReflection = new ReflectionClass($controller);
            foreach ($classReflection->getAttributes(NoCache::class) as $attr) {
                return ['ttl' => null, 'tags' => null, 'skip' => true];
            }

            foreach ($reflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();
                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                ];
            }

            foreach ($classReflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();
                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                ];
            }
        } catch (\ReflectionException $e) {
            // Fallback to middleware params
        }

        return ['ttl' => null, 'tags' => null, 'skip' => false];
    }

    protected function resolveTags(Request $request, ?string $tags): array
    {
        $result = [];

        if ($routeName = $request->route()?->getName()) {
            $result[] = $this->keyGenerator->generateRouteTag($routeName);
        }

        if ($tags) {
            $result = array_merge($result, explode(',', $tags));
        }

        return array_unique(array_filter($result));
    }
}
MIDDLEWARE

cat > src/Features/Cache/Http/Middleware/CacheControlMiddleware.php << 'MIDDLEWARE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheControlMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!config('asasflow-cache.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            config('asasflow-cache.headers.cache_control', 'public, max-age=300')
        );

        $response->headers->set(
            'X-Cache-Service',
            config('asasflow-cache.tagging.service_prefix', 'asasflow-service')
        );

        if ($request->hasHeader('X-Correlation-ID')) {
            $response->headers->set(
                'X-Correlation-ID',
                $request->header('X-Correlation-ID')
            );
        }

        return $response;
    }
}
MIDDLEWARE

echo "✅ Middleware created"

# =============================================================================
# 8. OBSERVER
# =============================================================================

cat > src/Features/Cache/Observers/ModelCacheObserver.php << 'OBSERVER'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Observers;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Illuminate\Database\Eloquent\Model;

class ModelCacheObserver
{
    protected CacheInvalidator $invalidator;
    protected CacheKeyGenerator $keyGenerator;

    public function __construct(
        CacheInvalidator $invalidator,
        CacheKeyGenerator $keyGenerator,
    ) {
        $this->invalidator = $invalidator;
        $this->keyGenerator = $keyGenerator;
    }

    public function created(Model $model): void
    {
        $this->invalidate($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model, 'updated');
        $this->invalidateTouchedRelations($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model, 'forceDeleted');
    }

    protected function invalidate(Model $model, string $event): void
    {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        $tags = $this->getModelCacheTags($model);

        $this->invalidator->invalidate($modelClass, (string) $modelId);

        if (!empty($tags)) {
            $this->invalidator->invalidateTags($tags);
        }

        $this->invalidateRelations($model, $event);
    }

    protected function getModelCacheTags(Model $model): array
    {
        if (method_exists($model, 'getCacheTags')) {
            return $model->getCacheTags();
        }

        if (property_exists($model, 'cacheTags')) {
            return (array) $model->cacheTags;
        }

        return [];
    }

    protected function invalidateTouchedRelations(Model $model): void
    {
        if (!method_exists($model, 'touches')) {
            return;
        }

        foreach ($model->touches as $relation) {
            if ($model->relationLoaded($relation) || $model->isDirty($relation)) {
                $related = $model->$relation;
                if ($related) {
                    $this->invalidator->invalidate(get_class($related), (string) $related->getKey());
                }
            }
        }
    }

    protected function invalidateRelations(Model $model, string $event): void
    {
        if (!method_exists($model, 'getCacheInvalidationRelations')) {
            return;
        }

        $relations = $model->getCacheInvalidationRelations();

        foreach ($relations as $relation => $config) {
            if ($event === 'updated' && !empty($config['on_update'])) {
                $this->invalidateRelation($model, $relation, $config);
            }

            if ($event === 'deleted' && !empty($config['on_delete'])) {
                $this->invalidateRelation($model, $relation, $config);
            }
        }
    }

    protected function invalidateRelation(Model $model, string $relation, array $config): void
    {
        if (!$model->relationLoaded($relation) && !$model->isDirty($relation)) {
            return;
        }

        $related = $model->$relation;

        if ($related instanceof Model) {
            $this->invalidator->invalidate(get_class($related), (string) $related->getKey());
        } elseif ($related instanceof \Illuminate\Database\Eloquent\Collection) {
            foreach ($related as $item) {
                $this->invalidator->invalidate(get_class($item), (string) $item->getKey());
            }
        }
    }
}
OBSERVER

echo "✅ Observer created"

# =============================================================================
# 9. TRAIT
# =============================================================================

cat > src/Features/Cache/Traits/CacheAware.php << 'TRAIT'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Traits;

use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;

trait CacheAware
{
    public static function bootCacheAware(): void
    {
        static::observe(ModelCacheObserver::class);
    }

    public function getCacheTags(): array
    {
        return property_exists($this, 'cacheTags')
            ? (array) $this->cacheTags
            : [];
    }

    public function getCacheInvalidationRelations(): array
    {
        return property_exists($this, 'cacheInvalidationRelations')
            ? (array) $this->cacheInvalidationRelations
            : [];
    }
}
TRAIT

echo "✅ Trait created"

# =============================================================================
# 10. JOB
# =============================================================================

cat > src/Features/Cache/Jobs/RefreshStaleCache.php << 'JOB'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Jobs;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheResponseSerializer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshStaleCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $key,
        public $callback,
        public int $ttl,
    ) {}

    public function handle(
        CacheManager $cacheManager,
        CacheResponseSerializer $serializer,
    ): void {
        $store = Cache::store(config('asasflow-cache.store', config('cache.default', 'redis')));

        $response = ($this->callback)();
        $data = $serializer->serialize($response);

        $store->put($this->key, $data, $this->ttl);

        Log::debug("[AsasFlowCache] Background refresh completed for {$this->key}");
    }
}
JOB

echo "✅ Job created"

# =============================================================================
# 11. CONSOLE COMMANDS
# =============================================================================

cat > src/Features/Cache/Console/Commands/CacheClearCommand.php << 'COMMAND'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Console\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'asasflow:cache:clear
                            {--model= : Model class to invalidate}
                            {--tag= : Specific tag to invalidate}
                            {--all : Flush all cache}';

    protected $description = 'Clear ASASFLOW cache';

    public function handle(CacheManager $manager, CacheInvalidator $invalidator): int
    {
        if ($this->option('all')) {
            $manager->flush();
            $this->info('✅ All cache flushed');
            return 0;
        }

        if ($model = $this->option('model')) {
            $invalidator->invalidate($model);
            $this->info("✅ Cache invalidated for model: {$model}");
            return 0;
        }

        if ($tag = $this->option('tag')) {
            $invalidator->invalidateTags([$tag]);
            $this->info("✅ Cache invalidated for tag: {$tag}");
            return 0;
        }

        $this->error('Please specify --model, --tag, or --all');
        return 1;
    }
}
COMMAND

cat > src/Features/Cache/Console/Commands/CacheStatsCommand.php << 'COMMAND'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Console\Command;

class CacheStatsCommand extends Command
{
    protected $signature = 'asasflow:cache:stats
                            {--entries : Show cached entries}';

    protected $description = 'Show ASASFLOW cache statistics';

    public function handle(CacheManager $manager): int
    {
        $stats = $manager->getStats();

        $this->info('=== ASASFLOW Cache Statistics ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Hits', $stats['hits']],
                ['Total Misses', $stats['misses']],
                ['Hit Ratio', $stats['hit_ratio'] . '%'],
                ['Total Requests', $stats['total_requests']],
                ['Cached Entries', $stats['entries_count']],
            ]
        );

        if ($this->option('entries')) {
            $entries = $manager->getEntries();
            $this->newLine();
            $this->info('=== Cached Entries ===');
            
            $rows = [];
            foreach ($entries as $key => $meta) {
                $rows[] = [
                    \Illuminate\Support\Str::limit($key, 50),
                    $meta['cached_at'] ?? 'N/A',
                    $meta['status_code'] ?? 'N/A',
                ];
            }
            
            $this->table(['Key', 'Cached At', 'Status'], $rows);
        }

        return 0;
    }
}
COMMAND

cat > src/Features/Cache/Console/Commands/CacheWarmCommand.php << 'COMMAND'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Console\Commands;

use Illuminate\Console\Command;

class CacheWarmCommand extends Command
{
    protected $signature = 'asasflow:cache:warm
                            {endpoint : URL endpoint to warm}
                            {--times=1 : Number of requests}';

    protected $description = 'Warm cache by hitting endpoints';

    public function handle(): int
    {
        $endpoint = $this->argument('endpoint');
        $times = (int) $this->option('times');

        for ($i = 0; $i < $times; $i++) {
            $response = \Illuminate\Support\Facades\Http::get($endpoint);
            $this->info("Warmed {$endpoint} - Status: {$response->status()}");
        }

        return 0;
    }
}
COMMAND

echo "✅ Console commands created"

# =============================================================================
# 12. STUB
# =============================================================================

cat > src/Features/Cache/Console/Stubs/cache-observer.stub << 'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;

class {{ class }} extends ModelCacheObserver
{
    protected array $cacheTags = [
        {{ tags }}
    ];

    protected array $cacheInvalidationRelations = {{ relations }};

    public function __construct(
        CacheInvalidator $invalidator,
        CacheKeyGenerator $keyGenerator,
    ) {
        parent::__construct($invalidator, $keyGenerator);
    }
}
STUB

echo "✅ Stub created"

# =============================================================================
# 13. DASHBOARD CONTROLLER & ROUTES
# =============================================================================

cat > src/Features/Cache/Http/Controllers/CacheDashboardController.php << 'CONTROLLER'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Controllers;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CacheDashboardController
{
    public function __construct(
        protected CacheManager $cacheManager,
    ) {}

    public function index(): Response
    {
        $stats = $this->cacheManager->getStats();
        $entries = $this->cacheManager->getEntries();

        return response()->view('asasflow-cache::dashboard', [
            'stats' => $stats,
            'entries' => $entries,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->cacheManager->getStats());
    }

    public function entries(): JsonResponse
    {
        return response()->json([
            'entries' => $this->cacheManager->getEntries(),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cacheManager->flush();

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared',
        ]);
    }
}
CONTROLLER

cat > src/Features/Cache/routes/dashboard.php << 'ROUTES'
<?php

use Bitsnio\AsasFlow\Features\Cache\Http\Controllers\CacheDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('asasflow-cache.dashboard.route_prefix', '_cache'))
    ->middleware(config('asasflow-cache.dashboard.middleware', ['web']))
    ->group(function () {
        
        Route::get('/', [CacheDashboardController::class, 'index'])
            ->name('asasflow-cache.dashboard');
        
        Route::get('/api/stats', [CacheDashboardController::class, 'stats'])
            ->name('asasflow-cache.api.stats');
        
        Route::get('/api/entries', [CacheDashboardController::class, 'entries'])
            ->name('asasflow-cache.api.entries');
        
        Route::post('/clear', [CacheDashboardController::class, 'clear'])
            ->name('asasflow-cache.clear');
    });
ROUTES

echo "✅ Dashboard controller & routes created"

# =============================================================================
# 14. FACADE
# =============================================================================

cat > src/Features/Cache/Facades/MicroCache.php << 'FACADE'
<?php

namespace Bitsnio\AsasFlow\Features\Cache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\Response remember(\Illuminate\Http\Request $request, callable $callback, ?int $ttl = null, array $tags = [])
 * @method static void invalidateByModel(string $modelClass, ?string $modelId = null)
 * @method static void invalidateByTag(string $tag)
 * @method static void invalidateByTags(array $tags)
 * @method static void flush()
 * @method static array getStats()
 * @method static array getEntries()
 *
 * @see \Bitsnio\AsasFlow\Features\Cache\Services\CacheManager
 */
class MicroCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'asasflow.cache';
    }
}
FACADE

echo "✅ Facade created"

# =============================================================================
# 15. SERVICE PROVIDER
# =============================================================================

cat > src/Features/Cache/CacheServiceProvider.php << 'PROVIDER'
<?php

namespace Bitsnio\AsasFlow\Features\Cache;

use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheClearCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheStatsCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheWarmCommand;
use Bitsnio\AsasFlow\Features\Cache\Facades\MicroCache;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\AutoCacheMiddleware;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\CacheControlMiddleware;
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheResponseSerializer;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheStampedeProtector;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheStatsCollector;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheKeyGenerator::class);
        $this->app->singleton(CacheResponseSerializer::class);
        $this->app->singleton(CacheStampedeProtector::class);
        $this->app->singleton(CacheStatsCollector::class);

        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(CacheKeyGenerator::class),
                $app->make(CacheResponseSerializer::class),
                $app->make(CacheStampedeProtector::class),
                $app->make(CacheStatsCollector::class),
            );
        });

        $this->app->singleton(CacheInvalidator::class, function ($app) {
            return new CacheInvalidator(
                $app->make(CacheKeyGenerator::class),
                $app->make(CacheManager::class),
            );
        });

        $this->app->singleton('asasflow.cache', function ($app) {
            return $app->make(CacheManager::class);
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/asasflow-cache.php',
            'asasflow-cache'
        );
    }

    public function boot(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('asasflow.cache', AutoCacheMiddleware::class);
        $router->aliasMiddleware('asasflow.cache.control', CacheControlMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheClearCommand::class,
                CacheStatsCommand::class,
                CacheWarmCommand::class,
            ]);
        }

        if (config('asasflow-cache.dashboard.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/dashboard.php');
        }

        $this->publishes([
            __DIR__ . '/../../config/asasflow-cache.php' => config_path('asasflow-cache.php'),
        ], 'asasflow-cache-config');
    }
}
PROVIDER

echo "✅ Service provider created"

# =============================================================================
# 16. GENERATOR
# =============================================================================

cat > src/Generators/Cache/CacheObserverGenerator.php << 'GENERATOR'
<?php

namespace Bitsnio\AsasFlow\Generators\Cache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CacheObserverGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $module, string $model, array $config = []): string
    {
        $modelClass = Str::studly($model);
        $observerClass = "{$modelClass}CacheObserver";
        
        $namespace = "Modules\\{$module}\\Observers";
        $modelNamespace = "Modules\\{$module}\\Models";
        
        $path = base_path("Modules/{$module}/Observers/{$observerClass}.php");
        
        $tags = $this->buildTags($config['tags'] ?? [], $module);
        $relations = $this->buildRelations($config['relations'] ?? []);
        
        $stub = $this->getStub();
        
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ tags }}',
                '{{ relations }}',
            ],
            [
                $namespace,
                $modelNamespace,
                $observerClass,
                $modelClass,
                $tags,
                $relations,
            ],
            $stub
        );
        
        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        
        return $path;
    }

    protected function buildTags(array $tags, string $module): string
    {
        $defaults = ["'{$module}-service-{$module}'"];
        
        foreach ($tags as $tag) {
            $defaults[] = "'{$tag}'";
        }
        
        return implode(",\n        ", $defaults);
    }

    protected function buildRelations(array $relations): string
    {
        if (empty($relations)) {
            return '[]';
        }

        $items = [];
        foreach ($relations as $relation => $config) {
            $items[] = "'{$relation}' => ['on_update' => true, 'on_delete' => " . ($config['on_delete'] ?? 'false') . "]";
        }

        return "[\n            " . implode(",\n            ", $items) . "\n        ]";
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;

class {{ class }} extends ModelCacheObserver
{
    protected array $cacheTags = [
        {{ tags }}
    ];

    protected array $cacheInvalidationRelations = {{ relations }};

    public function __construct(
        CacheInvalidator $invalidator,
        CacheKeyGenerator $keyGenerator,
    ) {
        parent::__construct($invalidator, $keyGenerator);
    }
}
STUB;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}
GENERATOR

echo "✅ Generator created"

# =============================================================================
# 17. SUMMARY
# =============================================================================

echo ""
echo "🎉 ASASFLOW Cache Feature Installation Complete!"
echo ""
echo "📁 Files created:"
echo "   - config/asasflow-cache.php"
echo "   - src/Features/Cache/Attributes/AutoCache.php"
echo "   - src/Features/Cache/Attributes/NoCache.php"
echo "   - src/Features/Cache/Contracts/*.php"
echo "   - src/Features/Cache/Events/*.php"
echo "   - src/Features/Cache/Services/*.php"
echo "   - src/Features/Cache/Http/Middleware/*.php"
echo "   - src/Features/Cache/Http/Controllers/*.php"
echo "   - src/Features/Cache/Observers/ModelCacheObserver.php"
echo "   - src/Features/Cache/Traits/CacheAware.php"
echo "   - src/Features/Cache/Jobs/RefreshStaleCache.php"
echo "   - src/Features/Cache/Console/Commands/*.php"
echo "   - src/Features/Cache/Console/Stubs/cache-observer.stub"
echo "   - src/Features/Cache/Facades/MicroCache.php"
echo "   - src/Features/Cache/routes/dashboard.php"
echo "   - src/Features/Cache/CacheServiceProvider.php"
echo "   - src/Generators/Cache/CacheObserverGenerator.php"
echo ""
echo "🔧 Update your AsasFlowServiceProvider manually (see below)"
echo ""
echo "📋 Environment variables to add:"
echo "   ASASFLOW_CACHE_ENABLED=true"
echo "   ASASFLOW_CACHE_STORE=redis"
echo "   ASASFLOW_CACHE_TTL=300"
echo "   ASASFLOW_CACHE_BYPASS_ENABLED=false"
echo "   ASASFLOW_CACHE_DISTRIBUTED_ENABLED=false"
echo "   ASASFLOW_CACHE_DASHBOARD_ENABLED=true"
echo ""
echo "✅ Done!"