<?php

return [
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
];
