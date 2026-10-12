<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

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
     *     provider: class-string,
     *     version: string|null,
     *     enabled: bool,
     *     requires: array<string, string>,
     *     compatibility: array<string, mixed>
     * }>
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function all(): array
    {
        if (! config('asasflow.modules.enabled', true)) {
            return [];
        }

        $discovered = [];
        $aliases = [];

        foreach ($this->modules->all() as $module) {
            $manifestPath = $module->path
                . DIRECTORY_SEPARATOR
                . 'module.json';

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
            $enabled = $manifest['enabled'] ?? true;
            $requires = $manifest['requires'] ?? [];
            $compatibility = $manifest['compatibility'] ?? [];
            $version = $this->resolveVersion($manifest, $module->path);

            if (
                ! is_string($name)
                || $name !== $module->name
                || ! is_string($alias)
                || ! preg_match('/^[a-z][a-z0-9_-]*$/', $alias)
                || ! is_string($namespace)
                || trim($namespace, '\\') === ''
                || ! is_string($provider)
                || trim($provider) === ''
                || ! is_bool($enabled)
                || ! is_array($requires)
                || ! is_array($compatibility)
            ) {
                throw new InvalidArgumentException(
                    "Module manifest has missing or invalid fields: "
                    . $manifestPath
                );
            }

            if (isset($aliases[$alias])) {
                throw new InvalidArgumentException(
                    "Duplicate module alias [{$alias}] found in "
                    . "[{$aliases[$alias]}] and [{$name}]."
                );
            }

            foreach ($requires as $dependencyAlias => $constraint) {
                if (
                    ! is_string($dependencyAlias)
                    || ! preg_match(
                        '/^[a-z][a-z0-9_-]*$/',
                        $dependencyAlias
                    )
                    || ! is_string($constraint)
                    || trim($constraint) === ''
                ) {
                    throw new InvalidArgumentException(
                        "Invalid dependency declaration in [{$name}]."
                    );
                }
            }

            $aliases[$alias] = $name;

            $discovered[$name] = [
                'name' => $name,
                'alias' => $alias,
                'namespace' => trim($namespace, '\\'),
                'path' => $module->path,
                'provider' => $provider,
                'version' => $version,
                'enabled' => $enabled,
                'requires' => $requires,
                'compatibility' => $compatibility,
            ];
        }

        return $discovered;
    }

    /**
     * Local modules should declare their version in module.json.
     * composer.json is a fallback for Composer-managed module packages.
     *
     * @param array<string, mixed> $manifest
     */
    private function resolveVersion(array $manifest, string $path): ?string
    {
        $version = $manifest['version'] ?? null;

        if (is_string($version) && trim($version) !== '') {
            return ltrim(trim($version), 'v');
        }

        $composerPath = $path . DIRECTORY_SEPARATOR . 'composer.json';

        if (File::isFile($composerPath)) {
            $composer = json_decode(
                File::get($composerPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (
                is_array($composer)
                && is_string($composer['version'] ?? null)
                && trim($composer['version']) !== ''
            ) {
                return ltrim(trim($composer['version']), 'v');
            }
        }

        return null;
    }
}
