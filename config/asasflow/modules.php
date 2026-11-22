<?php

return [
    'enabled' => true,
    'path' => base_path('Modules'),
    'namespace' => 'Modules',
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
                'stub' => 'Module/routes-custom.stub',
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
];
