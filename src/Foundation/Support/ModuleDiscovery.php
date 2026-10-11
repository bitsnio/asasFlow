<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class ModuleDiscovery
{
    public function __construct(
        private readonly ModuleRepositoryInterface $modules,
    ) {}

    /**
     * @return array<string, array{
     *     name: string,
     *     alias: string,
     *     namespace: string,
     *     path: string,
     *     provider: class-string
     * }>
     */
    public function all(): array
    {
        if (! config('asasflow.modules.enabled', true)) {
            return [];
        }

        $discovered = [];
        $aliases = [];

        foreach ($this->modules->all() as $module) {
            $manifestPath = $module->path . DIRECTORY_SEPARATOR . 'module.json';
            
            if (! File::isFile($manifestPath)) {
                throw new InvalidArgumentException(
                    "Module manifest not found: {$manifestPath}"
                );
            }

            $manifest = json_decode(
                File::get($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($manifest)) {
                throw new InvalidArgumentException(
                    "Invalid module manifest: {$manifestPath}"
                );
            }

            $name = $manifest['name'] ?? null;
            $alias = $manifest['alias'] ?? null;
            $namespace = $manifest['namespace'] ?? null;
            $provider = $manifest['provider'] ?? null;

            if (
                ! is_string($name)
                || $name !== $module->name
                || ! is_string($alias)
                || $alias === ''
                || ! is_string($namespace)
                || $namespace === ''
                || ! is_string($provider)
                || $provider === ''
            ) {
                throw new InvalidArgumentException(
                    "Module manifest has missing or invalid required fields: {$manifestPath}"
                );
            }

            if (isset($aliases[$alias])) {
                throw new InvalidArgumentException(
                    "Duplicate module alias [{$alias}] found in "
                    . "[{$aliases[$alias]}] and [{$name}]."
                );
            }

            $aliases[$alias] = $name;

            // Individual modules are enabled by default unless explicitly disabled.
            if (($manifest['enabled'] ?? true) !== true) {
                continue;
            }

            $discovered[$name] = [
                'name' => $name,
                'alias' => $alias,
                'namespace' => trim($namespace, '\\'),
                'path' => $module->path,
                'provider' => $provider,
            ];
        }

        return $discovered;
    }
}