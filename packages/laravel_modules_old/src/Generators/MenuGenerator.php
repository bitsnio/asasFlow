<?php

namespace Bitsnio\Modules\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Console\View\Components\Factory as ComponentFactory;

class MenuGenerator
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
     * Generate menu configuration file
     */
    public function generate(): void
    {
        $path = $this->module->getModulePath($this->moduleName) . '/Config/menu.php';

        try {
            if (!$this->filesystem->isDirectory(dirname($path))) {
                $this->filesystem->makeDirectory(dirname($path), 0755, true);
            }

            $contents = $this->getStubContents();
            $this->filesystem->put($path, $contents);

            $this->component?->info("Generated menu config at: $path");
        } catch (\Throwable $e) {
            $this->component?->error("Failed to generate menu config: " . $e->getMessage());
        }
    }

    /**
     * Get menu stub contents
     */
    protected function getStubContents(): string
    {
        return $this->replaceStubPlaceholders(
            $this->filesystem->get(__DIR__ . '/../commands/stubs/menu.stub')
        );
    }

    /**
     * Replace stub placeholders
     */
    protected function replaceStubPlaceholders(string $stub): string
    {
        return str_replace(
            ['$MODULE_NAME$', '$LOWER_NAME$', '$STUDLY_NAME$'],
            [$this->moduleName, strtolower($this->moduleName), Str::studly($this->moduleName)],
            $stub
        );
    }
}
