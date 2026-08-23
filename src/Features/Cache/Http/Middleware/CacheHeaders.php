<?php

namespace AsasFlow\Features\Cache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheHeaders
{
    /**
     * Add cache headers to response.
     */
    public function handle(Request $request, Closure $next, string $visibility = 'private', ?int $maxAge = null): Response
    {
        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        $maxAge ??= config('asasflow.cache.http_max_age', 0);

        if ($visibility === 'public') {
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}");
        } else {
            $response->headers->set('Cache-Control', "private, max-age={$maxAge}");
        }

        // Add cache tags header for debugging (if enabled)
        if (config('asasflow.cache.debug_headers', false) && app()->isLocal()) {
            $response->headers->set('X-ASASFLOW-Cache', 'HIT');
        }

        return $response;
    }
}
