<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services;

use Illuminate\Support\Facades\File;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

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

    public function writeFile(string $path, string $content, bool $force = false): void
    {
        if (!$force && $this->exists($path)) {
            return;
        }
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
        if (!File::exists($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === "php") {
                $relativePath = str_replace($path . "/", "", $file->getPathname());
                $files[rtrim($relativePath, ".php")] = $file->getMTime();
            }
        }
        
        return $files;
    }

    public function getMenuPath($module): string
    {
        return $module->getPath() . "/config/menu.php";
    }

    public function getRoutesPath($module): string
    {
        return $module->getPath() . "/Routes/api.php";
    }

    public function getControllerPath($module, string $controllerPath): string
    {
        return $module->getPath() . "/App/Http/Controllers/" . $controllerPath . ".php";
    }
}
