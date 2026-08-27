<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheControlMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!config('asasflow-cache.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            config('asasflow-cache.headers.cache_control', 'public, max-age=300')
        );

        $response->headers->set(
            'X-Cache-Service',
            config('asasflow-cache.tagging.service_prefix', 'asasflow-service')
        );

        if ($request->hasHeader('X-Correlation-ID')) {
            $response->headers->set(
                'X-Correlation-ID',
                $request->header('X-Correlation-ID')
            );
        }

        return $response;
    }
}
