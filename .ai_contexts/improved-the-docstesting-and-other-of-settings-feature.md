# Laravel Project Context

**Task:** improved the docs,testing and other of settings feature

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
.
config
database
database/migrations
packages
packages/laravel-modules
packages/laravel-modules/.ai
packages/laravel-modules/.ai/laravel-modules
packages/laravel-modules/.ai/laravel-modules/1
packages/laravel-modules/.ai/laravel-modules/1/skill
packages/laravel-modules/.ai/laravel-modules/1/skill/laravel-modules-development
packages/laravel-modules/.github
packages/laravel-modules/.github/ISSUE_TEMPLATE
packages/laravel-modules/.github/workflows
packages/laravel-modules/config
packages/laravel-modules/scripts
packages/laravel-modules/src
packages/laravel-modules/src/Activators
packages/laravel-modules/src/Commands
packages/laravel-modules/src/Commands/Actions
packages/laravel-modules/src/Commands/Database
packages/laravel-modules/src/Commands/Make
packages/laravel-modules/src/Commands/Publish
packages/laravel-modules/src/Constants
packages/laravel-modules/src/Contracts
packages/laravel-modules/src/Exceptions
packages/laravel-modules/src/Facades
packages/laravel-modules/src/Generators
packages/laravel-modules/src/Laravel
packages/laravel-modules/src/Lumen
packages/laravel-modules/src/Migrations
packages/laravel-modules/src/Process
packages/laravel-modules/src/Providers
packages/laravel-modules/src/Publishing
packages/laravel-modules/src/Routing
packages/laravel-modules/src/Support
packages/laravel-modules/src/Support/Config
packages/laravel-modules/src/Support/Migrations
packages/laravel-modules/src/Traits
routes
scripts
src
src/Console
src/Console/Commands
src/Console/Commands/ControllerCommands
src/Console/Commands/ControllerCommands/Contracts
src/Console/Commands/ControllerCommands/Services
src/Console/Commands/ControllerCommands/Services/Parsers
src/Console/Commands/ModuleCommands
src/Console/Commands/Stubs
src/Console/Commands/Traits
src/Features
src/Features/Cache
src/Features/Cache/Attributes
src/Features/Cache/Console
src/Features/Cache/Console/Commands
src/Features/Cache/Console/Stubs
src/Features/Cache/Contracts
src/Features/Cache/Events
src/Features/Cache/Facades
src/Features/Cache/Http
src/Features/Cache/Http/Controllers
src/Features/Cache/Http/Middleware
src/Features/Cache/Jobs
src/Features/Cache/Observers
src/Features/Cache/Services
src/Features/Cache/Traits
src/Features/Cache/routes
src/Features/ControllerGeneration
src/Features/ControllerGeneration/Commands
src/Features/ControllerGeneration/Contracts
src/Features/ControllerGeneration/Generators
src/Features/ControllerGeneration/Generators/Controller
src/Features/ControllerGeneration/Generators/Route
src/Features/ControllerGeneration/Generators/Stub
src/Features/ControllerGeneration/Parsers
src/Features/ControllerGeneration/Parsers/Extractors
src/Features/ControllerGeneration/Parsers/Validators
src/Features/ControllerGeneration/Services
src/Features/ControllerGeneration/Stubs
src/Features/ControllerGeneration/Traits
src/Features/ControllerGeneration/config
src/Features/ControllerGeneration/routes
src/Features/Settings
src/Features/Settings/Facades
src/Features/Settings/Http
src/Features/Settings/Http/Controllers
src/Features/Settings/Http/Requests
src/Features/Settings/Models
src/Features/Settings/Repositories
src/Features/Settings/Services
src/Features/Settings/routes
src/Features/Tenancy
src/Features/Tenancy/Contracts
src/Features/Tenancy/Http
src/Features/Tenancy/Http/Middleware
src/Features/Tenancy/Models
src/Features/Tenancy/Services
src/Foundation
src/Foundation/Contracts
src/Foundation/Exceptions
src/Foundation/Support
src/Generators
src/Generators/Cache
src/Generators/Controller
src/routes
```

---

## Project Files

### routes/docs.php

```php
<?php

