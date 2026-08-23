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