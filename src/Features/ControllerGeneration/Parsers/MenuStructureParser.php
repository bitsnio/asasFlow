<?php

namespace AsasFlow\Features\ControllerGeneration\Parsers;

use AsasFlow\Features\ControllerGeneration\Contracts\MenuParserInterface;
use Illuminate\Support\Str;

class MenuStructureParser implements MenuParserInterface
{
    public function parse(array $menu): array
    {
        $structure = [
            'controllers' => [],
            'routes' => [],
            'module_name' => $menu['module']['name'] ?? 'Default'
        ];

        $moduleName = $menu['module']['name'];
        $mainMiddleware = $menu['module']['middleware'] ?? ['api'];

        // Parse main module
        $structure['controllers'][] = $this->parseControllerConfig(
            $moduleName,
            $mainMiddleware,
            null,
            [$moduleName]
        );

        $structure['routes'][] = $this->parseRouteConfig(
            $moduleName,
            $mainMiddleware,
            [$moduleName],
            Str::studly($moduleName) . 'Controller'
        );

        // Parse sub-modules
        foreach ($menu['module']['sub_module'] ?? [] as $subModule) {
            $subName = $subModule['name'];
            $subMiddleware = $subModule['middleware'] ?? $mainMiddleware;
            
            $structure['controllers'][] = $this->parseControllerConfig(
                $subName,
                $subMiddleware,
                null,
                [$moduleName, $subName]
            );

            $structure['routes'][] = $this->parseRouteConfig(
                $subName,
                $subMiddleware,
                [$moduleName, $subName],
                Str::studly($subName) . 'Controller',
                $subName
            );

            // Parse actions
            foreach ($subModule['actions'] ?? [] as $action) {
                $actionName = $action['name'];
                $actionMiddleware = array_merge(
                    $subMiddleware,
                    $action['middleware'] ?? []
                );
                
                $structure['controllers'][] = $this->parseControllerConfig(
                    $actionName,
                    $actionMiddleware,
                    $subName,
                    [$moduleName, $subName, $actionName]
                );

                $structure['routes'][] = $this->parseRouteConfig(
                    $actionName,
                    $actionMiddleware,
                    [$moduleName, $subName, $actionName],
                    Str::studly($actionName) . 'Controller',
                    $subName
                );
            }
        }

        return $structure;
    }

    public function validate(array $menu): bool
    {
        if (!isset($menu['module'])) {
            return false;
        }

        if (!isset($menu['module']['name'])) {
            return false;
        }

        if (!isset($menu['module']['sub_module']) || !is_array($menu['module']['sub_module'])) {
            return false;
        }

        return true;
    }

    protected function parseControllerConfig(string $name, array $middleware, ?string $parent, array $pathParts): array
    {
        $controllerName = Str::studly($name) . 'Controller';
        $controllerPath = $parent ? Str::studly($parent) . '/' . $controllerName : $controllerName;
        
        return [
            'name' => $name,
            'controller_name' => $controllerName,
            'controller_path' => $controllerPath,
            'middleware' => $middleware,
            'parent' => $parent,
            'model_name' => $parent ? Str::studly($parent) . Str::studly($name) : Str::studly($name),
            'route_parts' => $pathParts,
            'resource' => true
        ];
    }

    protected function parseRouteConfig(string $name, array $middleware, array $pathParts, string $controller, ?string $parent = null): array
    {
        return [
            'name' => $name,
            'path_parts' => $pathParts,
            'middleware' => $middleware,
            'controller' => $controller,
            'parent' => $parent,
            'action' => 'apiResource'
        ];
    }
}