use Illuminate\Support\Facades\Route;
use BinaryTorch\LaRecipe\Http\Controllers\DocumentationController;

$prefix     = config('asasflow.routes.prefix', 'docs/asasflow');
$middleware = config('asasflow.routes.middleware', ['web']);
$version    = config('asasflow.docs.default_version', '1.0');
$page       = config('asasflow.docs.default_page', 'overview');

Route::prefix($prefix)->middleware($middleware)->group(function () use ($version, $page, $prefix) {

    Route::redirect('/', "/{$prefix}/{$version}/{$page}")
        ->name('asasflow.docs.index');

    Route::get('/{routeVersion}/{routePage?}', function ($routeVersion, $routePage = null) use ($page, $prefix) {
        $routePage = $routePage ?? $page;

        // DocumentationRepository hardcodes larecipe.* config values at
        // construction time for internal links (docsRoute, defaultVersionUrl).
        // We must override BEFORE the controller/repository is constructed,
        // and restore AFTER the response is built.
        //
        // The four keys LaRecipe reads at construction + render time:
        //   larecipe.docs.path    → where to find markdown files
        //   larecipe.docs.route   → base URL for internal links (the "docs route")
        //   larecipe.docs.landing → default landing page
        //   larecipe.versions.default → default version for canonical links

        $origPath    = config('larecipe.docs.path');
        $origRoute   = config('larecipe.docs.route');
        $origLanding = config('larecipe.docs.landing');
        $origVersion = config('larecipe.versions.default');

        config([
            'larecipe.docs.path'        => '/resources/docs/asasflow',
            'larecipe.docs.route'       => "/{$prefix}",
            'larecipe.docs.landing'     => config('asasflow.docs.default_page', 'overview'),
            'larecipe.versions.default' => config('asasflow.docs.default_version', '1.0'),
        ]);

        try {
            // Resolve fresh — controller + repository read config at construction,
            // so we must NOT use a cached instance from the container.
            $response = app()->make(DocumentationController::class)->show($routeVersion, $routePage);
        } finally {
            config([
                'larecipe.docs.path'        => $origPath,
                'larecipe.docs.route'       => $origRoute,
                'larecipe.docs.landing'     => $origLanding,
                'larecipe.versions.default' => $origVersion,
            ]);
        }

        return $response;
    })->where('routePage', '.*')->name('asasflow.docs.show');
});

```

### src/Features/Settings/Facades/ModuleSettings.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(
 *     string $module,
 *     string $key,
 *     mixed $default = null,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array all(
 *     string $module,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array update(
 *     string $module,
 *     array $values,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 *
 * @method static array schema(
 *     string $module,
 *     ?int $companyId = null,
 *     ?int $siteId = null
 * )
 */
class ModuleSettings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module-settings';
    }
}
```

### src/Features/Settings/Http/Controllers/ModuleSettingsController.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Http\Controllers;

use Bitsnio\AsasFlow\Features\Settings\Http\Requests\UpdateModuleSettingsRequest;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ModuleSettingsController extends Controller
{
    public function __construct(
        protected ModuleSettingsService $settings,
        protected ModuleSettingsRegistry $registry,
    ) {
    }

    /**
     * Get all registered modules that have settings.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'modules' => array_keys(
                $this->registry->all()
            ),
        ]);
    }

    /**
     * Get settings schema and resolved values for one module.
     */
    public function show(
        string $module
    ): JsonResponse {
        return response()->json([
            'module' => $module,

            'settings' => $this->settings->schema(
                $module
            ),
        ]);
    }

    /**
     * Update settings for one module.
     */
    public function update(
        UpdateModuleSettingsRequest $request,
        string $module
    ): JsonResponse {

        $companyId = $request->input('company_id');

        $siteId = $request->input('site_id');

        $values = $request->input(
            'settings',
            []
        );

        $settings = $this->settings->update(
            $module,
            $values,
            $companyId,
            $siteId
        );

        return response()->json([
            'message' => 'Settings updated successfully.',

            'module' => $module,

            'settings' => $settings,
        ]);
    }
}
```

### src/Features/Settings/Http/Requests/UpdateModuleSettingsRequest.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
            ],

            'company_id' => [
                'nullable',
                'integer',
            ],

            'site_id' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
```

