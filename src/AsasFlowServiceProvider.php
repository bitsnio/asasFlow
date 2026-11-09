<?php

namespace Bitsnio\AsasFlow;

use Bitsnio\AsasFlow\Features\Cache\CacheServiceProvider;
use Bitsnio\AsasFlow\Features\Settings\SettingsServiceProvider;
use Bitsnio\AsasFlow\Features\Tenancy\TenancyServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Bitsnio\AsasFlow\Foundation\Http\Dropdown\DropdownService;
use Bitsnio\AsasFlow\Foundation\Http\Exceptions\ApiExceptionRenderer;
use Bitsnio\AsasFlow\Foundation\Http\Pagination\PaginationResolver;
use Bitsnio\AsasFlow\Foundation\Models\Permission as AsasPermissionModel;
use Bitsnio\AsasFlow\Foundation\Models\Role as AsasRoleModel;
use Bitsnio\AsasFlow\Foundation\Support\MenuService;
use Bitsnio\AsasFlow\Foundation\Support\ModuleRegistrar;
use Bitsnio\AsasFlow\Foundation\Support\ModuleRepository;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDiscovery;
use Bitsnio\AsasFlow\Foundation\Support\PermissionService;

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
       
        // $this->app->register(\Bitsnio\Modules\LaravelModulesServiceProvider::class);
       

        $this->mergeConfigFrom(__DIR__ . '/../config/asasflow.php', 'asasflow');

        config([
            'permission.models.role' => AsasRoleModel::class,
            'permission.models.permission' => AsasPermissionModel::class,
        ]);

         // Register feature service providers
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);

        $this->app->bind(
            ModuleRepositoryInterface::class,
            ModuleRepository::class,
        );

        $this->app->singleton(ModuleDiscovery::class);
        $this->app->singleton(ModuleRegistrar::class);

        $this->app->singleton(MenuService::class);
        $this->app->alias(MenuService::class, 'asasflow.menu');

        $this->app->singleton(PermissionService::class);
        $this->app->alias(PermissionService::class, 'asasflow.permission');

        $this->app->singleton(PaginationResolver::class);
        $this->app->singleton(ApiResponseFactory::class);
        $this->app->alias(ApiResponseFactory::class, 'asasflow.response');
        $this->app->singleton(DropdownService::class);
        $this->app->alias(DropdownService::class, 'asasflow.dropdown');
        $this->app->singleton(ApiExceptionRenderer::class);

        $this->app->afterResolving(ExceptionHandler::class, function (ExceptionHandler $handler, $app): void {
            $app->make(ApiExceptionRenderer::class)->register($handler);
        });

         // Register tenancy if enabled
        if (config('asasflow.tenancy.enabled', false)) {
            $this->app->register(TenancyServiceProvider::class);
        }


        $this->app->booting(function () {
            if (config('asasflow.routes.enabled') === true) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/docs.php');
                $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
            }
        });
    }

    public function boot(): void
    {
        $this->registerResponseMacros();

        if ($this->app->resolved(ExceptionHandler::class)) {
            $this->app->make(ApiExceptionRenderer::class)->register(
                $this->app->make(ExceptionHandler::class)
            );
        }

        // Register module entry-point providers through the new mechanism.
        $this->app->make(ModuleRegistrar::class)->register();
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

    protected function registerResponseMacros(): void
    {
        ResponseFactory::macro('success', function (
            mixed $data = null,
            string $message = 'Success',
            int $status = 200,
            array $headers = [],
            array $meta = [],
        ) {
            return app(ApiResponseFactory::class)->success(
                $data,
                $message,
                $status,
                $headers,
                $meta,
            );
        });

        ResponseFactory::macro('created', function (
            mixed $data = null,
            string $message = 'Created successfully',
            array $headers = [],
            array $meta = [],
        ) {
            return app(ApiResponseFactory::class)->created(
                $data,
                $message,
                $headers,
                $meta,
            );
        });

        ResponseFactory::macro('paginated', function (
            mixed $items,
            string $message = 'Success',
            int $status = 200,
            array $headers = [],
            array $meta = [],
        ) {
            return app(ApiResponseFactory::class)->paginated(
                $items,
                $message,
                $status,
                $headers,
                $meta,
            );
        });

        ResponseFactory::macro('error', function (
            string $message = 'Error',
            int $status = 400,
            mixed $errors = null,
            array $headers = [],
        ) {
            return app(ApiResponseFactory::class)->error(
                $message,
                $status,
                $errors,
                $headers,
            );
        });
    }
}
