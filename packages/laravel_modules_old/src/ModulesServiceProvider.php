<?php

namespace Bitsnio\Modules;

use Bitsnio\Modules\Providers\MenuServiceProvider;
use Illuminate\Support\ServiceProvider;
use Bitsnio\Modules\Providers\BootstrapServiceProvider;
use Bitsnio\Modules\Providers\ConsoleServiceProvider;
use Bitsnio\Modules\Providers\ContractsServiceProvider;
use Bitsnio\Modules\Providers\PermissionServiceProvider;


abstract class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Booting the package.
     */
    public function boot()
    {
    }

    /**
     * Register all modules.
     */
    public function register()
    {
    }

    /**
     * Register all modules.
     */
    protected function registerModules()
    {
        $this->app->register(BootstrapServiceProvider::class);
    }

    /**
     * Register package's namespaces.
     */
    protected function registerNamespaces()
    {
        $configPath = __DIR__ . '/../config/config.php';
        $stubsPath = dirname(__DIR__) . '/src/Commands/stubs';

        $this->publishes([
            $configPath => config_path('modules.php'),
        ], 'config');

        $this->publishes([
            $stubsPath => base_path('stubs/bitsnio-stubs'),
        ], 'stubs');

        $this->publishes([
            __DIR__.'/../scripts/vite-module-loader.js' => base_path('vite-module-loader.js'),
        ], 'vite');
    }

    /**
     * Register the service provider.
     */
    abstract protected function registerServices();

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Contracts\RepositoryInterface::class, 'modules','modules.menu'];
    }

    /**
     * Register providers.
     */
    protected function registerProviders()
    {
        $this->app->register(ConsoleServiceProvider::class);
        $this->app->register(ContractsServiceProvider::class);
        $this->app->register(MenuServiceProvider::class);
        $this->app->register(PermissionServiceProvider::class);
    }
}
