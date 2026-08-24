<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
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
