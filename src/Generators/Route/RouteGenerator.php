<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Illuminate\Support\Str;

class RouteGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
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
        $controllers = $this->collectControllers($definitions);

        $imports = [
            \Illuminate\Support\Facades\Route::class,
        ];

        foreach ($controllers as $definition) {
            $imports[] = $this->support->controllerNamespace(
                $module,
                $definition
            ) . '\\' . $definition->controllerClass;
        }

        $content = "<?php\n\n";

        $content .= $this->support->generatorImports(
            'route',
            $imports
        );

        $content .= "\n\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode(
                $module,
                $definition,
                [],
                0
            );
        }

        return $content;
    }

    protected function buildNode(
        $module,
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
                    $module,
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
     * Custom actions belong to this resource controller.
     */
        foreach ($definition->customActions() as $actionName => $config) {
            $content .= $this->buildResourceActionRoute(
                $module,
                $definition,
                $actionName,
                $definition->customActionMethod($actionName),
                $additionalMiddleware,
                $level
            );
        }

        // /*
        //  * Action
        //  */
        // if ($definition->isAction()) {
        //     $content .=
        //         $this->buildActionRoute(
        //             $definition,
        //             $additionalMiddleware,
        //             $level
        //         );
        // }

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
                        $module,
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
        $module,
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {

        $indent = str_repeat('    ', $level);

        // $controller = '\\'
        //     . ltrim(
        //         $this->support->controllerNamespace(
        //             $module,
        //             $definition
        //         ) . '\\' . $definition->controllerClass,
        //         '\\'
        //     );

        $actions = $definition->routeActions();

        $resource = "Route::apiResource(" . var_export(Str::kebab($definition->name), true) . ", {$definition->controllerClass}::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .= '->only(' . $this->phpArray($actions) . ')';
        }

        $resource .= ';';

        if (!$middleware) {
            return $indent . $resource . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $resource . "\n"
            . $indent . "});\n\n";
    }


    protected function buildResourceActionRoute(
        $module,
        MenuDefinition $definition,
        string $actionName,
        string $method,
        array $middleware,
        int $level
    ): string {
        
        $indent = str_repeat('    ', $level);

        // // $controller = '\\'
        //     . ltrim(
        //         $this->support->controllerNamespace(
        //             $module,
        //             $definition
        //         ) . '\\' . $definition->controllerClass,
        //         '\\'
        //     );

        $uri = Str::kebab($definition->name) . '/' . Str::kebab($actionName);

        $route = 'Route::'
            . strtolower($method)
            . '('
            . var_export($uri, true)
            . ', ['
            . $definition->controllerClass
            . '::class, '
            . var_export(Str::camel($actionName), true)
            . ']);';

        if (!$middleware) {
            return $indent . $route . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $route . "\n"
            . $indent . "});\n\n";
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

    /**
     * Convert an array of values into a PHP array expression.
     */
    protected function phpArray(array $values): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = array_map(
            fn($value) => var_export($value, true),
            $values
        );

        return '[' . implode(', ', $items) . ']';
    }
}
