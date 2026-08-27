<?php

namespace Bitsnio\AsasFlow\Features\Cache\Contracts;

interface CacheInvalidationStrategy
{
    public function invalidate(string $modelClass, ?string $modelId = null): void;
    public function invalidateTags(array $tags): void;
}
