<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators;

use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleSettingsGenerator
{
    protected Filesystem $filesystem;

    protected $module;

    protected string $moduleName;

    protected ?ComponentFactory $component;

    public function __construct(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ) {
        $this->filesystem = new Filesystem();

        $this->module = $module;

        $this->moduleName = Str::studly($moduleName);

        $this->component = $component;
    }

    /**
     * Generate all settings files for the module.
     */
    public function generate(): void
    {
        $this->generateSettingsClass();

        $this->generateSettingsMigration();

        $this->generateSettingsConfig();

        $this->updateServiceProvider();

        // $this->updateModuleComposer();
    }

    /**
     * Generate Settings class.
     */
    protected function generateSettingsClass(): void
    {
        $path = $this->getSettingsClassPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info(
                "Settings class already exists: {$path}"
            );

            return;
        }

        $this->filesystem->put(
            $path,
            $this->buildClassStub()
        );

        $this->component?->info(
            "Settings class generated: {$path}"
        );
    }

    /**
     * Generate Spatie Settings migration.
     *
     * This migration creates the two standard properties:
     *
     *     {module}.enabled
     *     {module}.values
     */
    protected function generateSettingsMigration(): void
    {
        $path = $this->getSettingsMigrationPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info("Settings migration already exists: {$path}");
            return;
        }

        $this->filesystem->put(
            $path,
            $this->buildMigrationStub()
        );

        $this->component?->info(
            "Settings migration generated: {$path}"
        );
    }

    /**
     * Generate module settings configuration.
     */
    protected function generateSettingsConfig(): void
    {
        $path = $this->getSettingsConfigPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info(
                "Settings config already exists: {$path}"
            );

            return;
        }

        $stub = $this->filesystem->get(
            $this->getStubPath('settings-config.stub')
        );

        $content = str_replace(
            [
                '$CLASS$',
            ],
            [
                $this->getSettingsClass(),
            ],
            $stub
        );

        $this->filesystem->put(
            $path,
            $content
        );

        $this->component?->info(
            "Settings config generated: {$path}"
        );
    }

    /**
     * Update module ServiceProvider.
     *
     * IMPORTANT:
     *
     * We DO NOT register the settings class into
     * Spatie's settings.settings config here.
     *
     * We only load the module's config/settings.php.
     */
    protected function updateServiceProvider(): void
    {
        $providerPath = $this->getServiceProviderPath();

        if (! $this->filesystem->exists($providerPath)) {
            $this->component?->warn(
                "ServiceProvider not found: {$providerPath}"
            );

            return;
        }

        $content = $this->filesystem->get($providerPath);

        $marker = '// [MODULE-SETTINGS-CONFIG-AUTO]';

        if (str_contains($content, $marker)) {
            $this->component?->info(
                'ServiceProvider already configured for settings.'
            );

            return;
        }

        $configExpression =
            "dirname(__DIR__, 2) . '/config/settings.php'";

        $configKey =
            'modules.' .
            $this->getModuleConfigKey() .
            '.settings';

        $registration = <<<PHP
        {$marker}
        \$this->mergeConfigFrom(
            {$configExpression},
            '{$configKey}'
        );
PHP;

        if (
            preg_match(
                '/public function register\(\)[^{]*\{/',
                $content
            )
        ) {
            $content = preg_replace(
                '/(public function register\(\)[^{]*\{)/',
                "$1\n{$registration}",
                $content,
                1
            );
        } else {
            $method = <<<PHP

    public function register(): void
    {
{$registration}
    }

PHP;

            $position = strrpos($content, '}');

            if ($position === false) {
                throw new RuntimeException(
                    "Unable to update ServiceProvider: {$providerPath}"
                );
            }

            $content =
                substr($content, 0, $position) .
                $method .
                substr($content, $position);
        }

        $this->filesystem->put(
            $providerPath,
            $content
        );

        $this->component?->info(
            "ServiceProvider updated: {$providerPath}"
        );
    }

    /**
     * Update module composer.json so the Settings directory is
     * PSR-4 autoloadable.
     */
    protected function updateModuleComposer(): void
    {
        $path = $this->getModuleComposerPath();

        if (! $this->filesystem->exists($path)) {
            $this->component?->warn(
                "Module composer.json not found: {$path}"
            );

            return;
        }

        $json = json_decode(
            $this->filesystem->get($path),
            true
        );

        if (! is_array($json)) {
            throw new RuntimeException(
                "Invalid composer.json: {$path}"
            );
        }

        $prefix =
            $this->getModuleNamespace() .
            '\\Settings\\';

        $json['autoload']['psr-4'][$prefix] = 'Settings/';

        $content = json_encode(
            $json,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
        );

        if ($content === false) {
            throw new RuntimeException(
                "Unable to encode composer.json: {$path}"
            );
        }

        $this->filesystem->put(
            $path,
            $content . PHP_EOL
        );

        $this->component?->info(
            "Module composer.json updated: {$path}"
        );

        $this->component?->warn(
            'Run composer dump-autoload after module generation.'
        );
    }

    /**
     * Settings class stub.
     */
    protected function buildClassStub(): string
    {
        $stub = $this->filesystem->get(
            $this->getStubPath('settings-class.stub')
        );

        return str_replace(
            [
                '$NAMESPACE$',
                '$CLASS$',
                '$MODULE$',
            ],
            [
                $this->getSettingsNamespace(),
                $this->getSettingsClassName(),
                $this->getModuleConfigKey(),
            ],
            $stub
        );
    }

    /**
     * Settings migration stub.
     */
    protected function buildMigrationStub(): string
    {
        $stub = $this->filesystem->get(
            $this->getStubPath('settings-migration.stub')
        );

        return str_replace(
            [
                '$GROUP$',
            ],
            [
                $this->getModuleConfigKey(),
            ],
            $stub
        );
    }

    protected function getStubPath(string $stubName): string
    {
        return dirname(__DIR__)
            . '/Console/Commands/Stubs/'
            . ltrim($stubName, '/');
    }

    protected function getSettingsClassPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) .
            '/Settings/' .
            $this->getSettingsClassName() .
            '.php';
    }

    protected function getSettingsMigrationPath(): string
    {
        return $this->module->getModulePath($this->moduleName)
            . '/database/migrations/'
            . date('Y_m_d_His')
            . '_create_'
            . strtolower($this->moduleName)
            . '_settings_defaults.php';
    }

    protected function getSettingsConfigPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) . '/config/settings.php';
    }

    protected function getServiceProviderPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) .
            '/app/Providers/' .
            $this->moduleName .
            'ServiceProvider.php';
    }

    protected function getModuleComposerPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) . '/composer.json';
    }

    protected function getSettingsClassName(): string
    {
        return $this->moduleName . 'Settings';
    }

    protected function getSettingsNamespace(): string
    {
        $namespace = config(
            'modules.namespace',
            'Modules'
        );

        return $namespace .
            '\\' .
            $this->moduleName .
            '\\Settings';
    }

    protected function getModuleNamespace(): string
    {
        $namespace = config(
            'modules.namespace',
            'Modules'
        );

        return $namespace .
            '\\' .
            $this->moduleName;
    }

    protected function getSettingsClass(): string
    {
        return $this->getSettingsNamespace() .
            '\\' .
            $this->getSettingsClassName();
    }

    protected function getModuleConfigKey(): string
    {
        return Str::kebab($this->moduleName);
    }

    protected function exportPath(string $path): string
    {
        return var_export($path, true);
    }

    protected function ensureDirectory(string $path): void
    {
        if (! $this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory(
                $path,
                0755,
                true
            );
        }
    }
}
