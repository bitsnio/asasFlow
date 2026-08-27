<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services\FileHandler;

class ControllerGenerator
{
    protected $existingControllers = [];

    public function __construct(
        protected FileHandler $fileHandler,
        protected RouteNameGenerator $routeNameGenerator
    ) {}

    public function generate($module, array $structure, array $options = []): array
    {
        $results = [];
        $this->loadExistingControllers($module);

        foreach ($structure["controllers"] as $config) {
            $result = $this->generateController($module, $config, $options);
            $results[] = $result;
        }

        return $results;
    }

    public function preview($module, array $structure, array $options = []): array
    {
        $changes = [];
        $this->loadExistingControllers($module);

        foreach ($structure["controllers"] as $config) {
            $path = $this->getControllerPath($module, $config["controller_path"]);
            $exists = $this->fileHandler->exists($path);
            
            $changes[] = [
                "action" => $exists ? "update" : "create",
                "file" => $path,
                "type" => "controller",
                "name" => $config["controller_name"]
            ];
        }

        return $changes;
    }

    protected function generateController($module, array $config, array $options): array
    {
        $controllerName = $config["controller_name"];
        $controllerPath = $config["controller_path"];
        $fullPath = $this->getControllerPath($module, $controllerPath);
        
        $action = "created";
        $skip = false;

        if (!$options["force"] ?? false) {
            if (isset($this->existingControllers[$controllerPath])) {
                $action = "skipped";
                $skip = true;
            }
        }

        if (!$skip) {
            $this->createControllerFile($module, $config, $fullPath);
            $action = isset($this->existingControllers[$controllerPath]) ? "updated" : "created";
        }

        return [
            "name" => $controllerName,
            "path" => $controllerPath,
            "action" => $action,
            "full_path" => $fullPath
        ];
    }

    protected function createControllerFile($module, array $config, string $path): void
    {
        $content = $this->buildControllerContent($module, $config);
        $this->fileHandler->ensureDirectoryExists(dirname($path));
        $this->fileHandler->writeFile($path, $content, true);
    }

    protected function buildControllerContent($module, array $config): string
    {
        $namespace = $this->buildNamespace($module, $config);
        $className = $config["controller_name"];
        $routePath = $this->routeNameGenerator->generateRoutePath($config["route_parts"] ?? []);
        $routeName = $this->routeNameGenerator->generateRouteName($config["route_parts"] ?? []);
        
        $middleware = $config["middleware"] ?? [];
        $imports = $this->buildImports($module, $config);
        $methods = $this->buildMethods($module, $config);

        $middlewareStr = !empty($middleware) ? 
            implode(",", array_map(fn($m) => "{}", $middleware)) : "";

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Route;
use Illuminate\Routing\Attributes\Middleware;
{$imports}

#[Route("{$routePath}", name: "{$routeName}")]
#[Middleware([{$middlewareStr}])]
class {$className} extends Controller
{
    #[Route("", name: "index")]
    public function index()
    {
        {$methods["index"]}
    }

    #[Route("", name: "store")]
    public function store(Request \$request)
    {
        {$methods["store"]}
    }

    #[Route("{id}", name: "show")]
    public function show(\$id)
    {
        {$methods["show"]}
    }

    #[Route("{id}", name: "update")]
    public function update(Request \$request, \$id)
    {
        {$methods["update"]}
    }

    #[Route("{id}", name: "destroy")]
    public function destroy(\$id)
    {
        {$methods["destroy"]}
    }
}
PHP;
    }

    protected function buildNamespace($module, array $config): string
    {
        $namespace = "Modules\\{$module->getName()}\\App\\Http\\Controllers";
        if (!empty($config["parent"])) {
            $namespace .= "\\" . Str::studly($config["parent"]);
        }
        return $namespace;
    }

    protected function buildImports($module, array $config): string
    {
        $imports = [];
        $modelClass = $config["model_name"] ?? $config["name"];
        $moduleName = $module->getName();
        
        $modelPath = $module->getPath() . "/App/Models/{$modelClass}.php";
        if (File::exists($modelPath)) {
            $imports[] = "use Modules\\{$moduleName}\\App\\Models\\{$modelClass};";
        }
        
        $requestPath = $module->getPath() . "/App/Http/Requests/{$modelClass}Request.php";
        if (File::exists($requestPath)) {
            $imports[] = "use Modules\\{$moduleName}\\App\\Http\\Requests\\{$modelClass}Request;";
        }
        
        $resourcePath = $module->getPath() . "/App/Http/Resources/{$modelClass}Resource.php";
        if (File::exists($resourcePath)) {
            $imports[] = "use Modules\\{$moduleName}\\App\\Http\\Resources\\{$modelClass}Resource;";
        }
        
        return implode("\n", $imports);
    }

    protected function buildMethods($module, array $config): array
    {
        $modelClass = $config["model_name"] ?? $config["name"];
        $variableName = Str::camel($modelClass);
        
        $hasModel = File::exists($module->getPath() . "/App/Models/{$modelClass}.php");
        $hasResource = File::exists($module->getPath() . "/App/Http/Resources/{$modelClass}Resource.php");
        
        $indexMethod = $hasModel ? 
            "return {$modelClass}::paginate();" : 
            "return response()->json([]);";
        
        $storeMethod = $hasModel ? 
            "\$item = {$modelClass}::create(\$request->validated());\n        " . 
            ($hasResource ? "return new {$modelClass}Resource(\$item);" : "return \$item;") :
            "return response()->json([\"message\" => \"Store not implemented\"]);";
        
        $showMethod = $hasModel ?
            ($hasResource ? "return new {$modelClass}Resource(\${$variableName});" : "return \${$variableName};") :
            "return response()->json([\"message\" => \"Show not implemented\"]);";
        
        $updateMethod = $hasModel ?
            "\${$variableName}->update(\$request->validated());\n        " . 
            ($hasResource ? "return new {$modelClass}Resource(\${$variableName});" : "return \${$variableName};") :
            "return response()->json([\"message\" => \"Update not implemented\"]);";
        
        $destroyMethod = $hasModel ?
            "\${$variableName}->delete();\n        return response()->noContent();" :
            "return response()->json([\"message\" => \"Destroy not implemented\"]);";
        
        return [
            "index" => $indexMethod,
            "store" => $storeMethod,
            "show" => $showMethod,
            "update" => $updateMethod,
            "destroy" => $destroyMethod,
        ];
    }

    protected function getControllerPath($module, string $path): string
    {
        return $module->getPath() . "/App/Http/Controllers/" . $path . ".php";
    }

    protected function loadExistingControllers($module): void
    {
        $path = $module->getPath() . "/App/Http/Controllers";
        $this->existingControllers = $this->fileHandler->findPhpFiles($path);
    }
}
