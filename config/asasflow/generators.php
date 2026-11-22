<?php

return [
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
        'traits' => [
            \Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait::class,
        ],

        'features' => [
            'pagination' => [
                'enabled' => true,
                'per_page' => 15,
                'max_per_page' => 100,
                'allow_unpaginated' => false,
                'max_unpaginated' => 500,
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
        'traits' => [
            \Bitsnio\AsasFlow\Foundation\Http\Traits\HandlesApiValidation::class,
        ],

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
];
