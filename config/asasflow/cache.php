<?php

return [
    // Enable/disable caching
    'enabled' => env('ASASFLOW_CACHE_ENABLED', true),

    // Cache store to use (from cache.php stores)
    'store' => env('ASASFLOW_CACHE_STORE', config('cache.default', 'redis')),

    // Key prefix for all asasflow cache keys
    'prefix' => env('ASASFLOW_CACHE_PREFIX', 'asasflow'),

    // Default TTL in seconds
    'default_ttl' => env('ASASFLOW_CACHE_TTL', 3600),

    // Strict tenant isolation (prefix all keys with tenant ID)
    'strict_isolation' => env('ASASFLOW_CACHE_STRICT_ISOLATION', true),

    // Cache authenticated requests
    'cache_authenticated' => env('ASASFLOW_CACHE_AUTH', false),

    // Use queue for cache warming
    'warm_via_queue' => env('ASASFLOW_CACHE_QUEUE', true),

    // Maintain key registry for non-tag drivers (file/database)
    'fallback_registry' => true,

    // Add debug headers to responses
    'debug_headers' => env('ASASFLOW_CACHE_DEBUG', false),

    // HTTP cache headers max-age
    'http_max_age' => env('ASASFLOW_CACHE_HTTP_MAX_AGE', 0),
];
