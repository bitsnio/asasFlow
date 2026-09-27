<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Illuminate\Support\Str;

class RouteGenerator
implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected RouteNameGenerator $routeNameGenerator,
    ) {}

    public function generate(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $path =
            $this->files->getRoutesPath($module);

        $exists =
            $this->files->exists($path);

        $content =
            $this->buildRouteFile(
                $module,
                $definitions
            );

        $this->files->writeFile(
            $path,
            $content,
            true
        );

        return [[
            'path' => $path,
            'action' =>
            $exists ? 'updated' : 'created',
        ]];
    }

    public function preview(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $path =
            $this->files->getRoutesPath($module);

        return [[
            'action' =>
            $this->files->exists($path)
                ? 'update'
                : 'create',
            'file' => $path,
            'type' => 'routes',
        ]];
    }

    protected function buildRouteFile(
        $module,
        array $definitions
    ): string {
        $controllers =
            $this->collectControllers(
                $definitions
            );

        $content =
            "<?php\n\n"
            . "use Illuminate\\Support\\Facades\\Route;\n";

        foreach ($controllers as $definition) {
            $namespace =
                $this->controllerNamespace(
                    $module,
                    $definition
                );

            $content .=
                "use {$namespace}\\"
                . "{$definition->controllerClass};\n";
        }

        $content .= "\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode(
                $definition,
                [],
                0
            );
        }

        return rtrim($content) . "\n";
    }

    protected function buildNode(
        MenuDefinition $definition,
        array $parentMiddleware = [],
        int $level = 0
    ): string {
        $indent =
            str_repeat('    ', $level);

        $segment =
            Str::kebab($definition->name);

        $additionalMiddleware =
            array_values(
                array_diff(
                    $definition->middleware,
                    $parentMiddleware
                )
            );

        $content = '';

        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $content .= $this->resourceRoute(
                $definition,
                $segment,
                $level,
                $additionalMiddleware
            );
        }

        if (!empty($definition->children)) {
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
                    . ')';
            }

            $content .=
                "\n"
                . $indent
                . "    ->group(function () {\n";

            foreach (
                $definition->children
                as $child
            ) {
                $content .= $this->buildNode(
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

    protected function resourceRoute(
        MenuDefinition $definition,
        string $segment,
        int $level,
        array $additionalMiddleware
    ): string {
        $indent =
            str_repeat('    ', $level);

        $actions =
            $definition->routeActions();

        $resource =
            "Route::apiResource("
            . "'{$segment}', "
            . "{$definition->controllerClass}::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .=
                '->only('
                . $this->phpArray($actions)
                . ')';
        }

        $resource .=
            '->names('
            . $this->routeNames(
                $definition,
                $actions
            )
            . ');';

        if (!$additionalMiddleware) {
            return
                $indent
                . $resource
                . "\n\n";
        }

        return
            $indent
            . 'Route::middleware('
            . $this->phpArray(
                $additionalMiddleware
            )
            . ")->group(function () {\n"
            . $indent
            . '    '
            . $resource
            . "\n"
            . $indent
            . "});\n\n";
    }

    protected function routeNames(
        MenuDefinition $definition,
        array $actions
    ): string {
        $names = [];

        foreach ($actions as $action) {
            $names[$action] =
                $this->routeNameGenerator
                ->generateRouteName(
                    $definition->path,
                    $action
                );
        }

        return $this->phpArray($names);
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
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
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
        $parts = [];

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $parts[] =
                    var_export($value, true);
            } else {
                $parts[] =
                    var_export($key, true)
                    . ' => '
                    . var_export($value, true);
            }
        }

        return '['
            . implode(', ', $parts)
            . ']';
    }
}
