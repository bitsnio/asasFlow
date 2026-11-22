<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Automatic AsasFlow CORS
    |--------------------------------------------------------------------------
    |
    | When enabled, AsasFlow automatically configures Laravel's HandleCors
    | middleware for all package endpoints (/schema, /dropdown, /settings)
    | and all discovered module route prefixes (/core/*, /vms/*, /crm/*, etc.).
    |
    */
    'enabled' => env('ASASFLOW_CORS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-Discover Module Route Prefixes
    |--------------------------------------------------------------------------
    |
    | Automatically appends '<module>' and '<module>/*' to Laravel's cors.paths
    | for every discovered module so newly created modules work from browser
    | frontends immediately without manual CORS configuration.
    |
    */
    'auto_discover_module_paths' => env('ASASFLOW_CORS_AUTO_MODULE_PATHS', true),

    /*
    |--------------------------------------------------------------------------
    | Base CORS Paths
    |--------------------------------------------------------------------------
    */
    'paths' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ASASFLOW_CORS_PATHS',
            'api/*,sanctum/csrf-cookie,schema,schema/*,dropdown/*,settings,settings/*,asasflow/*'
        ))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Methods, Origins & Headers
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ASASFLOW_CORS_ALLOWED_METHODS', '*'))
    ))),

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ASASFLOW_CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ASASFLOW_CORS_ALLOWED_HEADERS', '*'))
    ))),

    'exposed_headers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ASASFLOW_CORS_EXPOSED_HEADERS',
            'X-AsasFlow-Cache,X-AsasFlow-Cache-Key,ETag'
        ))
    ))),

    'max_age' => (int) env('ASASFLOW_CORS_MAX_AGE', 0),

    'supports_credentials' => (bool) env('ASASFLOW_CORS_SUPPORTS_CREDENTIALS', false),
];
