<?php

namespace Bitsnio\AsasFlow\Features\Cache;

use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheClearCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheStatsCommand;
use Bitsnio\AsasFlow\Features\Cache\Console\Commands\CacheWarmCommand;
use Bitsnio\AsasFlow\Features\Cache\Facades\MicroCache;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\AutoCacheMiddleware;
use Bitsnio\AsasFlow\Features\Cache\Http\Middleware\CacheControlMiddleware;
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheResponseSerializer;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheStampedeProtector;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheStatsCollector;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheKeyGenerator::class);
        $this->app->singleton(CacheResponseSerializer::class);
        $this->app->singleton(CacheStampedeProtector::class);
        $this->app->singleton(CacheStatsCollector::class);

        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(CacheKeyGenerator::class),
                $app->make(CacheResponseSerializer::class),
                $app->make(CacheStampedeProtector::class),
                $app->make(CacheStatsCollector::class),
            );
        });

        $this->app->singleton(CacheInvalidator::class, function ($app) {
            return new CacheInvalidator(
                $app->make(CacheKeyGenerator::class),
                $app->make(CacheManager::class),
            );
        });

        $this->app->singleton('asasflow.cache', function ($app) {
            return $app->make(CacheManager::class);
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/asasflow-cache.php',
            'asasflow-cache'
        );
    }

    public function boot(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('asasflow.cache', AutoCacheMiddleware::class);
        $router->aliasMiddleware('asasflow.cache.control', CacheControlMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheClearCommand::class,
                CacheStatsCommand::class,
                CacheWarmCommand::class,
            ]);
        }

        if (config('asasflow-cache.dashboard.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/dashboard.php');
        }

        $this->publishes([
            __DIR__ . '/../../../config/asasflow-cache.php' => config_path('asasflow-cache.php'),
        ], 'asasflow-cache-config');
    }
}
