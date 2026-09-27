<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHandler
{
    public function exists(string $path): bool
    {
        return File::exists($path);
    }

    public function read(string $path): string
    {
        return File::get($path);
    }

    public function writeFile(
        string $path,
        string $content,
        bool $force = false
    ): void {
        if (!$force && $this->exists($path)) {
            return;
        }

        $this->ensureDirectoryExists(dirname($path));

        File::put($path, $content);
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    public function findPhpFiles(string $path): array
    {
        $files = [];

        if (!$this->exists($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile() &&
                $file->getExtension() === 'php'
            ) {
                $relative = str_replace(
                    $path . DIRECTORY_SEPARATOR,
                    '',
                    $file->getPathname()
                );

                $relative = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    $relative
                );

                $files[
                    preg_replace('/\.php$/', '', $relative)
                ] = $file->getMTime();
            }
        }

        return $files;
    }

    public function getMenuPath($module): string
    {
        return $module->getPath()
            . '/config/menu.php';
    }

    public function getRoutesPath($module): string
    {
        return $module->getPath()
            . '/Routes/api.php';
    }

    public function getControllerPath(
        $module,
        string $controllerPath
    ): string {
        return $module->getPath()
            . '/App/Http/Controllers/'
            . $controllerPath
            . '.php';
    }

    public function getResourcePath(
        $module,
        string $resourcePath
    ): string {
        return $module->getPath()
            . '/App/Http/Resources/'
            . $resourcePath
            . '.php';
    }

    public function getRequestPath(
        $module,
        string $requestPath
    ): string {
        return $module->getPath()
            . '/App/Http/Requests/'
            . $requestPath
            . '.php';
    }

    public function getModelPath(
        $module,
        string $modelPath
    ): string {
        return $module->getPath()
            . '/App/Models/'
            . $modelPath
            . '.php';
    }

    public function getSchemaPath(
        $module,
        string $schemaName
    ): string {
        return $module->getPath()
            . '/schema/'
            . $schemaName
            . '.json';
    }

    public function updateGeneratedBlock(
        string $path,
        string $block,
        string $content
    ): bool {
        if (!$this->exists($path)) {
            return false;
        }

        $document = $this->read($path);

        if (!GeneratedBlock::has($document, $block)) {
            return false;
        }

        $document = GeneratedBlock::replace(
            $document,
            $block,
            $content
        );

        File::put($path, $document);

        return true;
    }
}