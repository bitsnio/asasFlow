<?php

namespace Bitsnio\AsasFlow;

use Illuminate\Support\ServiceProvider;
use Bitsnio\AsasFlow\Console\Commands\Install;

class AsasFlowServiceProvider extends ServiceProvider
{
    public function register()
    {
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
