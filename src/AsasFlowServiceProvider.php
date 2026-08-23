<?php

namespace Bitsnio\AsasFlow;

use Bitsnio\AsasFlow\Features\Cache\CacheServiceProvider;
use Bitsnio\AsasFlow\Features\Settings\SettingsServiceProvider;
use Bitsnio\AsasFlow\Features\Tenancy\TenancyServiceProvider;
use Bitsnio\AsasFlow\Console\ConsoleServiceProvider;
use Illuminate\Support\ServiceProvider;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register feature service providers
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(CacheServiceProvider::class);
        
        // Register tenancy if enabled
        if (config('asasflow.tenancy.enabled', false)) {
            $this->app->register(TenancyServiceProvider::class);
        }

        // Register console commands
        $this->app->register(ConsoleServiceProvider::class);
    }

    public function boot(): void
    {
        // Boot model observers for all modules
        $this->bootModuleObservers();
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
