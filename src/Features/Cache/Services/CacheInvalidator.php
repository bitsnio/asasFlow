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
