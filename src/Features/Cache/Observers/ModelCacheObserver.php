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
