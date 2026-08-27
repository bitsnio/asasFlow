<?php

namespace AsasFlow\Features\ControllerGeneration\Generators\Route;

use AsasFlow\Features\ControllerGeneration\Contracts\RouteGeneratorInterface;
use AsasFlow\Features\ControllerGeneration\Services\FileHandlerService;
use AsasFlow\Features\ControllerGeneration\Services\RouteNameTruncator;

class RouteWithAttributesGenerator implements RouteGeneratorInterface
{
    public function __construct(
        protected FileHandlerService $fileHandler,
        protected RouteNameTruncator $truncator
    ) {}

    public function generate($module, array $structure, array $options = []): array
    {
        $routeFile = $module->getPath() . '/Routes/api.php';
        $content = $this->buildRouteFile($module, $structure);
        
        $this->fileHandler->ensureDirectoryExists(dirname($routeFile));
        $this->fileHandler->write($routeFile, $content, true);

        return [
            [
                'path' => $routeFile,
                'action' => 'created/updated'
            ]
        ];
    }

    public function preview($module, array $structure, array $options = []): array
    {
        $routeFile = $module->getPath() . '/Routes/api.php';
        $exists = $this->fileHandler->exists($routeFile);
        
        return [
            [
                'action' => $exists ? 'update' : 'create',
                'file' => $routeFile,
                'type' => 'routes'
            ]
        ];
    }

    protected function buildRouteFile($module, array $structure): string
    {
        $content = "<?php\n\n";
        $content .= "use Illuminate\\Support\\Facades\\Route;\n";
        $content .= "use Illuminate\\Routing\\Attributes\\Route as RouteAttribute;\n";
        $content .= "use Illuminate\\Routing\\Attributes\\Middleware;\n\n";
        
        // Group routes by middleware
        $groups = $this->groupByMiddleware($structure['routes']);
        
        // Add imports
        foreach ($structure['controllers'] as $controller) {
            $moduleName = $module->getName();
            $path = $controller['controller_path'];
            $content .= "use Modules\\{$moduleName}\\App\\Http\\Controllers\\{$path};\n";
        }
        
        $content .= "\n";
        
        foreach ($groups as $middleware => $routes) {
            $content .= $this->buildRouteGroup($middleware, $routes);
        }
        
        return $content;
    }

    protected function groupByMiddleware(array $routes): array
    {
        $groups = [];
        
        foreach ($routes as $route) {
            $middleware = $route['middleware'] ?? ['api'];
            $key = $this->getMiddlewareKey($middleware);
            
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'middleware' => $middleware,
                    'routes' => []
                ];
            }
            
            // Truncate route path if needed
            $routePath = $this->truncator->truncateRouteName($route['path_parts'] ?? []);
            $routeName = $this->truncator->generateRouteName($route['path_parts'] ?? []);
            
            $groups[$key]['routes'][] = [
                'path' => $routePath,
                'name' => $routeName,
                'controller' => $route['controller'],
                'action' => $route['action'] ?? 'apiResource'
            ];
        }
        
        return $groups;
    }

    protected function buildRouteGroup(array $middleware, array $group): string
    {
        $middlewareStr = implode("', '", $middleware);
        $content = "Route::middleware(['{$middlewareStr}'])->group(function () {\n";
        
        foreach ($group['routes'] as $route) {
            $content .= "    Route::apiResource('{$route['path']}', {$route['controller']}::class);\n";
        }
        
        $content .= "});\n\n";
        
        return $content;
    }

    protected function getMiddlewareKey(array $middleware): string
    {
        sort($middleware);
        return implode(':', $middleware);
    }
}
