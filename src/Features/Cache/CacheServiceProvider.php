<?php

namespace Bitsnio\AsasFlow\Features\Cache;

use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheFlushCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheStatusCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheWarmCommand;
use Bitsnio\AsasFlow\Features\Cache\Facades\ModuleCache;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\CacheHeaders;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\ModuleRouteCache;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheObserverManager;
use Bitsnio\AsasFlow\Features\Cache\Services\ModuleCacheManager;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register cache manager
        $this->app->singleton(ModuleCacheManager::class, function ($app) {
            return new ModuleCacheManager();
        });

        // Register observer manager
        $this->app->singleton(CacheObserverManager::class, function ($app) {
            return new CacheObserverManager($app->make(ModuleCacheManager::class));
        });

        // Register facade accessor
        $this->app->singleton('asasflow.cache', function ($app) {
            return $app->make(ModuleCacheManager::class);
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/asasflow.php',
            'asasflow'
        );
    }

    public function boot(): void
    {
        // Register middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('asasflow.cache', ModuleRouteCache::class);
        $router->aliasMiddleware('asasflow.cache.headers', CacheHeaders::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheFlushCommand::class,
                CacheWarmCommand::class,
                CacheStatusCommand::class,
            ]);
        }

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Publish config
        $this->publishes([
            __DIR__ . '/../../../config/asasflow.php' => config_path('asasflow.php'),
        ], 'asasflow-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../../database/migrations' => database_path('migrations'),
        ], 'asasflow-migrations');
    }
}
