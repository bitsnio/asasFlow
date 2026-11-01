<?php

namespace Bitsnio\AsasFlow\Features\Cache\Observers;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class ModelCacheObserver
{
    protected CacheInvalidator $invalidator;
    protected CacheKeyGenerator $keyGenerator;
    protected array $cacheTags = [];
    protected array $cacheInvalidationRelations = [];

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

        $this->invalidator->invalidate($modelClass, $modelId !== null ? (string) $modelId : null);

        if (!empty($tags)) {
            $this->invalidator->invalidateTags($tags);
        }

        $this->invalidateRelations($model, $event);
    }

    protected function getModelCacheTags(Model $model): array
    {
        $tags = $this->cacheTags;

        if (method_exists($model, 'getCacheTags')) {
            $tags = array_merge($tags, (array) $model->getCacheTags());
        } elseif (property_exists($model, 'cacheTags')) {
            $tags = array_merge($tags, (array) $model->cacheTags);
        }

        return array_values(array_unique(array_filter($tags)));
    }

    protected function invalidateTouchedRelations(Model $model): void
    {
        if (!method_exists($model, 'getTouchedRelations')) {
            return;
        }

        foreach ($model->getTouchedRelations() as $relation) {
            if ($model->relationLoaded($relation) || $model->isDirty($relation)) {
                $related = $model->$relation;
                if ($related instanceof Model) {
                    $this->invalidator->invalidate(get_class($related), (string) $related->getKey());
                }
            }
        }
    }

    protected function invalidateRelations(Model $model, string $event): void
    {
        $relations = $this->cacheInvalidationRelations;

        if (method_exists($model, 'getCacheInvalidationRelations')) {
            $relations = array_replace($relations, (array) $model->getCacheInvalidationRelations());
        }

        foreach ($relations as $relation => $config) {
            if ($event === 'updated' && !empty($config['on_update'])) {
                $this->invalidateRelation($model, (string) $relation);
            }

            if ($event === 'deleted' && !empty($config['on_delete'])) {
                $this->invalidateRelation($model, (string) $relation);
            }
        }
    }

    protected function invalidateRelation(Model $model, string $relation): void
    {
        if (!$model->relationLoaded($relation) && !$model->isDirty($relation)) {
            return;
        }

        $related = $model->$relation;

        if ($related instanceof Model) {
            $this->invalidator->invalidate(get_class($related), (string) $related->getKey());
        } elseif ($related instanceof EloquentCollection) {
            foreach ($related as $item) {
                if ($item instanceof Model) {
                    $this->invalidator->invalidate(get_class($item), (string) $item->getKey());
                }
            }
        }
    }
}
