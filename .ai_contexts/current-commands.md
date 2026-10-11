# Laravel Project Context

**Task:** Current commands




## Project Files

### config/asasflow.php

```php
<?php

return [

    'modules' => [
        'path' => base_path('Modules'),
        'namespace' => 'Modules',
        'cache_discovery' => true,
        'structure' => [
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Http/Resources',
            'app/Models',
            'app/Providers',
            'app/Services',
            'app/Actions',
            'app/DTOs',
            'app/Enums',
            'app/Events',
            'app/Listeners',
            'app/Jobs',
            'app/Policies',
            'app/Rules',
            'app/Traits',
            'app/Exceptions',
            'config',
            'database/migrations',
            'database/factories',
            'database/seeders',
            'lang/en',
            'resources/views',
            'routes',
            'tests/Feature',
            'tests/Unit',
        ],
        'composer' => [
            'author' => 'Bitsnio',
            'email' => 'haseeb.khan@bitsnio.com',
        ],
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
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id column) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

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

        // Tenant model class
        'tenant_model' => \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

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

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'path' => base_path('Modules'),
        'cache_discovery' => true,
    ],
];

```

### src/Foundation/Support/ModuleScaffolder.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

final class ModuleScaffolder
{
    public function __construct(
        private readonly StubRenderer $stubs,
        private readonly FileHandler $files,
    ) {}

    public function createDirectories(
        ModuleDefinition $module
    ): void {
        File::ensureDirectoryExists($module->path);

        foreach ((array) config('asasflow.modules.structure', []) as $directory) {

            $directory = trim((string) $directory, '/\\');

            if ($directory === '') {
                continue;
            }

            $path = $module->path
                . DIRECTORY_SEPARATOR
                . str_replace(
                    ['/', '\\'],
                    DIRECTORY_SEPARATOR,
                    $directory
                );

            File::ensureDirectoryExists($path);
        }
    }

    /**
     * @return array<int, array{path: string, action: string}>
     */
    public function createFiles(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        $this->createDirectories($module);

        $replacements = $this->replacements($module);

        $files = [
            'module-service-provider.stub' => $module->appPath(
                'Providers/' . $module->name . 'ServiceProvider.php'
            ),

            'config.stub' => $module->configPath() . '/config.php',

            'menu.stub' => $module->configPath() . '/menu.php',

            'settings.stub' => $module->configPath() . '/settings.php',

            'api.stub' => $module->routesPath() . '/api.php',

            'module-json.stub' => $module->path . '/module.json',

            'composer-json.stub' => $module->path . '/composer.json',
        ];

        $results = [];

        foreach ($files as $stub => $path) {
            if ($this->files->exists($path) && ! $force) {
                $results[] = [
                    'path' => $path,
                    'action' => 'skipped-existing',
                ];

                continue;
            }

            $content = $this->stubs->renderFile(
                'Module/' . $stub,
                $replacements,
            );

            $this->files->writeFile(
                $path,
                $content,
                force: $force,
            );

            $results[] = [
                'path' => $path,
                'action' => 'generated',
            ];
        }

        return $results;
    }

    private function replacements(
        ModuleDefinition $module
    ): array {
        $name = $module->name;
        $lower = Str::kebab($name);
        $title = Str::headline($name);

        return [
            'MODULE' => $name,
            'MODULE_LOWER' => $lower,
            'MODULE_TITLE' => $title,
            'MODULE_NAMESPACE' => $module->namespace,
            'MODULE_COMPOSER_NAMESPACE' => str_replace(
                '\\',
                '\\\\',
                $module->namespace . '\\',
            ),
            'MODULE_PROVIDER' => $module->providerClass(),
            'AUTHOR_NAME' => (string) config(
                'asasflow.modules.composer.author',
                'Bitsnio',
            ),
            'AUTHOR_EMAIL' => (string) config(
                'asasflow.modules.composer.email',
                '',
            ),
            'MODULE_NAMESPACE_JSON' => str_replace(
                '\\',
                '\\\\',
                $module->namespace,
            ),
            'MODULE_PROVIDER_JSON' => str_replace(
                '\\',
                '\\\\',
                $module->providerClass(),
            ),
        ];
    }
}

```

### src/Generators/Module/ModuleGenerator.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleScaffolder;
use InvalidArgumentException;

final class ModuleGenerator
{
    public function __construct(
        private readonly ModuleScaffolder $scaffolder,
    ) {}

    /**
     * @return array<int, array{path: string, action: string}>
     */
    public function generate(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        if (! preg_match(
            '/^[A-Za-z][A-Za-z0-9_]*$/',
            $module->name,
        )) {
            throw new InvalidArgumentException(
                "Invalid module name [{$module->name}]."
            );
        }

        return $this->scaffolder->createFiles(
            $module,
            $force,
        );
    }
}
```

### src/Generators/Model/ModelGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Model;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;

