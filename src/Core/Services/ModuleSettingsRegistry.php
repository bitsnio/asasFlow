<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Services;

class ModuleSettingsRegistry
{
    protected array $modules = [];

    public function register(
        string $module,
        string $configKey
    ): void {
        $this->modules[strtolower($module)] = $configKey;
    }

    public function configKey(string $module): string
    {
        $module = strtolower($module);

        if (!isset($this->modules[$module])) {
            throw new \InvalidArgumentException(
                "Settings for module [{$module}] are not registered."
            );
        }

        return $this->modules[$module];
    }

    public function has(string $module): bool
    {
        return isset(
            $this->modules[strtolower($module)]
        );
    }

    public function all(): array
    {
        return $this->modules;
    }
}