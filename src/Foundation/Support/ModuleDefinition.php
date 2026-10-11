<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

final readonly class ModuleDefinition
{
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
    ) {}

    /**
     * Backward-compatible accessors for existing generators.
     */

    public function appPath(string $path = ''): string
    {
        $base = $this->path . '/app';

        return $path === ''
            ? $base
            : $base . '/' . ltrim($path, '/');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function configPath(): string
    {
        return $this->path . '/config';
    }

    public function routesPath(): string
    {
        return $this->path . '/routes';
    }

    public function migrationsPath(): string
    {
        return $this->path . '/database/migrations';
    }

    /**
     * Matches the existing app/Providers directory convention.
     */
    public function providerClass(): string
    {
        return $this->namespace . '\\Providers\\' . $this->name . 'ServiceProvider';
    }
}
