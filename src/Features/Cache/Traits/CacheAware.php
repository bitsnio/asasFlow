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
