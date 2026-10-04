<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Str;

class MenuDefinition
{
    public function __construct(
        public readonly string $module,
        public readonly string $name,
        public readonly string $title,
        public readonly array $path,
        public readonly string $type = 'group',
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

    /*
    |--------------------------------------------------------------------------
    | Type
    |--------------------------------------------------------------------------
    */

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isResource(): bool
    {
        return $this->type === 'resource';
    }

    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    /*
    |--------------------------------------------------------------------------
    | Generated Components
    |--------------------------------------------------------------------------
    */

    public function hasController(): bool
    {
        return $this->controllerClass !== null;
    }

    public function hasResource(): bool
    {
        return $this->resourceClass !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Menu / Permission Path
    |--------------------------------------------------------------------------
    */

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

    public function routePath(): string
    {
        $segments = array_map(
            [Str::class, 'kebab'],
            $this->path
        );

        if ($this->isAction()) {
            array_pop($segments);

            $actionPath = $this->actionPath();

            if ($actionPath !== '') {
                $segments[] = $actionPath;
            }
        }

        return implode('/', $segments);
    }

    public function routeSegment(): string
    {
        return $this->isAction()
            ? $this->actionPath()
            : Str::kebab($this->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Action
    |--------------------------------------------------------------------------
    */

    public function actionMethod(): ?string
    {
        if (!$this->isAction()) {
            return null;
        }

        return strtoupper(
            $this->config['method'] ?? 'POST'
        );
    }

    public function actionPath(): string
    {
        if (!$this->isAction()) {
            return Str::kebab($this->name);
        }

        return trim(
            (string) (
                $this->config['path']
                ?? Str::kebab($this->name)
            ),
            '/'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Controller / Resource Namespace
    |--------------------------------------------------------------------------
    */

    public function controllerNamespace(): string
    {
        return $this->relativeNamespace($this->path);
    }

    public function controllerRelativePath(): string
    {
        $namespace = $this->controllerNamespace();

        return ($namespace
            ? str_replace('\\', '/', $namespace) . '/'
            : '')
            . $this->controllerClass;
    }

    public function resourceNamespace(): string
    {
        return $this->relativeNamespace($this->path);
    }

    public function resourceRelativePath(): string
    {
        $namespace = $this->resourceNamespace();

        return ($namespace
            ? str_replace('\\', '/', $namespace) . '/'
            : '')
            . $this->resourceClass;
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

    /*
    |--------------------------------------------------------------------------
    | Model / Request
    |--------------------------------------------------------------------------
    */

    public function modelClass(): string
    {
        $model = $this->config['model'] ?? null;

        if (is_array($model)) {
            return $model['class']
                ?? Str::studly($this->name);
        }

        return $this->config['model_name']
            ?? Str::studly($this->name);
    }

    public function requestClass(): string
    {
        $request = $this->config['request'] ?? null;

        if (is_array($request)) {
            return $request['class']
                ?? $this->modelClass() . 'Request';
        }

        return $this->config['request_name']
            ?? $this->modelClass() . 'Request';
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Routes
    |--------------------------------------------------------------------------
    */

    public function routeActions(): array
    {
        if (!empty($this->routes)) {
            return array_values(
                array_unique($this->routes)
            );
        }

        return match ($this->config['routes_type'] ?? 'full') {
            'index', 'list' => ['index'],
            'create', 'store' => ['store'],
            'show' => ['show'],
            'update' => ['update'],
            'delete', 'destroy' => ['destroy'],

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

    /**
     * Custom actions belonging to this resource.
     *
     * @return array<string, array>
     */
    public function customActions(): array
    {
        $actions = $this->config['actions'] ?? [];

        if (!is_array($actions)) {
            return [];
        }

        return $actions;
    }

    /**
     * HTTP method for a custom action.
     */
    public function customActionMethod(string $name): string
    {
        $action = $this->customActions()[$name] ?? [];

        return strtoupper($action['method'] ?? 'POST');
    }

    /**
     * Human-readable permission label for a custom action.
     */
    public function customActionPermissionLabel(string $name): string
    {
        $action = $this->customActions()[$name] ?? [];

        return $action['permissionLabel']
            ?? Str::headline($name) . ' ' . $this->title;
    }

    /**
     * Preserve the existing action permission naming convention.
     *
     * Example:
     * organization.companies.approve.execute
     */
    public function customActionPermissionKey(string $name): string
    {
        return $this->permissionKey()
            . '.'
            . Str::kebab($name)
            . '.execute';
    }

    /**
     * Full custom action route path.
     */
    public function customActionRoutePath(string $name): string
    {
        return $this->routePath()
            . '/'
            . Str::kebab($name);
    }
}
