<?php

return [
    'default_limit' => 500,
    'max_limit' => 1000,
    'cache' => [
        'enabled' => env('ASASFLOW_DROPDOWN_CACHE', true),
        'ttl' => env('ASASFLOW_DROPDOWN_CACHE_TTL', 300),
    ],
    'table_aliases' => [
        'tenants' => 'asasflow_tenants',
        'tenant_domains' => 'asasflow_tenant_domains',
        'module_settings' => 'module_settings',
    ],
    'protected_tables' => [
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
        'failed_jobs',
        'jobs',
        'job_batches',
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'asasflow_cache_entries',
    ],
    'protected_columns' => [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],
];
