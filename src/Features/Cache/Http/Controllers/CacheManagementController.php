<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Controllers;

use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CacheManagementController
{
    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Flush all cache.
     */
    public function flush(Request $request): JsonResponse
    {
        $this->cacheManager->flushAll();

        return response()->json([
            'success' => true,
            'message' => 'All cache flushed successfully',
        ]);
    }

    /**
     * Flush module cache.
     */
    public function flushModule(Request $request, string $module): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        
        $this->cacheManager->invalidateModule($module, $tenantId);

        return response()->json([
            'success' => true,
            'message' => "Cache flushed for module: {$module}",
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Warm module cache.
     */
    public function warmModule(Request $request, string $module): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        
        $this->cacheManager->warmModuleCache($module, $tenantId);

        return response()->json([
            'success' => true,
            'message' => "Cache warming initiated for module: {$module}",
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get cache status.
     */
    public function status(Request $request): JsonResponse
    {
        $store = config('asasflow.cache.store', config('cache.default'));
        $driver = config("cache.stores.{$store}.driver", 'file');

        return response()->json([
            'enabled' => config('asasflow.cache.enabled', true),
            'store' => $store,
            'driver' => $driver,
            'supports_tags' => $this->cacheManager->supportsTags(),
            'strict_isolation' => config('asasflow.cache.strict_isolation', true),
            'current_tenant' => TenantContext::getTenantSlug(),
            'prefix' => config('asasflow.cache.prefix', 'asasflow'),
        ]);
    }
}
