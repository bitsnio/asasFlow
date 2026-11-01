<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Cache\Events\CacheHit;
use Bitsnio\AsasFlow\Features\Cache\Events\CacheInvalidated;
use Bitsnio\AsasFlow\Features\Cache\Events\CacheMiss;
use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class CacheManager
{
    protected const TAG_INDEX_PREFIX = 'asasflow-cache:tag-index:';

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

    public function remember(
        Request $request,
        callable $callback,
        ?int $ttl = null,
        array $tags = [],
        array $options = [],
    ): Response {
        if (!$this->isEnabled() || $this->shouldBypass($request)) {
            $response = $callback();
            $this->stripInternalHeaders($response);

            return $response;
        }

        $keyContext = [];
        if (!empty($options['ignore_query_params'])) {
            $keyContext['ignore_query_params'] = true;
        }

        $key = $this->keyGenerator->generate($request, $keyContext);
        $ttl = $ttl ?? (int) config('asasflow-cache.ttl', 300);

        $cached = $this->getFromStore($key, $tags);
        if ($cached && $this->isNotModified($request, (string) ($cached['etag'] ?? ''))) {
            return new Response('', 304, [
                'ETag' => (string) $cached['etag'],
                'X-AsasFlow-Cache' => 'HIT',
            ]);
        }

        if ($cached) {
            $this->stats->recordHit($key);
            event(new CacheHit($key, implode(',', $tags)));

            return $this->serializer->deserialize($cached);
        }

        $this->stats->recordMiss($key);
        event(new CacheMiss($key));

        $stampedeEnabled = array_key_exists('stampede_protection', $options)
            ? (bool) $options['stampede_protection']
            : (bool) config('asasflow-cache.stampede_protection.enabled', true);

        if ($stampedeEnabled) {
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
        if ($modelId !== null && $modelId !== '') {
            $tag = $this->keyGenerator->generateModelTag($modelClass, $modelId);
            $this->invalidateByTag($tag);
        }

        $classTag = $this->keyGenerator->generateModelTag($modelClass);
        $this->invalidateByTag($classTag);

        $this->publishInvalidation('model', $modelClass, $modelId);
    }

    public function invalidateByTag(string $tag): void
    {
        $store = $this->store();

        if ($this->supportsTags()) {
            $store->tags([$tag])->flush();
        }

        // Also flush any indexed keys (supports non-tag stores or mixed lookups)
        $indexKey = self::TAG_INDEX_PREFIX . $tag;
        $indexedKeys = $store->get($indexKey, []);
        if (is_array($indexedKeys)) {
            foreach ($indexedKeys as $cacheKey) {
                $store->forget((string) $cacheKey);
                $this->stampedeProtector->clearStale((string) $cacheKey);
            }
        }
        $store->forget($indexKey);

        event(new CacheInvalidated('tag', $tag));
    }

    public function invalidateByTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->invalidateByTag((string) $tag);
        }
    }

    public function flush(): void
    {
        $this->store()->flush();

        event(new CacheInvalidated('manual', 'all'));
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
                if ($this->isCacheableResponse($response)) {
                    $staleTtl = $ttl + (int) config('asasflow-cache.stampede_protection.stale_ttl', 60);
                    $this->stampedeProtector->storeStale($key, $this->serializer->serialize($response), $staleTtl);
                }

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

        return $this->executeAndCache($key, $callback, $ttl, $tags);
    }

    protected function executeAndCache(string $key, callable $callback, int $ttl, array $tags): Response
    {
        $response = $callback();

        $extractedModelTags = $this->extractResponseModelTags($response);
        $this->stripInternalHeaders($response);

        if (!$this->isCacheableResponse($response)) {
            return $response;
        }

        $allTags = array_values(array_unique(array_filter(array_merge($tags, $extractedModelTags))));
        $data = $this->serializer->serialize($response);
        $store = $this->store();

        // Always store by direct key so ->get($key) works regardless of dynamic response tags
        $store->put($key, $data, $ttl);

        if (!empty($allTags)) {
            if ($this->supportsTags()) {
                $store->tags($allTags)->put($key, $data, $ttl);
            }

            $this->indexTagsForKey($key, $allTags, $ttl);
        }

        $this->stats->recordEntry($key, [
            'tags' => $allTags,
            'ttl' => $ttl,
            'status_code' => $response->getStatusCode(),
            'etag' => $data['etag'] ?? null,
        ]);

        $response->headers->set('X-AsasFlow-Cache', 'MISS');

        return $response;
    }

    protected function getFromStore(string $key, array $tags = []): ?array
    {
        $store = $this->store();

        if ($this->supportsTags() && !empty($tags)) {
            $tagged = $store->tags($tags)->get($key);
            if (is_array($tagged)) {
                return $tagged;
            }
        }

        $direct = $store->get($key);

        return is_array($direct) ? $direct : null;
    }

    protected function indexTagsForKey(string $key, array $tags, int $ttl): void
    {
        $store = $this->store();

        foreach ($tags as $tag) {
            $indexKey = self::TAG_INDEX_PREFIX . $tag;
            $existing = $store->get($indexKey, []);
            $keys = is_array($existing) ? $existing : [];
            $keys[] = $key;
            $store->put($indexKey, array_values(array_unique($keys)), max($ttl, 3600));
        }
    }

    protected function extractResponseModelTags(mixed $response): array
    {
        if (!$response instanceof Response) {
            return [];
        }

        if (!config('asasflow-cache.tagging.enabled', true) || !config('asasflow-cache.tagging.auto_tag_models', true)) {
            return [];
        }

        $header = $response->headers->get(ApiResponseFactory::MODEL_TAGS_HEADER);
        if (!$header) {
            return [];
        }

        return array_values(array_unique(array_filter(explode(',', (string) $header))));
    }

    protected function stripInternalHeaders(mixed $response): void
    {
        if ($response instanceof Response) {
            $response->headers->remove(ApiResponseFactory::MODEL_TAGS_HEADER);
        }
    }

    protected function isCacheableResponse($response): bool
    {
        if (!$response instanceof Response) {
            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return false;
        }

        $content = (string) $response->getContent();
        if ($content !== '' && str_starts_with(ltrim($content), '{')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && array_key_exists('success', $decoded) && $decoded['success'] === false) {
                return false;
            }
        }

        return true;
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
        return (bool) config('asasflow-cache.enabled', true);
    }

    protected function store()
    {
        return Cache::store(config('asasflow-cache.store', config('cache.default', 'array')));
    }

    protected function supportsTags(): bool
    {
        if (!config('asasflow-cache.tagging.enabled', true)) {
            return false;
        }

        $repository = $this->store();

        if (method_exists($repository, 'supportsTags')) {
            return $repository->supportsTags();
        }

        return method_exists($repository->getStore(), 'tags');
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
            'redis' => Redis::publish($channel, json_encode($payload)),
            default => Log::info("[AsasFlowCache] Distributed invalidation via {$driver}", $payload),
        };
    }
}
