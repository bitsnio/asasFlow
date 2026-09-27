<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Illuminate\Support\Str;

class MenuDefinition
{
    public function __construct(
        public readonly string $module,
        public readonly string $name,
        public readonly string $title,
        public readonly array $path,
        public readonly string $type,
        public readonly array $middleware = [],
        public readonly bool $model = false,
        public readonly array $routes = [],
        public readonly array $permissions = [],
        public readonly ?string $controllerClass = null,
        public readonly ?string $resourceClass = null,
        public readonly ?string $schemaName = null,
        public readonly array $children = [],
        public readonly array $config = [],
    ) {}

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function hasController(): bool
    {
        return $this->controllerClass !== null;
    }

    public function hasResource(): bool
    {
        return $this->resourceClass !== null;
    }

    public function isResource(): bool
    {
        return $this->type === 'resource';
    }

    public function routePath(): string
    {
        return implode(
            '/',
            array_map(
                [Str::class, 'kebab'],
                $this->path
            )
        );
    }

    public function permissionKey(): string
    {
        return implode(
            '.',
            array_map(
                [Str::class, 'kebab'],
                $this->path
            )
        );
    }

    public function permission(
        string $action
    ): string {
        $permissions = $this->permissions;

        /*
         * Allow explicit permission override:
         *
         * 'permissions' => [
         *     'view' => 'admin.dashboard.read',
         * ]
         */
        if (
            isset($permissions[$action]) &&
            is_string($permissions[$action])
        ) {
            return $permissions[$action];
        }

        return $this->permissionKey()
            . '.'
            . Str::kebab($action);
    }

    public function controllerNamespace(): string
    {
        return $this->relativeNamespace(
            array_slice(
                $this->path,
                1,
                -1
            )
        );
    }

    public function controllerRelativePath(): string
    {
        $namespace =
            $this->controllerNamespace();

        return (
            $namespace
            ? str_replace(
                '\\',
                '/',
                $namespace
            ) . '/'
            : ''
        )
            . $this->controllerClass;
    }

    public function resourceNamespace(): string
    {
        return $this->relativeNamespace(
            array_slice(
                $this->path,
                1,
                -1
            )
        );
    }

    public function resourceRelativePath(): string
    {
        $namespace =
            $this->resourceNamespace();

        return (
            $namespace
            ? str_replace(
                '\\',
                '/',
                $namespace
            ) . '/'
            : ''
        )
            . $this->resourceClass;
    }

    /*
     * ---------------------------------------------------------
     * Model
     * ---------------------------------------------------------
     */

    public function modelClass(): string
    {
        $model =
            $this->config['model']
            ?? null;

        if (
            is_array($model) &&
            !empty($model['class'])
        ) {
            return $model['class'];
        }

        if (
            isset($this->config['model_name']) &&
            is_string(
                $this->config['model_name']
            )
        ) {
            return $this->config['model_name'];
        }

        return Str::studly(
            $this->name
        );
    }

    /*
     * ---------------------------------------------------------
     * Request
     * ---------------------------------------------------------
     */

    public function requestClass(): string
    {
        $request =
            $this->config['request']
            ?? null;

        if (
            is_array($request) &&
            !empty($request['class'])
        ) {
            return $request['class'];
        }

        if (
            isset($this->config['request_name']) &&
            is_string(
                $this->config['request_name']
            )
        ) {
            return $this->config['request_name'];
        }

        return $this->modelClass()
            . 'Request';
    }

    /*
     * ---------------------------------------------------------
     * Resource
     * ---------------------------------------------------------
     */

    public function resourceClass(): ?string
    {
        if (!$this->resourceClass) {
            return null;
        }

        return $this->resourceClass;
    }

    /*
     * ---------------------------------------------------------
     * Routes
     * ---------------------------------------------------------
     */

    public function routeActions(): array
    {
        if (!empty($this->routes)) {
            return array_values(
                array_unique(
                    $this->routes
                )
            );
        }

        return match ($this->config['routes_type']
            ?? 'full') {
            'index',
            'list' => [
                'index',
            ],

            'create',
            'store' => [
                'store',
            ],

            'show' => [
                'show',
            ],

            'update' => [
                'update',
            ],

            'delete',
            'destroy' => [
                'destroy',
            ],

            default => [
                'index',
                'store',
                'show',
                'update',
                'destroy',
            ],
        };
    }

    public function parameterName(): string
    {
        return Str::camel(
            $this->modelClass()
        );
    }

    protected function relativeNamespace(
        array $segments
    ): string {
        return implode(
            '\\',
            array_map(
                [Str::class, 'studly'],
                $segments
            )
        );
    }
}
