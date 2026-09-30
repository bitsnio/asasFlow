<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Support\Str;
use RuntimeException;

class MenuGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate menu configuration file.
     */
    public function generate(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): void {
        $path = $module->getPath()
            . '/config/menu.php';

        try {
            $content = $this->getStubContents(
                $moduleName
            );

            $this->files->writeFile(
                $path,
                $content,
                false
            );

            $component?->info(
                "Generated menu config at: {$path}"
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'MenuGenerator failed: '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get rendered menu stub.
     */
    protected function getStubContents(
        string $moduleName
    ): string {
        return $this->stubs->renderFile(
            'Menu.stub',
            [
                'MODULE_NAME' => $moduleName,
                'TITLE' => Str::headline(
                    $moduleName
                ),
            ]
        );
    }
}