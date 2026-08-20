<?php

namespace Bitsnio\AsasFlow;

use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Console\Commands\Install;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsService;
use Bitsnio\AsasFlow\Core\Repositories\ModuleSettingsRepository;
use Illuminate\Filesystem\Filesystem;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {

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

        $this->mergeConfigFrom(__DIR__ . '/../config/asasflow.php', 'asasflow');

        $this->app->register(\Bitsnio\Modules\LaravelModulesServiceProvider::class);


        $this->app->booting(function () {
            if (config('asasflow.routes.enabled') === true) {
                $this->loadRoutesFrom(__DIR__ . '/../routes/docs.php');
            }
        });
    }

    public function boot()
    {
        /*
         * Discover module settings classes after all module
         * ServiceProviders have had a chance to load their
         * configuration.
         */
        $this->app
            ->make(ModuleSettingsDiscovery::class)
            ->discover();

        $this->loadRoutesFrom(
            __DIR__ . '/routes/settings.php'
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
}