class ModelGenerator
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    public function generate(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        $path =
            $this->files->getModelPath(
                $module,
                $definition->modelClass()
            );

        if (!$this->files->exists($path)) {
            $this->files->writeFile(
                $path,
                $this->content(
                    $module,
                    $definition,
                    $schema
                ),
                true
            );

            return [
                'action' => 'created',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (!GeneratedBlock::has(
            $document,
            'generated-fillable'
        )) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-fillable',
            $this->buildFillable($schema)
        );

        $this->files->updateGeneratedBlock(
            $path,
            'generated-casts',
            $this->buildCasts($schema)
        );

        return [
            'action' => 'updated',
            'path' => $path,
        ];
    }

    protected function content(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): string {
        $namespace = "Modules\\{$module->getName()}\\Models";

        return <<<PHP
                <?php

                namespace {$namespace};

                use Illuminate\Database\Eloquent\Model;
                use Illuminate\Database\Eloquent\SoftDeletes;

                class {$definition->modelClass()} extends Model
                {
                    use SoftDeletes;

                    protected \$fillable = [
                        // @asasflow:generated-fillable:start
                        // @asasflow:generated-fillable:end
                    ];

                    protected \$casts = [
                        // @asasflow:generated-casts:start
                        // @asasflow:generated-casts:end
                    ];
                }

            PHP;
    }

    protected function buildFillable(
        SchemaDefinition $schema
    ): string {
        $lines = [];

        foreach (array_keys($schema->properties()) as $field) {
            $lines[] = '        '. var_export( $field, true ). ',';
        }

        return implode( PHP_EOL, $lines);
    }

    protected function buildCasts(
        SchemaDefinition $schema
    ): string {
        $casts = $schema->casts();

        $lines = [];

        foreach ($casts as $field => $cast) {
            $lines[] =
                '        '
                . var_export(
                    $field,
                    true
                )
                . ' => '
                . var_export(
                    $cast,
                    true
                )
                . ',';
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }
}

```

### src/Console/Commands/Module/MakeModuleCommand.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Module\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class MakeModuleCommand extends Command
{
    protected $signature = 'asasflow:make-module
                            {name : Name of the module to create}
                            {--force : Replace existing generated files}';

    protected $description = 'Create an API-focused AsasFlow module.';

    public function handle(ModuleGenerator $generator): int
    {
        try {
            $input = trim((string) $this->argument('name'));

            if (
                $input === ''
                || str_contains($input, '/')
                || str_contains($input, '\\')
                || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $input)
            ) {
                throw new InvalidArgumentException(
                    "Invalid module name [{$input}]."
                );
            }

            $name = Str::studly($input);

            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException(
                    "Invalid normalized module name [{$name}]."
                );
            }

            $modulesPath = rtrim(
                (string) config(
                    'asasflow.modules.path',
                    base_path('Modules'),
                ),
                '/\\',
            );

            $namespace = trim(
                (string) config('asasflow.modules.namespace', 'Modules'),
                '\\',
            );

            $path = $modulesPath . DIRECTORY_SEPARATOR . $name;

            if (is_dir($path) && ! $this->option('force')) {
                $this->error(
                    "Module [{$name}] already exists. "
                    . 'Use --force only if you intend to replace generated files.'
                );

                return self::FAILURE;
            }

            $module = new ModuleDefinition(
                name: $name,
                path: $path,
                namespace: $namespace . '\\' . $name,
            );

            $results = $generator->generate(
                $module,
                (bool) $this->option('force'),
            );

            $this->newLine();
            $this->info("Module [{$name}] scaffolding completed.");

            foreach ($results as $result) {
                $action = $result['action'] === 'generated'
                    ? 'Created'
                    : 'Skipped';

                $this->line(
                    "  {$action}: {$result['path']}"
                );
            }

            $this->newLine();
            $this->comment(
                'Review the generated module manifest and run '
                . '`composer dump-autoload` in the host project.'
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
```

### src/AsasFlowServiceProvider.php

```php
<?php

namespace Bitsnio\AsasFlow;

use Bitsnio\AsasFlow\Features\Cache\CacheServiceProvider;
use Bitsnio\AsasFlow\Features\Settings\SettingsServiceProvider;
use Bitsnio\AsasFlow\Features\Tenancy\TenancyServiceProvider;
use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\ModuleRepository;

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register feature service providers
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);
        // $this->app->register(\Bitsnio\Modules\LaravelModulesServiceProvider::class);
        // Register tenancy if enabled
        if (config('asasflow.tenancy.enabled', false)) {
            $this->app->register(TenancyServiceProvider::class);
        }

        $this->app->booting(function () {
            if (config('asasflow.routes.enabled') === true) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/docs.php');
            }
        });

        $this->app->bind(
            ModuleRepositoryInterface::class,
            ModuleRepository::class,
        );

        $this->mergeConfigFrom(__DIR__ . '/../config/asasflow.php', 'asasflow');
    }

    public function boot(): void
    {
        // Boot model observers for all modules
        $this->bootModuleObservers();


        // $this->app
        //     ->make(ModuleSettingsDiscovery::class)
        //     ->discover();

        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );

        // Publishing + commands (console only)
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../config/asasflow.php' => \config_path('asasflow.php'),
            ], 'asasflow-config');

            $this->commands(
                \Bitsnio\AsasFlow\Console\ConsoleServiceProvider::commands()->toArray()
            );
        }
    }

    protected function bootModuleObservers(): void
    {
        if (!config('asasflow.cache.enabled', true)) {
            return;
        }

        $modulesPath = config('asasflow.modules.path', base_path('Modules'));

        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);
            $observerPath = $modulePath . '/Observers';

            if (!is_dir($observerPath)) {
                continue;
            }

            foreach (glob($observerPath . '/*CacheObserver.php') as $observerFile) {
                $observerClass = $this->resolveObserverClass($module, basename($observerFile, '.php'));
                $modelClass = $this->resolveModelClass($module, $observerFile);

                if ($observerClass && $modelClass && class_exists($modelClass)) {
                    $modelClass::observe($observerClass);
                }
            }
        }
    }

    protected function resolveObserverClass(string $module, string $observerName): ?string
    {
        $class = "Modules\\{$module}\\Observers\\{$observerName}";
        return class_exists($class) ? $class : null;
    }

    protected function resolveModelClass(string $module, string $observerFile): ?string
    {
        // Extract model name from observer filename: UserCacheObserver -> User
        $observerName = basename($observerFile, '.php');
        $modelName = str_replace('CacheObserver', '', $observerName);

        $class = "Modules\\{$module}\\Models\\{$modelName}";
        return class_exists($class) ? $class : null;
    }
}

```

