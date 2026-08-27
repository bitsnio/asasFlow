<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Name Constraints
    |--------------------------------------------------------------------------
    */
    'route_constraints' => [
        'max_length' => 64,
        'max_name_length' => 60,
        'max_nesting_level' => 3,
        'enable_truncation' => true,
        'enable_hash_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller Generation
    |--------------------------------------------------------------------------
    */
    'controllers' => [
        'use_attributes' => true,
        'api_version' => 'v1',
        'middleware' => ['api', 'auth'],
        'methods' => ['index', 'store', 'show', 'update', 'destroy'],
        'suffix' => 'Controller',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Generation
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'file' => 'Routes/api.php',
        'group_by_middleware' => true,
        'use_attributes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Detection
    |--------------------------------------------------------------------------
    */
    'detection' => [
        'check_content_hash' => true,
        'check_timestamps' => true,
        'check_exists' => true,
    ],
];
