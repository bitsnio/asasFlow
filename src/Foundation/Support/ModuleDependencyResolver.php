<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Composer\Semver\Semver;
use LogicException;

final class ModuleDependencyResolver
{
    /**
     * Validate enabled modules and return them in dependency order.
     *
     * @param array<string, array<string, mixed>> $modules
     * @return array<string, array<string, mixed>>
     */
    public function resolve(array $modules): array
    {
        $enabled = [];

        foreach ($modules as $name => $module) {
            if (($module['enabled'] ?? true) === true) {
                $enabled[$name] = $module;
            }
        }

        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $name) use (
            &$visit,
            &$ordered,
            &$visiting,
            &$visited,
            $enabled,
            $modules
        ): void {
            if (isset($visited[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                $cycle = implode(' -> ', [...array_keys($visiting), $name]);

                throw new LogicException(
                    "Circular module dependency detected: {$cycle}."
                );
            }

            $module = $enabled[$name] ?? null;

            if ($module === null) {
                throw new LogicException(
                    "Module [{$name}] is not enabled or installed."
                );
            }

            $visiting[$name] = true;

            foreach (($module['requires'] ?? []) as $alias => $constraint) {
                $dependency = $this->findByAlias($modules, (string) $alias);

                if ($dependency === null) {
                    throw new LogicException(
                        "Module [{$name}] requires missing module "
                            . "[{$alias}]. Install that module before enabling "
                            . "[{$name}]."
                    );
                }

                $dependencyName = $dependency['name'];

                if (! isset($enabled[$dependencyName])) {
                    throw new LogicException(
                        "Module [{$name}] requires [{$alias}], but that "
                            . "module is disabled."
                    );
                }

                $this->assertVersionCompatible(
                    $name,
                    (string) $alias,
                    $dependency,
                    $constraint
                );

                $visit($dependencyName);
            }

            unset($visiting[$name]);

            $visited[$name] = true;
            $ordered[$name] = $module;
        };

        foreach (array_keys($enabled) as $name) {
            $this->assertAsasFlowCompatible($name, $enabled[$name]);
            $visit($name);
        }

        return $ordered;
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     * @return array<string, mixed>|null
     */
    private function findByAlias(array $modules, string $alias): ?array
    {
        foreach ($modules as $module) {
            if (($module['alias'] ?? null) === $alias) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $dependency
     */
    private function assertVersionCompatible(
        string $moduleName,
        string $alias,
        array $dependency,
        mixed $constraint
    ): void {
        if (! is_string($constraint) || trim($constraint) === '') {
            throw new LogicException(
                "Invalid version constraint for dependency [{$alias}] "
                    . "declared by module [{$moduleName}]."
            );
        }

        $version = $dependency['version'] ?? null;

        if (! is_string($version) || trim($version) === '') {
            throw new LogicException(
                "Cannot validate [{$moduleName}]'s dependency on [{$alias}]: "
                    . "the dependency has no resolvable version. Set a version "
                    . "in its module.json or composer.json."
            );
        }

        if (! Semver::satisfies($version, $constraint)) {
            throw new LogicException(
                "Module [{$moduleName}] requires [{$alias}] {$constraint}, "
                    . "but version [{$version}] is installed."
            );
        }
    }

    private function assertAsasFlowCompatible(
        string $moduleName,
        array $module
    ): void {
        $constraint = $module['compatibility']['asasflow'] ?? null;

        // Compatibility is optional for older manifests.
        if ($constraint === null) {
            return;
        }

        if (! is_string($constraint) || trim($constraint) === '') {
            throw new LogicException(
                "Invalid AsasFlow compatibility constraint in [{$moduleName}]."
            );
        }

        $version = config('asasflow.version');

        if (! is_string($version) || trim($version) === '') {
            throw new LogicException(
                'Cannot validate module compatibility because '
                    . '[asasflow.version] is not configured.'
            );
        }

        if (! Semver::satisfies(ltrim($version, 'v'), $constraint)) {
            throw new LogicException(
                "Module [{$moduleName}] requires AsasFlow [{$constraint}], "
                    . "but the installed version is [{$version}]."
            );
        }
    }
}
