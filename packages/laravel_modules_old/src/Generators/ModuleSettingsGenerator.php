<?php

namespace Bitsnio\Modules\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Console\View\Components\Factory as ComponentFactory;

class ModuleSettingsGenerator
{
    protected Filesystem $filesystem;
    protected $module;
    protected string $moduleName;
    protected ?ComponentFactory $component;

    public function __construct($module, string $moduleName, ?ComponentFactory $component = null)
    {
        $this->filesystem = new Filesystem();
        $this->module = $module;
        $this->moduleName = $moduleName;
        $this->component = $component;
    }

    /**
     * Generate all settings files
     */
    public function generate(): void
    {
        $this->generateSettingsClass();
        $this->generateMigration();
        $this->updateServiceProvider();
    }

    /**
     * Generate the settings class
     */
    protected function generateSettingsClass(): void
    {
        $path = $this->getSettingsClassPath();

        if (!$this->filesystem->isDirectory(dirname($path))) {
            $this->filesystem->makeDirectory(dirname($path), 0755, true);
        }

        if (!$this->filesystem->exists($path)) {
            $this->filesystem->put($path, $this->getSettingsStubContents());
            $this->component?->info("Settings class generated: $path");
        } else {
            $this->component?->info("Settings class already exists: $path");
        }
    }

    /**
     * Generate the settings migration
     */
    protected function generateMigration(): void
    {
        $path = $this->getMigrationPath();

        if (!$this->filesystem->isDirectory(dirname($path))) {
            $this->filesystem->makeDirectory(dirname($path), 0755, true);
        }

        if (!$this->filesystem->exists($path)) {
            $this->filesystem->put($path, $this->getMigrationStubContents());
            $this->component?->info("Migration file generated: $path");
        } else {
            $this->component?->info("Migration file already exists: $path");
        }
    }

    /**
     * Get the contents of the settings class stub
     */
    protected function getSettingsStubContents(): string
    {
        $stub = $this->filesystem->get($this->getStubPath('settings-class.stub'));

        return str_replace(
            ['$NAMESPACE$', '$CLASS$', '$MODULE$', '$LOWER_MODULE$', '$STUDLY_MODULE$'],
            [
                $this->getSettingsNamespace(),
                $this->getSettingsClassName(),
                $this->moduleName,
                strtolower($this->moduleName),
                Str::studly($this->moduleName)
            ],
            $stub
        );
    }

    /**
     * Get the contents of the migration stub
     */
    protected function getMigrationStubContents(): string
    {
        $stub = $this->filesystem->get($this->getStubPath('settings-migration.stub'));

        return str_replace(
            ['$CLASS$', '$TABLE$'],
            [
                'Create' . Str::studly($this->moduleName) . 'SettingsTable',
                strtolower($this->moduleName)
            ],
            $stub
        );
    }

    protected function getStubPath(string $stubName): string
    {
        return __DIR__ . '/../commands/stubs/' . ltrim($stubName, '/');
    }

    protected function getSettingsClassPath(): string
    {
        return $this->module->getModulePath($this->moduleName) . '/Settings/' . $this->getSettingsClassName() . '.php';
    }

    protected function getMigrationPath(): string
    {
        return $this->module->getModulePath($this->moduleName) . '/Database/migrations/' .
            date('Y_m_d_His') . '_create_' . strtolower($this->moduleName) . '_settings_table.php';
    }

    protected function getSettingsClassName(): string
    {
        return Str::studly($this->moduleName) . 'Settings';
    }

    protected function getSettingsNamespace(): string
    {
        $namespace = config('modules.namespace', 'Modules');
        return "$namespace\\" . Str::studly($this->moduleName) . "\\Settings";
    }

    protected function updateServiceProvider(): void
    {
        $providerPath = $this->module->getModulePath($this->moduleName) . 'App/Providers/' . $this->moduleName . 'ServiceProvider.php';

        if (!$this->filesystem->exists($providerPath)) {
            $this->component?->error("Service provider not found at: {$providerPath}");
            return;
        }

        $content = $this->filesystem->get($providerPath);
        $settingsClass = $this->getSettingsNamespace() . '\\' . $this->getSettingsClassName();
        $originalContent = $content;

        // 1. Add use statement after namespace if not exists
        if (!str_contains($content, "use {$settingsClass};")) {
            $content = preg_replace(
                '/(namespace .*?;)\s*/',
                "$1\n\nuse {$settingsClass};\n",
                $content,
                1
            );
        }

        // 2. Add registerSettings() if it doesn't exist
        if (!str_contains($content, 'function registerSettings()')) {
            $registerMethod = <<<PHP

                    protected function registerSettings()
                    {
                        \$settings = config('settings.settings', []);
                        if (!in_array(\\{$settingsClass}::class, \$settings)) {
                            \$settings[] = \\{$settingsClass}::class;
                            config(['settings.settings' => \$settings]);
                        }
                    }
                PHP;
            // Insert registerSettings method before final closing brace
            $content = preg_replace('/}\s*$/', "$registerMethod\n\n}", $content);
        }

        // 3. Ensure boot() calls registerSettings()
        if (!str_contains($content, '$this->registerSettings()')) {
            if (str_contains($content, 'public function boot()')) {
                $content = preg_replace(
                    '/(public function boot\(\)[^{]*{)/',
                    "$1\n        \$this->app->booted(function () {\n            \$this->registerSettings();\n        });",
                    $content
                );
            } else {
                    $bootMethod = <<<PHP

                        public function boot()
                        {
                            \$this->app->booted(function () {
                                \$this->registerSettings();
                            });
                        }
                    PHP;
                $content = preg_replace('/}\s*$/', "$bootMethod\n}", $content);
            }
        }

        // Only write if changes were made
        if ($content !== $originalContent) {
            $this->filesystem->put($providerPath, $content);
            $this->component?->info("Service provider successfully updated with settings registration.");
        } else {
            $this->component?->info("Service provider already properly configured for settings.");
        }
    }
}
