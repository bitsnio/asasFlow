<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Str;

class RouteGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    public function generate(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $routeFile =
            $this->files->getRoutesPath(
                $module
            );

        $content =
            $this->buildRouteFile(
                $module,
                $definitions
            );

        $this->files->writeFile(
            $routeFile,
            $content,
            true
        );

        return [
            [
                'path' => $routeFile,
                'action' => 'created/updated',
            ],
        ];
    }

    public function preview(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $routeFile =
            $this->files->getRoutesPath(
                $module
            );

        return [
            [
                'action' =>
                $this->files->exists($routeFile)
                    ? 'update'
                    : 'create',

                'file' => $routeFile,
                'type' => 'routes',
            ],
        ];
    }

    protected function buildRouteFile(
        $module,
        array $definitions
    ): string {
        $moduleName =
            $module->getName();

        $controllers =
            $this->collectControllers(
                $definitions
            );

        $content =
            "<?php\n\n";

        $content .=
            "use Illuminate\\Support\\Facades\\Route;\n";

        foreach ($controllers as $definition) {
            $namespace =
                $this->controllerNamespace(
                    $moduleName,
                    $definition
                );

            $content .=
                "use {$namespace}\\"
                . $definition->controllerClass
                . ";\n";
        }

        if (!empty($controllers)) {
            $content .= "\n";
        }

        foreach ($definitions as $definition) {
            $content .=
                $this->buildNode(
                    $definition,
                    [],
                    0
                );
        }

        return $content;
    }

    protected function buildNode(
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $additionalMiddleware =
            array_values(
                array_diff(
                    $definition->middleware,
                    $parentMiddleware
                )
            );

        $content = '';

        /*
         * Resource
         */
        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $content .=
                $this->buildResourceRoute(
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
         * Action
         */
        if ($definition->isAction()) {
            $content .=
                $this->buildActionRoute(
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
         * Children / group
         */
        if (
            !empty($definition->children)
        ) {
            $segment =
                Str::kebab(
                    $definition->name
                );

            $content .=
                $indent
                . "Route::prefix('{$segment}')";

            if ($additionalMiddleware) {
                $content .=
                    "\n"
                    . $indent
                    . "    ->middleware("
                    . $this->phpArray(
                        $additionalMiddleware
                    )
                    . ")";
            }

            $content .=
                "\n"
                . $indent
                . "    ->group(function () {\n";

            foreach (
                $definition->children
                as $child
            ) {
                $content .=
                    $this->buildNode(
                        $child,
                        $definition->middleware,
                        $level + 1
                    );
            }

            $content .=
                $indent
                . "});\n\n";
        }

        return $content;
    }

    protected function buildResourceRoute(
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $actions =
            $definition->routeActions();

        $resource =
            "Route::apiResource("
            . "'"
            . Str::kebab(
                $definition->name
            )
            . "', "
            . $definition->controllerClass
            . "::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .=
                "->only("
                . $this->phpArray($actions)
                . ")";
        }

        $resource .= ";";

        if (!$middleware) {
            return
                $indent
                . $resource
                . "\n\n";
        }

        return
            $indent
            . "Route::middleware("
            . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent
            . "    "
            . $resource
            . "\n"
            . $indent
            . "});\n\n";
    }

    protected function buildActionRoute(
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $method =
            strtolower(
                $definition->actionMethod()
            );

        $path =
            $definition->actionPath();

        $controller =
            $definition->controllerClass;

        if (!$controller) {
            throw new \InvalidArgumentException(
                "Action ["
                    . $definition->permissionKey()
                    . "] requires a controller."
            );
        }

        $route =
            "Route::{$method}("
            . "'{$path}', "
            . $controller
            . "::class)";

        if ($definition->config['controller_method'] ?? null) {
            $route =
                "Route::{$method}("
                . "'{$path}', ["
                . $controller
                . "::class, '"
                . $definition->config['controller_method']
                . "'])";
        }

        $route .= ";";

        if (!$middleware) {
            return
                $indent
                . $route
                . "\n\n";
        }

        return
            $indent
            . "Route::middleware("
            . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent
            . "    "
            . $route
            . "\n"
            . $indent
            . "});\n\n";
    }

    protected function collectControllers(
        array $definitions
    ): array {
        $result = [];

        foreach ($definitions as $definition) {
            if ($definition->controllerClass) {
                $result[] = $definition;
            }

            $result = [
                ...$result,
                ...$this->collectControllers(
                    $definition->children
                ),
            ];
        }

        return $result;
    }

    protected function controllerNamespace(
        string $moduleName,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$moduleName}"
            . "\\App\\Http\\Controllers";

        $relative =
            $definition->controllerNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function phpArray(
        array $values
    ): string {
        return var_export(
            array_values($values),
            true
        );
    }
}
