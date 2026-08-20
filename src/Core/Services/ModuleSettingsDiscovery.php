<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Services;

use Bitsnio\AsasFlow\Core\Settings\ModuleSettings;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsRegistry;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ModuleSettingsDiscovery
{
    public function __construct(
        protected ModuleSettingsRegistry $registry,
        protected Filesystem $filesystem,
    ) {
    }

    /**
     * Discover module settings classes.
     */
    public function discover(): void
    {
        $this->registry->clear();

        $modulesPath = $this->getModulesPath();

        if (! $this->filesystem->isDirectory($modulesPath)) {
            return;
        }

        foreach (
            $this->filesystem->directories($modulesPath)
            as $modulePath
        ) {
            $this->discoverModule($modulePath);
        }
    }

    protected function discoverModule(string $modulePath): void
    {
        $moduleName = basename($modulePath);

        $configPath = $modulePath . '/config/settings.php';

        if (! $this->filesystem->exists($configPath)) {
            return;
        }

        $config = require $configPath;

        $settingsClass = $config['class'] ?? null;

        if (! is_string($settingsClass)) {
            return;
        }

        if (! class_exists($settingsClass)) {
            throw new RuntimeException(
                "Settings class [{$settingsClass}] for module "
                . "[{$moduleName}] could not be autoloaded."
            );
        }

        if (
            ! is_subclass_of(
                $settingsClass,
                ModuleSettings::class
            )
        ) {
            throw new RuntimeException(
                "Settings class [{$settingsClass}] must extend "
                . ModuleSettings::class
                . '.'
            );
        }

        $this->registry->register(
            $settingsClass::module(),
            $settingsClass
        );
    }

    protected function getModulesPath(): string
    {
        return config(
            'modules.paths.modules',
            base_path('Modules')
        );
    }

     public function settings(string $module): array
    {
        $configKey = $this->registry->configKey($module);

        return config($configKey . '.settings', []);
    }
}