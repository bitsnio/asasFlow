<?php

namespace AsasFlow\Features\ControllerGeneration\Services;

use Illuminate\Support\Facades\File;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class FileHandlerService
{
    public function exists(string $path): bool
    {
        return File::exists($path);
    }

    public function read(string $path): string
    {
        return File::get($path);
    }

    public function write(string $path, string $content, bool $force = false): void
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

    public function getLastModified(string $path): int
    {
        return File::lastModified($path);
    }

    public function findFiles(string $path, string $extension = 'php'): array
    {
        $files = [];
        if (!File::exists($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $relativePath = str_replace($path . '/', '', $file->getPathname());
                $files[rtrim($relativePath, ".{$extension}")] = $file->getMTime();
            }
        }
        
        return $files;
    }

    public function getModifiedTime(string $path): int
    {
        return filemtime($path);
    }
}