### src/Features/Settings/Models/ModuleSetting.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleSetting extends Model
{
    protected $table = 'module_settings';

    protected $fillable = [
        'module',
        'company_id',
        'site_id',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scope Helpers
    |--------------------------------------------------------------------------
    */

    public function isModuleLevel(): bool
    {
        return $this->company_id === null;
    }

    public function isCompanyLevel(): bool
    {
        return $this->company_id !== null && $this->site_id === null;
    }

    public function isSiteLevel(): bool
    {
        return $this->company_id !== null && $this->site_id !== null;
    }
}
```

### src/Features/Settings/Repositories/ModuleSettingsRepository.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Repositories;

use Bitsnio\AsasFlow\Features\Settings\Models\ModuleSetting;

class ModuleSettingsRepository
{
    public function find(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): ?ModuleSetting {
        return ModuleSetting::query()
            ->where('module', $module)
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function values(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        return $this->find(
            $module,
            $companyId,
            $siteId
        )?->values ?? [];
    }

    public function save(
        string $module,
        array $values,
        ?int $companyId = null,
        ?int $siteId = null
    ): ModuleSetting {
        return ModuleSetting::query()->updateOrCreate(
            [
                'module' => $module,
                'company_id' => $companyId,
                'site_id' => $siteId,
            ],
            [
                'values' => $values,
            ]
        );
    }
}

```

### src/Features/Settings/Services/ModuleSettingsDiscovery.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Services;

use Illuminate\Filesystem\Filesystem;

class ModuleSettingsDiscovery
{
    public function __construct(
        protected ModuleSettingsRegistry $registry,
        protected Filesystem $filesystem,
    ) {
    }

    /**
     * Discover settings definitions from all modules.
     */
    public function discover(): void
    {
        $this->registry->clear();

        $modulesPath = $this->getModulesPath();

        if (! $this->filesystem->isDirectory($modulesPath)) {
            return;
        }

        foreach ($this->filesystem->directories($modulesPath) as $modulePath) {
            $this->discoverModule($modulePath);
        }
    }

    /**
     * Discover one module's settings definition.
     */
    protected function discoverModule(string $modulePath): void
    {
        $moduleName = basename($modulePath);

        $settingsPath = $modulePath . '/config/settings.php';

        if (! $this->filesystem->exists($settingsPath)) {
            return;
        }

        $module = strtolower($moduleName);

        /*
         * Register settings in Laravel's runtime config.
         *
         * Example:
         *
         * inventory.settings
         * admin.settings
         */
        $configKey = $module;

        config()->set(
            $configKey . '.settings',
            require $settingsPath
        );

        $this->registry->register(
            $module,
            $configKey
        );
    }

    protected function getModulesPath(): string
    {
        return config(
            'modules.paths.modules',
            base_path('Modules')
        );
    }

    /**
     * Get raw settings definitions for a module.
     */
    public function settings(string $module): array
    {
        $configKey = $this->registry->configKey($module);

        return config($configKey . '.settings', []);
    }
}
```

### src/Features/Settings/Services/ModuleSettingsRegistry.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Services;

class ModuleSettingsRegistry
{
    protected array $modules = [];

    public function register(
        string $module,
        string $configKey
    ): void {
        $this->modules[strtolower($module)] = $configKey;
    }

    public function clear(): void
    {
        $this->modules = [];
    }

    public function configKey(string $module): string
    {
        $module = strtolower($module);

        if (! isset($this->modules[$module])) {
            throw new \InvalidArgumentException(
                "Settings for module [{$module}] are not registered."
            );
        }

        return $this->modules[$module];
    }

    public function has(string $module): bool
    {
        return isset($this->modules[strtolower($module)]);
    }

    public function all(): array
    {
        return $this->modules;
    }
}
```

### src/Features/Settings/Services/ModuleSettingsService.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Services;

use Bitsnio\AsasFlow\Features\Settings\Repositories\ModuleSettingsRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ModuleSettingsService
{
    public function __construct(
        protected ModuleSettingsDiscovery $discovery,
        protected ModuleSettingsRepository $repository,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Get one setting
    |--------------------------------------------------------------------------
    */

    public function get(
        string $module,
        string $key,
        mixed $default = null,
        ?int $companyId = null,
        ?int $siteId = null
    ): mixed {
        $settings = $this->all(
            $module,
            $companyId,
            $siteId
        );

        return data_get(
            $settings,
            $key,
            $default
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get all effective settings
    |--------------------------------------------------------------------------
    */

    public function all(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $cacheKey = $this->cacheKey(
            $module,
            $companyId,
            $siteId
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours(24),
            fn () => $this->resolve(
                $module,
                $companyId,
                $siteId
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve effective values
    |--------------------------------------------------------------------------
    */

    protected function resolve(
        string $module,
        ?int $companyId,
        ?int $siteId
    ): array {
        $definitions = $this->definitions($module);

        $moduleValues = $this->repository->values(
            $module
        );

        $companyValues = [];

        if ($companyId !== null) {
            $companyValues = $this->repository->values(
                $module,
                $companyId
            );
        }

        $siteValues = [];

        if ($companyId !== null && $siteId !== null) {
            $siteValues = $this->repository->values(
                $module,
                $companyId,
                $siteId
            );
        }

        $result = [];

        foreach ($definitions as $key => $definition) {

            $definition = $this->normalizeDefinition(
                $key,
                $definition
            );

            $scope = $definition['scope'];

            /*
             * Module-level:
             *
             * module DB override
             * otherwise default
             */
            if ($scope === 'module') {

                $result[$key] = array_key_exists(
                    $key,
                    $moduleValues
                )
                    ? $moduleValues[$key]
                    : $definition['default'];

                continue;
            }

            /*
             * Company-level:
             *
             * company override
             * otherwise module override
             * otherwise default
             */
            if ($scope === 'company') {

                if (array_key_exists($key, $companyValues)) {
                    $result[$key] = $companyValues[$key];

                } elseif (array_key_exists($key, $moduleValues)) {
                    $result[$key] = $moduleValues[$key];

                } else {
                    $result[$key] = $definition['default'];
                }

                continue;
            }

            /*
             * Site-level:
             *
             * site override
             * company override
             * module override
             * default
             */
            if ($scope === 'site') {

                if (array_key_exists($key, $siteValues)) {
                    $result[$key] = $siteValues[$key];

                } elseif (array_key_exists($key, $companyValues)) {
                    $result[$key] = $companyValues[$key];

                } elseif (array_key_exists($key, $moduleValues)) {
                    $result[$key] = $moduleValues[$key];

                } else {
                    $result[$key] = $definition['default'];
                }
            }
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Update settings
    |--------------------------------------------------------------------------
    */

    public function update(
        string $module,
        array $values,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $definitions = $this->definitions($module);

        $existing = $this->repository->values(
            $module,
            $companyId,
            $siteId
        );

        foreach ($values as $key => $value) {

            if (!array_key_exists($key, $definitions)) {
                throw new InvalidArgumentException(
                    "Unknown setting [{$module}.{$key}]."
                );
            }

            $definition = $this->normalizeDefinition(
                $key,
                $definitions[$key]
            );

            $this->ensureScopeMatches(
                $key,
                $definition['scope'],
                $companyId,
                $siteId
            );

            $existing[$key] = $value;
        }

        $this->repository->save(
            $module,
            $existing,
            $companyId,
            $siteId
        );

        $this->forget(
            $module,
            $companyId,
            $siteId
        );

        return $this->all(
            $module,
            $companyId,
            $siteId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get definitions
    |--------------------------------------------------------------------------
    */

    public function definitions(string $module): array
    {
        return $this->discovery->settings($module);
    }

    /*
    |--------------------------------------------------------------------------
    | Frontend-friendly response
    |--------------------------------------------------------------------------
    */

    public function schema(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $definitions = $this->definitions($module);

        $values = $this->all(
            $module,
            $companyId,
            $siteId
        );

        $result = [];

        foreach ($definitions as $key => $definition) {

            $definition = $this->normalizeDefinition(
                $key,
                $definition
            );

            $result[$key] = array_merge(
                $definition,
                [
                    'key' => $key,
                    'value' => $values[$key] ?? null,
                ]
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize definition
    |--------------------------------------------------------------------------
    */

    protected function normalizeDefinition(
        string $key,
        mixed $definition
    ): array {
        /*
         * Simplest possible form:
         *
         * 'foo' => 'bar'
         *
         * becomes:
         *
         * [
         *     'default' => 'bar',
         *     'scope' => 'module',
         * ]
         */
        if (!is_array($definition)) {
            return [
                'default' => $definition,
                'scope' => 'module',
            ];
        }

        return array_merge(
            [
                'label' => $key,
                'description' => null,
                'type' => null,
                'input' => null,
                'default' => null,
                'options' => [],
                'rules' => [],
                'scope' => 'module',
            ],
            $definition
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate scope
    |--------------------------------------------------------------------------
    */

    protected function ensureScopeMatches(
        string $key,
        string $scope,
        ?int $companyId,
        ?int $siteId
    ): void {
        if ($scope === 'module') {

            if ($companyId !== null || $siteId !== null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] is module-level and cannot be overridden per company/site."
                );
            }

            return;
        }

        if ($scope === 'company') {

            if ($companyId === null || $siteId !== null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] requires a company scope."
                );
            }

            return;
        }

        if ($scope === 'site') {

            if ($companyId === null || $siteId === null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] requires a site scope."
                );
            }

            return;
        }

        throw new InvalidArgumentException(
            "Invalid scope [{$scope}] for setting [{$key}]."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    protected function cacheKey(
        string $module,
        ?int $companyId,
        ?int $siteId
    ): string {
        return sprintf(
            'module-settings:%s:%s:%s',
            $module,
            $companyId ?? 'global',
            $siteId ?? 'global'
        );
    }

    public function forget(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): void {
        Cache::forget(
            $this->cacheKey(
                $module,
                $companyId,
                $siteId
            )
        );
    }
}
```

### src/Features/Settings/SettingsServiceProvider.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings;

use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Features\Settings\Repositories\ModuleSettingsRepository;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Stateful registry.
         *
         * Must be singleton because discovery populates it
         * during application boot.
         */
        $this->app->singleton(
            ModuleSettingsRegistry::class
        );

        $this->app->singleton(
            ModuleSettingsDiscovery::class
        );

        $this->app->singleton(
            ModuleSettingsRepository::class
        );

        $this->app->singleton(
            ModuleSettingsService::class
        );

        $this->app->alias(
            ModuleSettingsService::class,
            'module-settings'
        );
    }

    public function boot(): void
    {
        /*
         * Discover settings definitions from installed modules.
         */
        $this->app
            ->make(ModuleSettingsDiscovery::class)
            ->discover();

        /*
         * Settings API routes.
         */
        $this->loadRoutesFrom(
            __DIR__ . '/routes/api.php'
        );
    }
}

```

### src/Features/Settings/routes/api.php

```php
<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Http\Controllers\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')
    ->middleware(['auth:api'])
    ->group(function () {

        Route::get('/', [
            ModuleSettingsController::class,
            'index',
        ])->name('settings.index');

        Route::get('/{module}', [
            ModuleSettingsController::class,
            'show',
        ])->name('settings.show');

        Route::put('/{module}', [
            ModuleSettingsController::class,
            'update',
        ])->name('settings.update');

    });
```

