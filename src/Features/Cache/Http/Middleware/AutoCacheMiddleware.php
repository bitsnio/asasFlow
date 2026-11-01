<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Bitsnio\AsasFlow\Features\Cache\Attributes\AutoCache;
use Bitsnio\AsasFlow\Features\Cache\Attributes\NoCache;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
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

        $resolvedTtl = $routeConfig['ttl'] ?? ($ttl !== null && $ttl !== '' ? (int) $ttl : null);
        $resolvedTags = $this->resolveTags($request, $routeConfig['tags'] ?? $tags);

        $options = [
            'ignore_query_params' => $routeConfig['ignore_query_params'] ?? false,
        ];

        if (array_key_exists('stampede_protection', $routeConfig) && $routeConfig['stampede_protection'] !== null) {
            $options['stampede_protection'] = $routeConfig['stampede_protection'];
        }

        return $this->cacheManager->remember(
            $request,
            function () use ($next, $request) {
                return $next($request);
            },
            $resolvedTtl,
            $resolvedTags,
            $options,
        );
    }

    protected function getRouteCacheConfig(Request $request): array
    {
        $default = [
            'ttl' => null,
            'tags' => null,
            'skip' => false,
            'ignore_query_params' => false,
            'stampede_protection' => null,
        ];

        $route = $request->route();

        if (!$route) {
            return $default;
        }

        $controller = $route->getControllerClass();
        $method = $route->getActionMethod();

        if (!$controller || !$method) {
            return $default;
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);

            if ($reflection->getAttributes(NoCache::class) !== []) {
                return array_merge($default, ['skip' => true]);
            }

            $classReflection = new ReflectionClass($controller);
            if ($classReflection->getAttributes(NoCache::class) !== []) {
                return array_merge($default, ['skip' => true]);
            }

            foreach ($reflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();

                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                    'ignore_query_params' => $instance->ignoreQueryParams,
                    'stampede_protection' => $instance->stampedeProtection,
                ];
            }

            foreach ($classReflection->getAttributes(AutoCache::class) as $attr) {
                $instance = $attr->newInstance();

                return [
                    'ttl' => $instance->ttl,
                    'tags' => $instance->tags,
                    'skip' => false,
                    'ignore_query_params' => $instance->ignoreQueryParams,
                    'stampede_protection' => $instance->stampedeProtection,
                ];
            }
        } catch (ReflectionException) {
            // Fallback to middleware params
        }

        return $default;
    }

    protected function resolveTags(Request $request, ?string $tags): array
    {
        $result = [];

        if ($routeName = $request->route()?->getName()) {
            $result[] = $this->keyGenerator->generateRouteTag($routeName);
        }

        if ($tags) {
            $result = array_merge($result, array_map('trim', explode(',', $tags)));
        }

        return array_values(array_unique(array_filter($result)));
    }
}
