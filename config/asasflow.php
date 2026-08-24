<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id column) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Central domains (landlord access)
        'central_domains' => explode(',', env('ASASFLOW_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),

        // Routes that skip tenant resolution
        'central_routes' => [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ],

        // Tenant model class
        'tenant_model' => \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

        // Auto-create tenant database on tenant creation
        'auto_create_db' => true,

        // Auto-run tenant migrations
        'auto_migrate' => true,

        // Cache tenant resolution
        'cache_resolution' => true,
        'cache_resolution_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'path' => base_path('Modules'),
        'cache_discovery' => true,
    ],
];
