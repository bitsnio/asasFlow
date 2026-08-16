<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Services;

use Bitsnio\AsasFlow\Core\Settings\ModuleSettings;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ModuleSettingsService
{
    public function __construct(
        protected ModuleSettingsRegistry $registry,
    ) {}

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        return array_keys($this->registry->all());
    }

    /**
     * @return class-string<ModuleSettings>
     */
    public function settingsClass(string $module): string
    {
        $class = $this->registry->getClass($module);

        if (! $class) {
            throw new InvalidArgumentException(
                "No settings registered for module [{$module}]."
            );
        }

        return $class;
    }

    public function settings(string $module): ModuleSettings
    {
        $class = $this->settingsClass($module);

        /** @var ModuleSettings $settings */
        $settings = app($class);

        return $settings;
    }

    /**
     * Get module definitions.
     */
    public function definitions(string $module): array
    {
        $class = $this->settingsClass($module);

        $configPath = $this->configPath($class);

        if (! is_file($configPath)) {
            return [];
        }

        $config = require $configPath;

        return $config['settings'] ?? [];
    }

    /**
     * Get defaults defined in config/settings.php.
     */
    public function defaults(string $module): array
    {
        $definitions = $this->definitions($module);

        $defaults = [];

        foreach ($definitions as $key => $definition) {

            if (
                is_array($definition)
                && array_key_exists('default', $definition)
            ) {
                $defaults[$key] = $definition['default'];
            }
        }

        return $defaults;
    }

    /**
     * Return complete frontend-friendly representation.
     */
    public function get(string $module): array
    {
        $settings = $this->settings($module);

        return [
            'module' => $module,
            'class' => $this->settingsClass($module),

            'settings' => $this->buildSettings(
                $module,
                $settings->values
            ),
        ];
    }

    /**
     * Build dynamic values response.
     */
    /**
     * Build complete settings response.
     */
    protected function buildSettings(
        string $module,
        array $currentValues
    ): array {
        $definitions = $this->definitions($module);

        $result = [];

        foreach ($definitions as $key => $definition) {

            $default = $definition['default'] ?? null;

            $value = array_key_exists(
                $key,
                $currentValues
            )
                ? $currentValues[$key]
                : $default;

            $result[$key] = [
                'key' => $key,

                'label' =>
                $definition['label'] ?? $key,

                'description' =>
                $definition['description'] ?? null,

                'type' =>
                $definition['type'] ?? 'string',

                'input' =>
                $definition['input'] ?? 'text',

                'value' => $value,

                'default' => $default,

                'options' =>
                $definition['options'] ?? null,

                'rules' =>
                $definition['rules'] ?? [],
            ];
        }

        return $result;
    }
    /**
     * Update module settings.
     */
    public function update(
        string $module,
        array $data
    ): ModuleSettings {
        $settings = $this->settings($module);

        $definitions = $this->definitions($module);

        if (array_key_exists('values', $data)) {

            $values = $data['values'];

            /*
         * Only settings declared in config/settings.php
         * can be updated.
         */
            $values = array_intersect_key(
                $values,
                $definitions
            );

            $settings->values = array_replace(
                $settings->values,
                $values
            );
        }

        $settings->save();

        return $settings;
    }

    /**
     * Set one dynamic setting.
     */
    public function set(
        string $module,
        string $key,
        mixed $value
    ): ModuleSettings {
        return $this->update(
            $module,
            [
                'values' => [
                    $key => $value,
                ],
            ]
        );
    }

    /**
     * Get module config file path from settings class.
     */
    protected function configPath(string $class): string
    {
        $reflection = new \ReflectionClass($class);

        /*
         * Settings class:
         *
         * Modules/Admin/Settings/AdminSettings.php
         *
         * Config:
         *
         * Modules/Admin/config/settings.php
         */
        return dirname(
            dirname($reflection->getFileName())
        ) . '/config/settings.php';
    }
}
