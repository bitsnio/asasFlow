<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuBuilder
{
    protected const TYPES = [
        'resource',
        'action',
    ];

    protected const ACTION_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    public function build(array $menu): array
    {
        $module = $menu['module'] ?? null;

        if (!is_array($module)) {
            throw new InvalidArgumentException(
                'Menu configuration must contain a module array.'
            );
        }

        $moduleName = $module['name'] ?? null;

        if (!$moduleName) {
            throw new InvalidArgumentException(
                'Menu module name is required.'
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

    protected function buildNode(
        array $config,
        string $moduleName,
        array $parentPath,
        array $inheritedMiddleware,
    ): MenuDefinition {
        $name = $config['name'] ?? null;

        if (!$name) {
            throw new InvalidArgumentException(
                'Every menu item must have a name.'
            );
        }

        $path = [
            ...$parentPath,
            $name,
        ];

        /*
        |--------------------------------------------------------------------------
        | Type
        |--------------------------------------------------------------------------
        |
        | No type means group.
        |
        */

        $type = $config['type'] ?? 'group';

        if (!in_array(
            $type,
            ['group', ...self::TYPES],
            true
        )) {
            throw new InvalidArgumentException(
                "Invalid menu type [{$type}] "
                    . "for [{$name}]. "
                    . "Allowed types: resource, action."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Middleware
        |--------------------------------------------------------------------------
        */

        $middleware = $this->middleware(
            $inheritedMiddleware,
            $config['middleware'] ?? [],
            empty($inheritedMiddleware)
                && !isset($config['middleware'])
                ? ['api']
                : []
        );

        /*
        |--------------------------------------------------------------------------
        | Children
        |--------------------------------------------------------------------------
        */

        $children = [];

        foreach (
            ($config['children'] ?? [])
            as $child
        ) {
            if (!is_array($child)) {
                continue;
            }

            $children[] = $this->buildNode(
                $child,
                $moduleName,
                $path,
                $middleware
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Action Validation
        |--------------------------------------------------------------------------
        */

        if ($type === 'action') {
            if (!empty($children)) {
                throw new InvalidArgumentException(
                    "Action [{$name}] "
                        . "cannot contain children."
                );
            }

            $method = strtoupper(
                $config['method'] ?? 'POST'
            );

            if (!in_array(
                $method,
                self::ACTION_METHODS,
                true
            )) {
                throw new InvalidArgumentException(
                    "Invalid method [{$method}] "
                        . "for action [{$name}]. "
                        . "Allowed methods: "
                        . implode(
                            ', ',
                            self::ACTION_METHODS
                        )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Component Defaults
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Schema
        |--------------------------------------------------------------------------
        */

        $schemaName = null;

        if (
            ($config['model'] ?? false) === true
        ) {
            $schemaName =
                $config['schema']['name']
                ?? Str::studly($name);
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
        $controller = $config['controller'] ?? null;

        /*
        | Explicitly disabled.
        */
        if (
            is_array($controller)
            && ($controller['enabled'] ?? true) === false
        ) {
            return null;
        }

        /*
        | Explicit class.
        */
        if (
            is_array($controller)
            && !empty($controller['class'])
        ) {
            return $controller['class'];
        }

        /*
        | Groups don't get controllers automatically.
        */
        if ($type === 'group') {
            return null;
        }

        /*
        | Resource default.
        */
        if ($type === 'resource') {
            return Str::studly(
                $config['name']
            ) . 'Controller';
        }

        /*
        | Action default.
        |
        | We only generate the controller class.
        | No controller method is assumed.
        */
        if ($type === 'action') {
            return Str::studly(
                $config['name']
            ) . 'Controller';
        }

        return null;
    }

    protected function resourceClass(
        array $config,
        string $type
    ): ?string {
        /*
        | Actions and groups don't have API resources.
        */
        if (
            $type !== 'resource'
        ) {
            return null;
        }

        $resource = $config['resource'] ?? null;

        if (
            is_array($resource)
            && ($resource['enabled'] ?? true) === false
        ) {
            return null;
        }

        if (
            is_array($resource)
            && !empty($resource['class'])
        ) {
            return $resource['class'];
        }

        return Str::studly(
            $config['name']
        ) . 'Resource';
    }

    protected function middleware(
        array $inherited,
        array $current,
        array $defaults = []
    ): array {
        return array_values(
            array_unique([
                ...$inherited,
                ...$defaults,
                ...$current,
            ])
        );
    }

    protected function flattenNode(
        MenuDefinition $definition,
        array &$flat
    ): void {
        $flat[$definition->permissionKey()] = $definition;

        foreach (
            $definition->children
            as $child
        ) {
            $this->flattenNode(
                $child,
                $flat
            );
        }
    }
}
