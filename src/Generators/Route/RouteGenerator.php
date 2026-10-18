<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RouteGenerator implements GeneratorInterface
{
    protected const DEFAULT_RESOURCE_ACTIONS = [
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ];

    // 1. Inject RouteActionSynchronizer in the constructor
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
        protected RouteActionSynchronizer $routeSynchronizer,
    ) {}

    public function key(): string
    {
        return 'routes';
    }

    public function generate(
        $module,
        array $definitions,
        array $options = [],
    ): array {
        $routeFile = $this->files->getRoutesPath($module);
        $exists = $this->files->exists($routeFile);
        $force = (bool) ($options['force'] ?? false);

        if (!$exists || $force) {
            $content = $this->buildRouteFile($module, $definitions);

            $this->files->writeFile(
                $routeFile,
                $content,
                true,
            );

            return [
                [
                    'path' => $routeFile,
                    'action' => $exists ? 'regenerated' : 'created',
                ],
            ];
        }

        $document = $this->files->read($routeFile);

        // 2. Delegate incremental route syncing to RouteActionSynchronizer
        [$updatedDocument, $addedRoutes] = $this->syncMissingRoutes(
            $module,
            $document,
            $definitions,
        );

        if (!empty($addedRoutes)) {
            $this->files->writeFile(
                $routeFile,
                $updatedDocument,
                true,
            );

            return [
                [
                    'path' => $routeFile,
                    'action' => 'updated (added: ' . implode(', ', $addedRoutes) . ')',
                ],
            ];
        }

        return [
            [
                'path' => $routeFile,
                'action' => 'skipped (up-to-date)',
            ],
        ];
    }

    protected function syncMissingRoutes(
        $module,
        string $document,
        array $definitions,
    ): array {
        $added = [];

        foreach ($definitions as $definition) {
            $this->syncNodeRoutes(
                $module,
                $definition,
                [],
                0,
                $document,
                $added,
            );
        }

        return [$document, $added];
    }

    protected function syncNodeRoutes(
        $module,
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level,
        string &$document,
        array &$added,
    ): void {
        if ($definition->isResource() && $definition->hasController()) {
            $controllerClass = $definition->controllerClass;
            $fqcn = $this->support->controllerNamespace($module, $definition) . '\\' . $controllerClass;

            if (!$this->routeSynchronizer->hasApiResourceRoute($document, $definition)) {
                $document = $this->routeSynchronizer->ensureImportExists($document, $fqcn);

                $additionalMiddleware = array_values(
                    array_diff($definition->middleware, $parentMiddleware),
                );

                $resourceSnippet = trim($this->buildResourceRoute(
                    $module,
                    $definition,
                    $additionalMiddleware,
                    $level,
                ));

                $document = $this->routeSynchronizer->insertRouteIntoParentGroup(
                    $document,
                    $definition,
                    $resourceSnippet,
                    $level,
                );

                $added[] = Str::kebab($definition->name);
            }

            foreach ($definition->customActions() as $actionName => $config) {
                $methodName = Str::camel($actionName);

                if (!$this->routeSynchronizer->hasCustomActionRoute($document, $controllerClass, $methodName)) {
                    $document = $this->routeSynchronizer->ensureImportExists($document, $fqcn);

                    $additionalMiddleware = array_values(
                        array_diff($definition->middleware, $parentMiddleware),
                    );

                    $actionRouteSnippet = trim($this->buildResourceActionRoute(
                        $module,
                        $definition,
                        $actionName,
                        $definition->customActionMethod($actionName),
                        $additionalMiddleware,
                        $level,
                    ));

                    $document = $this->routeSynchronizer->insertAfterControllerRoutes(
                        $document,
                        $controllerClass,
                        $actionRouteSnippet,
                        $level,
                    );

                    $added[] = Str::kebab($definition->name) . '/' . Str::kebab($actionName);
                }
            }
        }

        foreach ($definition->children as $child) {
            $this->syncNodeRoutes(
                $module,
                $child,
                $definition->middleware,
                $level + 1,
                $document,
                $added,
            );
        }
    }

    protected function buildRouteFile(
        $module,
        array $definitions,
    ): string {
        $controllers = $this->collectControllers($definitions);

        $imports = [
            Route::class,
        ];

        foreach ($controllers as $definition) {
            $imports[] = $this->support->controllerNamespace($module, $definition)
                . '\\'
                . $definition->controllerClass;
        }

        $content = "<?php\n\n";
        $content .= $this->support->generatorImports('route', $imports);
        $content .= "\n\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode(
                $module,
                $definition,
                [],
                0,
            );
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
            $content .= $this->buildResourceRoute(
                $module,
                $definition,
                $additionalMiddleware,
                $level,
            );
        }

        foreach ($definition->customActions() as $actionName => $config) {
            $content .= $this->buildResourceActionRoute(
                $module,
                $definition,
                $actionName,
                $definition->customActionMethod($actionName),
                $additionalMiddleware,
                $level,
            );
        }

        if (!empty($definition->children)) {
            $segment = Str::kebab($definition->name);

            $content .= $indent . "Route::prefix('{$segment}')";

            if ($additionalMiddleware) {
                $content .= "\n"
                    . $indent
                    . '    ->middleware('
                    . $this->phpArray($additionalMiddleware)
                    . ')';
            }

            $content .= "\n"
                . $indent
                . "    ->group(function () {\n";

            foreach ($definition->children as $child) {
                $content .= $this->buildNode(
                    $module,
                    $child,
                    $definition->middleware,
                    $level + 1,
                );
            }

            $content .= $indent . "});\n\n";
        }

        return $content;
    }

    protected function buildResourceRoute(
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
            $resource .= '->only(' . $this->phpArray($actions) . ')';
        }

        $resource .= ';';

        return $this->wrapInMiddlewareGroup($resource, $middleware, $indent);
    }

    protected function buildResourceActionRoute(
        $module,
        MenuDefinition $definition,
        string $actionName,
        string $method,
        array $middleware,
        int $level,
    ): string {
        $indent = str_repeat('    ', $level);
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

        return $this->wrapInMiddlewareGroup($route, $middleware, $indent);
    }

    protected function wrapInMiddlewareGroup(
        string $routeStatement,
        array $middleware,
        string $indent,
    ): string {
        if (!$middleware) {
            return $indent . $routeStatement . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $routeStatement . "\n"
            . $indent . "});\n\n";
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

    protected function phpArray(array $values): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = array_map(
            static fn($value) => var_export($value, true),
            $values,
        );

        return '[' . implode(', ', $items) . ']';
    }
}