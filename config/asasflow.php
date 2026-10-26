<?php

return [
    'version' => env('ASASFLOW_VERSION'),
    "modules" => [
        "enabled" => true,
        "path" => base_path("Modules"),
        "namespace" => "Modules",
        'scaffold' => [

            'app_providers' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Providers',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Providers/{{ MODULE }}ServiceProvider.php',
                    'stub' => 'Module/module-service-provider.stub',
                    'replacements' => [],
                ],
            ],

            'app_commands' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Console/Commands',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_controllers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Controllers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_requests' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Requests',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_resources' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Resources',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_models' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Models',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_observers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Observers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_policies' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Policies',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_rules' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Rules',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_services' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Services',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_actions' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Actions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_dtos' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/DTOs',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_enums' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Enums',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_traits' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Traits',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_events' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Events',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_listeners' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Listeners',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_jobs' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs',
                'stub' => [
                    'enabled' => false,
                    'path' => 'app/Jobs/{{ MODULE }}QueueJob.php',
                    'stub' => 'Module/queue-job.stub',
                    'replacements' => [],
                ],
            ],

            'queue' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs/Queues',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Jobs/Queues/{{ MODULE }}Queue.php',
                    'stub' => 'Module/queue.stub',
                    'replacements' => [],
                ],
            ],

            'app_notifications' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Notifications',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_exceptions' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Exceptions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'config' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/config.php',
                    'stub' => 'Module/config.stub',
                    'replacements' => [],
                ],
            ],

            'menu' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/menu.php',
                    'stub' => 'Module/menu.stub',
                    'replacements' => [],
                ],
            ],

            'settings' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/settings.php',
                    'stub' => 'Module/settings.stub',
                    'replacements' => [],
                ],
            ],

            'migrations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/migrations',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'factories' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/factories',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'seeders' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/seeders',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'translations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'lang/en',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'views' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'resources/views',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/api.php',
                    'stub' => 'Module/api.stub',
                    'replacements' => [],
                ],
            ],
            'custom_routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/custom.php',
                    'stub' => 'Module/rout.stub',
                    'replacements' => [],
                ],
            ],
            'tests_feature' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Feature',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'tests_unit' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Unit',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'manifest' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'module.json',
                    'stub' => 'Module/module-json.stub',
                    'replacements' => [],
                ],
            ],

            'composer' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'composer.json',
                    'stub' => 'Module/composer-json.stub',
                    'replacements' => [],
                ],
            ],

        ],
    ],
    "routes" => [
        "enabled" => true,
    ],
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
    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id/site_id columns) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Multi-site support within a tenant
        'site_enabled' => env('ASASFLOW_TENANCY_SITE_ENABLED', true),

        // Column names injected into single-DB migrations & scopes
        'tenant_column' => 'tenant_id',
        'site_column' => 'site_id',
        'site_nullable' => true,

        // Tables that belong to the central landlord and NEVER get tenant_id or site_id
        'exempt_tables' => [
            'tenants',
            'tenant_domains',
            'migrations',
            'jobs',
            'failed_jobs',
            'personal_access_tokens',
        ],

        // Tables that get tenant_id, but NEVER get site_id
        'site_exempt_tables' => [
            'sites',
        ],

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

        // Tenant model class (Point this to Modules\Core\Models\Tenants::class if using Core module)
        'tenant_model' => \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => env('DB_CONNECTION', 'mysql'),

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
    | ASASFLOW Generator Configuration
    |--------------------------------------------------------------------------
    |
    | Defaults used when generating controllers, requests, and resources.
    | Individual generators decide which configured features apply.
    |
    */
    'generators' => [
        /*
        |--------------------------------------------------------------------------
        | Controller Generator
        |--------------------------------------------------------------------------
        */
        'controller' => [
            /*
         * Additional imports added to generated controllers.
         * Use fully qualified class names, without "use".
         */
            'imports' => [],

            /*
         * Traits imported and applied inside the generated class.
         * Use fully qualified trait names.
         */
            'traits' => [],

            'features' => [
                'pagination' => [
                    'enabled' => true,
                    'per_page' => 15,
                ],

                /*
             * Reserved for the filtering implementation.
             * Enabling this alone does not implement filtering.
             */
                'filtering' => [
                    'enabled' => false,
                ],

                /*
             * Global model scopes are applied by Eloquent automatically.
             * This flag does not attach scopes to a model.
             */
                'scopes' => [
                    'enabled' => false,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Request Generator
    |--------------------------------------------------------------------------
    */
        'request' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_rules' => [
                    'enabled' => true,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Resource Generator
    |--------------------------------------------------------------------------
    */
        'resource' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_fields' => [
                    'enabled' => true,
                ],
            ],
        ],
    ],
    'install' => [
        /*
     * Modules required for a usable AsasFlow installation.
     */
        'required_modules' => [
            'system' => [
                'version' => '^1.0',
                'dev_url' => env('ASASFLOW_SYSTEM_MODULE_SOURCE'),
                'release_url' => env('ASASFLOW_SYSTEM_MODULE_SHA256'),
            ],
        ],
        'jwt' => [
            'user_model' => env(
                'ASASFLOW_JWT_USER_MODEL',
                'Modules\\Core\\Models\\Users'
            ),
            'guard' => 'api',
            'provider' => 'users',
        ],
    ],
];
