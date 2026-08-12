<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Package Settings
    |--------------------------------------------------------------------------
    |
    | Basic configuration options for the AsasFlow package environment.
    |
    */
    'installed' => false,
    'debug'     => env('ASASFLOW_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Routing Configurations
    |--------------------------------------------------------------------------
    |
    | Control how the routing behaves for your package APIs and assets.
    |
    */
   'routes' => [
        'enabled'       => false,
        'redirect_root' => false, // Toggled on if host app documentation is empty
        'prefix'        => 'docs/asasflow',
        'middleware'    => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation (LaRecipe Integration)
    |--------------------------------------------------------------------------
    |
    | Define custom paths and properties for rendering markdown files.
    |
    */
    'docs' => [
        'default_version' => '1.0',
        'default_page'    => 'overview',
        'storage_path'    => 'resources/docs/bitsnio/asasflow',
    ],

];