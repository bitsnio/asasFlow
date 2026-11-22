<?php

return [
    'version' => env('ASASFLOW_VERSION', '1.0.0'),

    'routes' => [
        'enabled' => true,
    ],

    'responses' => [
        'render_exceptions' => env('ASASFLOW_RENDER_API_EXCEPTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modular Feature Configurations
    |--------------------------------------------------------------------------
    |
    | Each functional domain is maintained in its own configuration file under
    | config/asasflow/*.php and imported here for simplicity and readability.
    |
    */
    'modules' => require __DIR__ . '/asasflow/modules.php',
    'cache' => require __DIR__ . '/asasflow/cache.php',
    'tenancy' => require __DIR__ . '/asasflow/tenancy.php',
    'generators' => require __DIR__ . '/asasflow/generators.php',
    'dropdown' => require __DIR__ . '/asasflow/dropdown.php',
    'cors' => require __DIR__ . '/asasflow/cors.php',
    'install' => require __DIR__ . '/asasflow/install.php',
];
