<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Bitsnio\AsasFlow\Features\Cache\Attributes\AutoCache;
use Bitsnio\AsasFlow\Features\Cache\Attributes\NoCache;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

class AutoCacheMiddleware
{
    protected CacheManager $cacheManager;
    protected CacheKeyGenerator $keyGenerator;

    public function __construct(
        CacheManager $cacheManager,
        CacheKeyGenerator $keyGenerator,
    ) {
        $this->cacheManager = $cacheManager;
        $this->keyGenerator = $keyGenerator;
    }

    public function handle(Request $request, Closure $next, ?string $ttl = null, ?string $tags = null): Response
    {
        if (!config('asasflow-cache.enabled', true)) {
            return $next($request);
        }

        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $routeConfig = $this->getRouteCacheConfig($request);
        if ($routeConfig['skip']) {
            return $next($request);
        }

        $ttl = $routeConfig['ttl'] ?? ($ttl ? (int) $ttl : null);
        $tags = $this->resolveTags($request, $routeConfig['tags'] ?? $tags);

        return $this->cacheManager->remember($request, function () use ($next, $request) {
            return $next($request);
        }, $ttl, $tags);
    }

    protected function getRouteCacheConfig(Request $request): array
    {
        $route = $request->route();

        if (!$route) {
            return ['ttl' => null, 'tags' => null, 'skip' => false];
        }

        $controller = $route->getControllerClass();
        $method = $route->getActionMethod();

        if (!$controller || !$method) {
            return ['ttl' => null, 'tags' => null, 'skip' => false];
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);

            foreach ($reflection->getAttributes(NoCache::class) as $attr) {
                return ['ttl' => null, 'tags' => null, 'skip' => true];
            }

            $classReflection = new ReflectionClass($controller);
            foreach ($classReflection->getAttributes(NoCache::class) as $attr) {
                return ['ttl' => null, 'tags' => null, 'skip' => true];
            }

            foreach ($reflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();
                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                ];
            }

            foreach ($classReflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();
                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                ];
            }
        } catch (\ReflectionException $e) {
            // Fallback to middleware params
        }

        return ['ttl' => null, 'tags' => null, 'skip' => false];
    }

    protected function resolveTags(Request $request, ?string $tags): array
    {
        $result = [];

        if ($routeName = $request->route()?->getName()) {
            $result[] = $this->keyGenerator->generateRouteTag($routeName);
        }

        if ($tags) {
            $result = array_merge($result, explode(',', $tags));
        }

        return array_unique(array_filter($result));
    }
}
