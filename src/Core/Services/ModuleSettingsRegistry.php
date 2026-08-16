<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Services;

use InvalidArgumentException;

class ModuleSettingsRegistry
{
    /**
     * @var array<string, class-string>
     */
    protected array $classes = [];

    public function register(
        string $module,
        string $settingsClass
    ): void {
        if (
            isset($this->classes[$module])
            && $this->classes[$module] !== $settingsClass
        ) {
            throw new InvalidArgumentException(
                "Settings are already registered for module [{$module}]."
            );
        }

        $this->classes[$module] = $settingsClass;
    }

    public function getClass(string $module): ?string
    {
        return $this->classes[$module] ?? null;
    }

    public function has(string $module): bool
    {
        return isset($this->classes[$module]);
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->classes;
    }

    public function clear(): void
    {
        $this->classes = [];
    }
}