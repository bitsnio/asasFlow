<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ModuleRepository implements ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition
    {
        $name = $this->normalizeName($name);
        $path = $this->modulesPath() . DIRECTORY_SEPARATOR . $name;

        if (! File::isDirectory($path)) {
            return null;
        }

        return new ModuleDefinition(
            name: $name,
            path: $path,
            namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
        );
    }

    public function all(): array
    {
        $path = $this->modulesPath();
        if (! File::isDirectory($path)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($path) as $directory) {
            $name = basename($directory);
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $modules[$name] = new ModuleDefinition(
                name: $name,
                path: $directory,
                namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
            );
        }

        return $modules;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (
            $name === ''
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)
        ) {
            throw new InvalidArgumentException("Invalid module name [{$name}].");
        }

        $name = Str::studly($name);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid normalized module name [{$name}].");
        }

        return $name;
    }

    private function modulesPath(): string
    {
        return rtrim(
            (string) config('asasflow.modules.path', base_path('Modules')),
            DIRECTORY_SEPARATOR
        );
    }

    private function modulesNamespace(): string
    {
        return (string) config('asasflow.modules.namespace', 'Modules');
    }
}
