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
        $loaded = require $settingsPath;

        if (
            is_array($loaded)
            && array_key_exists('settings', $loaded)
            && is_array($loaded['settings'])
            && !array_key_exists('default', $loaded['settings'])
            && !array_key_exists('scope', $loaded['settings'])
        ) {
            $loaded = $loaded['settings'];
        }

        config()->set(
            $configKey . '.settings',
            is_array($loaded) ? $loaded : []
        );

        $this->registry->register(
            $module,
            $configKey
        );
    }

    protected function getModulesPath(): string
    {
        return (string) config(
            'asasflow.modules.path',
            config(
                'modules.paths.modules',
                base_path('Modules')
            )
        );
    }

    /**
     * Get raw settings definitions for a module.
     */
    public function settings(string $module): array
    {
        if (! $this->registry->has($module)) {
            $this->discover();
        }

        $configKey = $this->registry->configKey($module);

        $settings = config($configKey . '.settings', []);

        if (
            is_array($settings)
            && array_key_exists('settings', $settings)
            && is_array($settings['settings'])
            && !array_key_exists('default', $settings['settings'])
            && !array_key_exists('scope', $settings['settings'])
        ) {
            return $settings['settings'];
        }

        return is_array($settings) ? $settings : [];
    }
}