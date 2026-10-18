<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteFileBuilder
{
    protected const DEFAULT_RESOURCE_ACTIONS = [
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ];

    public function __construct(
        protected GeneratorSupport $support,
        protected RouteFormatter $formatter,
    ) {}

    public function build($module, array $definitions): string
    {
        $controllers = $this->collectControllers($definitions);
        $imports = [Route::class];

        foreach ($controllers as $definition) {
            $imports[] = $this->support->controllerNamespace($module, $definition)
                . '\\'
                . $definition->controllerClass;
        }

        $content = "<?php\n\n";
        $content .= $this->support->generatorImports('route', $imports);
        $content .= "\n\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode($module, $definition, [], 0);
        }

        return $content;
    }

    protected function buildNode(
        $module,
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $additionalMiddleware = array_values(
            array_diff($definition->middleware, $parentMiddleware),
        );

        $content = '';

        if ($definition->isResource() && $definition->hasController()) {
            $content .= $this->buildResourceRoute($module, $definition, $additionalMiddleware, $level);
        }

        foreach ($definition->customActions() as $actionName => $config) {
            $content .= $this->buildResourceActionRoute($module, $definition, $actionName, $additionalMiddleware, $level);
        }

        if (!empty($definition->children)) {
            $segment = Str::kebab($definition->name);
            $content .= $indent . "Route::prefix('{$segment}')";

            if ($additionalMiddleware) {
                $content .= "\n" . $indent . '    ->middleware(' . $this->formatter->phpArray($additionalMiddleware) . ')';
            }

            $content .= "\n" . $indent . "    ->group(function () {\n";

            foreach ($definition->children as $child) {
                $content .= $this->buildNode($module, $child, $definition->middleware, $level + 1);
            }

            $content .= $indent . "});\n\n";
        }

        return $content;
    }

    public function buildResourceRoute(
        $module,
        MenuDefinition $definition,
        array $middleware,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $actions = $definition->routeActions();

        $resource = 'Route::apiResource('
            . var_export(Str::kebab($definition->name), true)
            . ", {$definition->controllerClass}::class)";

        if ($actions !== self::DEFAULT_RESOURCE_ACTIONS) {
            $resource .= '->only(' . $this->formatter->phpArray($actions) . ')';
        }

        $resource .= ';';

        return $this->formatter->wrapInMiddlewareGroup($resource, $middleware, $indent);
    }

    public function buildResourceActionRoute(
        $module,
        MenuDefinition $definition,
        string $actionName,
        array $middleware,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
        $uri = Str::kebab($definition->name) . '/' . Str::kebab($actionName);
        $method = strtolower($definition->customActionMethod($actionName));

        $route = 'Route::'
            . $method
            . '('
            . var_export($uri, true)
            . ', ['
            . $definition->controllerClass
            . '::class, '
            . var_export(Str::camel($actionName), true)
            . ']);';

        return $this->formatter->wrapInMiddlewareGroup($route, $middleware, $indent);
    }

    protected function collectControllers(array $definitions): array
    {
        $result = [];

        foreach ($definitions as $definition) {
            if ($definition->hasController()) {
                $result[] = $definition;
            }

            if (!empty($definition->children)) {
                array_push($result, ...$this->collectControllers($definition->children));
            }
        }

        return $result;
    }
}