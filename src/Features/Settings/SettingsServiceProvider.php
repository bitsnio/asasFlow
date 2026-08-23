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
