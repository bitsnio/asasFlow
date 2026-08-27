<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Toggle
    |--------------------------------------------------------------------------
    | Use Laravel's default cache.enabled or override via env.
    */
    'enabled' => env('ASASFLOW_CACHE_ENABLED', env('CACHE_ENABLED', true)),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | Uses Laravel's cache.default config. Override via env if needed.
    | Supported: apc, array, database, file, memcached, redis, dynamodb, octane
    */
    'store' => env('ASASFLOW_CACHE_STORE', env('CACHE_STORE', config('cache.default', 'redis'))),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'ttl' => env('ASASFLOW_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Strategy
    |--------------------------------------------------------------------------
    */
    'key_strategy' => [
        'driver' => 'url_context',
        'include_query_params' => true,
        'ignore_params' => [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'tracking_id',
            '_ga',
            'fbclid',
        ],
        'include_headers' => [
            'X-Tenant-ID',
            'Accept-Language',
        ],
        'include_user' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tagging
    |--------------------------------------------------------------------------
    */
    'tagging' => [
        'enabled' => true,
        'auto_tag_models' => true,
        'service_prefix' => env('APP_NAME', 'asasflow-service'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stampede Protection
    |--------------------------------------------------------------------------
    */
    'stampede_protection' => [
        'enabled' => true,
        'lock_ttl' => 10,
        'stale_while_revalidate' => true,
        'stale_ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Control Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'enabled' => true,
        'etag' => true,
        'last_modified' => true,
        'cache_control' => 'public, max-age=300',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass Cache
    |--------------------------------------------------------------------------
    */
    'bypass' => [
        'enabled' => env('ASASFLOW_CACHE_BYPASS_ENABLED', false),
        'header' => 'X-Bypass-Cache',
        'api_key' => env('ASASFLOW_CACHE_BYPASS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Events (Microservices)
    |--------------------------------------------------------------------------
    */
    'distributed' => [
        'enabled' => env('ASASFLOW_CACHE_DISTRIBUTED_ENABLED', false),
        'driver' => env('ASASFLOW_CACHE_DISTRIBUTED_DRIVER', 'redis'),
        'channel' => 'asasflow-cache-invalidation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => env('ASASFLOW_CACHE_DASHBOARD_ENABLED', true),
        'route_prefix' => '_cache',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */
    'telemetry' => [
        'enabled' => true,
        'events' => true,
    ],
];
