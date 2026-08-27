<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services\FileHandler;

class RouteGenerator
{
    public function __construct(
        protected FileHandler $fileHandler,
        protected RouteNameGenerator $routeNameGenerator
    ) {}

    public function generate($module, array $structure, array $options = []): array
    {
        $routeFile = $module->getPath() . "/Routes/api.php";
        $content = $this->buildRouteFile($module, $structure);
        
        $this->fileHandler->ensureDirectoryExists(dirname($routeFile));
        $this->fileHandler->writeFile($routeFile, $content, true);

        return [
            [
                "path" => $routeFile,
                "action" => "created/updated"
            ]
        ];
    }

    public function preview($module, array $structure, array $options = []): array
    {
        $routeFile = $module->getPath() . "/Routes/api.php";
        $exists = $this->fileHandler->exists($routeFile);
        
        return [
            [
                "action" => $exists ? "update" : "create",
                "file" => $routeFile,
                "type" => "routes"
            ]
        ];
    }

    protected function buildRouteFile($module, array $structure): string
    {
        $content = "<?php\n\n";
        $content .= "use Illuminate\\Support\\Facades\\Route;\n";
        $content .= "use Illuminate\\Routing\\Attributes\\Route as RouteAttribute;\n";
        $content .= "use Illuminate\\Routing\\Attributes\\Middleware;\n\n";
        
        $moduleName = $module->getName();
        foreach ($structure["controllers"] as $controller) {
            $path = $controller["controller_path"];
            $content .= "use Modules\\{$moduleName}\\App\\Http\\Controllers\\{$path};\n";
        }
        
        $content .= "\n";
        
        $groups = $this->groupByMiddleware($structure["routes"]);
        foreach ($groups as $group) {
            $content .= $this->buildRouteGroup($group["middleware"], $group["routes"]);
        }
        
        return $content;
    }

    protected function groupByMiddleware(array $routes): array
    {
        $groups = [];
        
        foreach ($routes as $route) {
            $middleware = $route["middleware"] ?? ["api"];
            $key = $this->getMiddlewareKey($middleware);
            
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    "middleware" => $middleware,
                    "routes" => []
                ];
            }
            
            $route["generated_path"] = $this->routeNameGenerator->generateRoutePath($route["path_parts"] ?? []);
            $groups[$key]["routes"][] = $route;
        }
        
        return $groups;
    }

    protected function buildRouteGroup(array $middleware, array $routes): string
    {
        if (empty($routes)) {
            return "";
        }
        
        $middlewareStr = $this->formatMiddleware($middleware);
        $content = "Route::middleware([{$middlewareStr}])->group(function () {\n";
        
        foreach ($routes as $route) {
            $path = $route["generated_path"] ?? $this->routeNameGenerator->generateRoutePath($route["path_parts"] ?? []);
            $controller = $route["controller"];
            $content .= "    Route::apiResource({}, {$controller}::class);\n";
        }
        
        $content .= "});\n\n";
        return $content;
    }

    protected function getMiddlewareKey(array $middleware): string
    {
        sort($middleware);
        return implode(":", $middleware);
    }

    protected function formatMiddleware(array $middleware): string
    {
        return implode(",", array_map(function($m) {
            return "{}";
        }, $middleware));
    }
}
