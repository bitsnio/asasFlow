<?php

namespace Bitsnio\AsasFlow\Features\Cache\Traits;

use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;

trait CacheAware
{
    public static function bootCacheAware(): void
    {
        static::created(ModelCacheObserver::class . '@created');
        static::updated(ModelCacheObserver::class . '@updated');
        static::deleted(ModelCacheObserver::class . '@deleted');

        if (method_exists(static::class, 'restored')) {
            static::restored(ModelCacheObserver::class . '@restored');
        }

        if (method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted(ModelCacheObserver::class . '@forceDeleted');
        }
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
