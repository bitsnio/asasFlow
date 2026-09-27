<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuBuilder
{
    public function build(array $menu): array
    {
        $module = $menu['module'] ?? null;

        if (!is_array($module)) {
            throw new InvalidArgumentException(
                'Missing module configuration.'
            );
        }

        $moduleName = $module['name'] ?? null;

        if (
            !is_string($moduleName) ||
            trim($moduleName) === ''
        ) {
            throw new InvalidArgumentException(
                'Module name is required.'
            );
        }

        return [
            $this->buildNode(
                $module,
                $moduleName,
                [],
                []
            ),
        ];
    }

    public function flatten(
        array $definitions
    ): array {
        $flat = [];

        foreach ($definitions as $definition) {
            $this->flattenNode(
                $definition,
                $flat
            );
        }

        return $flat;
    }

    protected function flattenNode(
        MenuDefinition $definition,
        array &$flat
    ): void {
        $flat[$definition->permissionKey()] = $definition;

        foreach ($definition->children as $child) {
            $this->flattenNode(
                $child,
                $flat
            );
        }
    }

    protected function buildNode(
        array $config,
        string $moduleName,
        array $parentPath,
        array $inheritedMiddleware,
    ): MenuDefinition {
        $name = $config['name'] ?? null;

        if (
            !is_string($name) ||
            trim($name) === ''
        ) {
            throw new InvalidArgumentException(
                'Every menu node must contain a non-empty name.'
            );
        }

        $path = [
            ...$parentPath,
            $name,
        ];

        $middleware = $this->middleware(
            $inheritedMiddleware,
            $config['middleware'] ?? [],
            empty($inheritedMiddleware) &&
                !isset($config['middleware'])
                ? ['api']
                : []
        );

        $type = $config['type']
            ?? (
                !empty($config['children'])
                ? 'group'
                : 'resource'
            );

        if (!in_array(
            $type,
            ['group', 'resource'],
            true
        )) {
            throw new InvalidArgumentException(
                "Invalid type [{$type}] for ["
                    . implode('.', $path)
                    . ']. Use group or resource.'
            );
        }

        $controllerClass =
            $this->controllerClass(
                $config,
                $type
            );

        $resourceClass =
            $this->resourceClass(
                $config,
                $type
            );

        $schemaName = null;

        if (($config['model'] ?? false) === true) {
            $schemaName =
                $config['schema']['name']
                ?? Str::studly($name);
        }

        $children = [];

        foreach ($config['children'] ?? [] as $child) {
            if (!is_array($child)) {
                throw new InvalidArgumentException(
                    'Every children entry must be an array at ['
                        . implode('.', $path)
                        . '].'
                );
            }

            $children[] = $this->buildNode(
                $child,
                $moduleName,
                $path,
                $middleware
            );
        }

        return new MenuDefinition(
            module: $moduleName,
            name: $name,
            title: $config['title']
                ?? Str::headline($name),
            path: $path,
            type: $type,
            middleware: $middleware,
            model: (bool) (
                $config['model'] ?? false
            ),
            routes: is_array(
                $config['routes'] ?? null
            )
                ? $config['routes']
                : [],
            permissions: is_array(
                $config['permissions'] ?? null
            )
                ? $config['permissions']
                : [],
            controllerClass: $controllerClass,
            resourceClass: $resourceClass,
            schemaName: $schemaName,
            children: $children,
            config: $config,
        );
    }

    protected function controllerClass(
        array $config,
        string $type
    ): ?string {
        if (
            ($config['controller']['enabled']
                ?? true) === false
        ) {
            return null;
        }

        if (
            $type !== 'resource' &&
            !isset($config['controller']['class'])
        ) {
            return null;
        }

        return $config['controller']['class']
            ?? Str::studly($config['name'])
            . 'Controller';
    }

    protected function resourceClass(
        array $config,
        string $type
    ): ?string {
        if (
            ($config['resource']['enabled']
                ?? true) === false
        ) {
            return null;
        }

        if (
            $type !== 'resource' &&
            !isset($config['resource']['class'])
        ) {
            return null;
        }

        return $config['resource']['class']
            ?? Str::studly($config['name'])
            . 'Resource';
    }

    protected function middleware(
        array ...$sets
    ): array {
        $result = [];

        foreach ($sets as $set) {
            foreach ($set as $middleware) {
                if (!in_array(
                    $middleware,
                    $result,
                    true
                )) {
                    $result[] = $middleware;
                }
            }
        }

        return $result;
    }
}
