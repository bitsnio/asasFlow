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
