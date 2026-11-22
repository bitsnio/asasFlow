<?php

return [
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
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'migrations',
        'jobs',
        'failed_jobs',
        'personal_access_tokens',
    ],

    // Tables that get tenant_id, but NEVER get site_id
    'site_exempt_tables' => [
        'sites',
        'roles',
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
];
