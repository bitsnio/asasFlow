<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleSettingsGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate module settings configuration.
     *
     * Generates:
     *
     *     config/settings.php
     */
    public function generate(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): array {
        $moduleName = Str::studly($moduleName);

        try {
            return $this->generateSettingsConfig(
                $module,
                $moduleName,
                $component
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'ModuleSettingsGenerator failed: '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Generate module settings configuration.
     */
    protected function generateSettingsConfig(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): array {
        $path = $this->getSettingsConfigPath($module);

        if ($this->files->exists($path)) {
            $component?->info(
                "Settings config already exists: {$path}"
            );

            return [
                'action' => 'skipped-existing',
                'path' => $path,
            ];
        }

        $content = $this->stubs->renderFile(
            'Module/settings-config.stub',
            [
                'MODULE' => $moduleName,
                'KEY' => $this->getModuleConfigKey($moduleName),
            ]
        );

        $this->files->writeFile(
            $path,
            $content
        );

        $component?->info(
            "Settings config generated: {$path}"
        );

        return [
            'action' => 'generated',
            'path' => $path,
        ];
    }

    /**
     * Get module settings config path.
     */
    protected function getSettingsConfigPath(
        $module
    ): string {
        return $module->getPath()
            . '/config/settings.php';
    }

    /**
     * Get module config key.
     */
    protected function getModuleConfigKey(
        string $moduleName
    ): string {
        return Str::kebab($moduleName);
    }
}