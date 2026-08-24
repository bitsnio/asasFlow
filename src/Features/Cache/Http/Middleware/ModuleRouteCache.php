<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleRouteCache
{
    protected ModuleCacheManager $cacheManager;

    public function __construct(ModuleCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Handle incoming request with cache.
     */
    public function handle(Request $request, Closure $next, string $tags = '', ?int $ttl = null): Response
    {
        // Skip caching for non-GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Skip if cache disabled
        if (!config('asasflow.cache.enabled', true)) {
            return $next($request);
        }

        // Skip for authenticated requests if configured
        if ($request->user() && !config('asasflow.cache.cache_authenticated', false)) {
            return $next($request);
        }

        $cacheKey = $this->resolveCacheKey($request);
        $tagsArray = array_filter(explode(',', $tags));
        
        // Auto-inject module tag
        $module = $this->resolveModule($request);
        if ($module) {
            $tagsArray[] = "module:{$module}";
        }

        // Auto-inject tenant tag
        if (TenantContext::isTenantInitialized()) {
            $tagsArray[] = 'tenant:' . TenantContext::getTenantId();
        }

        return $this->cacheManager->rememberRoute(
            $cacheKey,
            $tagsArray,
            fn() => $next($request),
            $ttl
        );
    }

    /**
     * Resolve cache key from request.
     */
    protected function resolveCacheKey(Request $request): string
    {
        $tenantPrefix = TenantContext::isTenantInitialized() 
            ? TenantContext::getTenantSlug() . ':' 
            : '';

        return sprintf(
            '%sroute:%s:%s',
            $tenantPrefix,
            $request->route()->getName() ?? $request->path(),
            md5($request->fullUrl() . serialize($request->all()))
        );
    }

    /**
     * Resolve module name from route.
     */
    protected function resolveModule(Request $request): ?string
    {
        $routeName = $request->route()->getName();
        
        if (!$routeName) {
            return null;
        }

        // Extract module from route name: module.users.index -> users
        if (str_starts_with($routeName, 'module.')) {
            $parts = explode('.', $routeName);
            return $parts[1] ?? null;
        }

        // Check route action for module
        $action = $request->route()->getAction();
        return $action['module'] ?? null;
    }
}
