<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class MakeMenuControllerCommand extends Command
{
    protected $signature = 'module:make-menu_controllers {module : The module name}';
    protected $description = 'Generate or update controllers and API routes based on module menu configuration';

    protected $repository;
    protected $existingControllers = [];
    protected $menuLastModified;

    public function __construct(RepositoryInterface $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    public function handle(): void
    {
        $moduleName = $this->argument('module');
        $module = $this->repository->find($moduleName);

        if (!$module) {
            $this->error("Module [{$moduleName}] does not exist!");
            return;
        }

        $menuPath = $module->getPath() . '/config/menu.php';
        if (!file_exists($menuPath)) {
            $this->error("Menu configuration not found for module [{$moduleName}]!");
            return;
        }

        $this->menuLastModified = filemtime($menuPath);
        $this->loadExistingControllers($module);

        $menu = require $menuPath;

        // if (!$this->validateGeneratedFiles($module, $menu)) {
        //     $this->error('Missing model, migration, or API resources. Run module:make-from-schema first.');
        //     return;
        // }

        $this->generateControllers($module, $menu);
        $this->generateApiRoutes($module, $menu);
    }

    protected function validateGeneratedFiles($module, array $menu): bool
    {
        $required = [];
        
        foreach ($menu['module']['sub_module'] as $sub) {
            $subName = Str::studly($sub['name']);
            
            // Only require files if model is true for the submodule
            if ($sub['model'] === true) {
                $required[] = $subName;
            }
    
            // If actions exist, check each action
            if (!empty($sub['actions'])) {
                foreach ($sub['actions'] as $action) {
                    // Only require files if model is true for the action
                    if (($action['model'] ?? false) === true) {
                        $actionName = $subName . Str::studly($action['name']);
                        $required[] = $actionName;
                    }
                }
            }
        }
    
        // Check all required files exist
        foreach ($required as $name) {
            $modelPath = $module->getPath() . "/App/Models/{$name}.php";
            $requestPath = $module->getPath() . "/App/Http/Requests/{$name}Request.php";
            $resourcePath = $module->getPath() . "/App/Http/Resources/{$name}Resource.php";
    
            if (!file_exists($modelPath) || !file_exists($requestPath) || !file_exists($resourcePath)) {
                $this->error("Missing required files for: {$name}");
                $this->line("Expected:");
                $this->line("- {$modelPath}");
                $this->line("- {$requestPath}");
                $this->line("- {$resourcePath}");
                return false;
            }
        }
    
        return true;
    }

    protected function generateControllers($module, array $menu): void
    {
        $this->handleControllerGeneration($module, $this->argument('module'));

        foreach ($menu['module']['sub_module'] as $subModule) {
            $this->handleControllerGeneration($module, $subModule['name']);

            if (isset($subModule['actions']) && is_array($subModule['actions'])) {
                foreach ($subModule['actions'] as $action) {
                    $this->handleControllerGeneration($module, $action['name'], $subModule['name']);
                }
            }
        }
    }

    protected function generateApiRoutes($module, array $menu): void
    {
        $routeGroups = [];
        $mainMiddleware = $menu['module']['middleware'] ?? [];
        $middlewareKey = !empty($mainMiddleware) ? $this->getMiddlewareKey($mainMiddleware) : "no_middleware";

        $routeGroups[$middlewareKey] = [
            'middleware' => $mainMiddleware,
            'routes' => [$this->generateResourceRoute($this->argument('module'))]
        ];

        foreach ($menu['module']['sub_module'] as $subModule) {
            $middlewareKey = !empty($subModule['middleware']) ? $this->getMiddlewareKey($subModule['middleware']) : "no_middleware";

            if (!isset($routeGroups[$middlewareKey])) {
                $routeGroups[$middlewareKey] = [
                    'middleware' => $subModule['middleware'] ?? [],
                    'routes' => []
                ];
            }

            $routeGroups[$middlewareKey]['routes'][] = $this->generateResourceRoute($subModule['name']);

            if (isset($subModule['actions']) && is_array($subModule['actions'])) {
                foreach ($subModule['actions'] as $action) {
                    $actionMiddlewareKey = !empty($action['middleware']) ? $this->getMiddlewareKey($action['middleware']) : "no_middleware";

                    if (!isset($routeGroups[$actionMiddlewareKey])) {
                        $routeGroups[$actionMiddlewareKey] = [
                            'middleware' => $action['middleware'] ?? [],
                            'routes' => []
                        ];
                    }

                    $routeGroups[$actionMiddlewareKey]['routes'][] =
                        $this->generateResourceRoute($action['name'], $subModule['name']);
                }
            }
        }

        if (!empty($routeGroups)) {
            $this->saveOptimizedRoutes($module, $routeGroups);
        }
    }

    protected function loadExistingControllers($module): void
    {
        $controllersPath = $module->getPath() . '/App/Http/Controllers';
        if (!file_exists($controllersPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersPath));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($controllersPath . '/', '', $file->getPathname());
                $this->existingControllers[rtrim($relativePath, '.php')] = $file->getMTime();
            }
        }
    }

    protected function handleControllerGeneration($module, string $name, ?string $parentName = null): void
    {
        $controllerName = Str::studly($name) . 'Controller';
        $controllerPath = $parentName ? Str::studly($parentName) . '/' . $controllerName : $controllerName;
    
        if (!isset($this->existingControllers[$controllerPath])) {
            $this->generateController($module, $name, $parentName);
            $this->info("Controller [{$controllerPath}] created.");
        // } elseif ($this->existingControllers[$controllerPath] < $this->menuLastModified) {
        //     $this->generateController($module, $name, $parentName);
        //     $this->info("Controller [{$controllerPath}] updated.");
        } 
        else {
            $this->info("Controller [{$controllerPath}] already exists, skipping.");
        }
    }

    protected function generateController($module, string $name, ?string $parentName = null): void
    {
        $controllerName = Str::studly($name) . 'Controller';
        $modelName = $this->getModelName($name, $parentName);
        $controllerPath = $parentName ? Str::studly($parentName) . '/' . $controllerName : $controllerName;
    
        // Generate the basic controller first
        $this->call('module:make-controller', [
            'controller' => $controllerPath,
            'module' => $module->getName(),
            '--api' => true
        ]);
    
        // Now enhance it with proper resource and request references
        $fullControllerPath = $module->getPath() . '/App/Http/Controllers/' . $controllerPath . '.php';
        
        // Prepare the class names without full namespace
        $modelClass = $this->getModelName($name, $parentName);
        $requestClass = $modelClass . 'Request';
        $resourceClass = $modelClass . 'Resource';
        $collectionClass = $modelClass . 'Collection';
    
        // Check if files exist before including them
        $modelExists = File::exists($module->getPath() . "/App/Models/{$modelClass}.php");
        $requestExists = File::exists($module->getPath() . "/App/Http/Requests/{$requestClass}.php");
        $resourceExists = File::exists($module->getPath() . "/App/Http/Resources/{$resourceClass}.php");
        $collectionExists = File::exists($module->getPath() . "/App/Http/Resources/{$collectionClass}.php");
    
        // Prepare imports and type hints based on what exists
        $imports = [];
        $typeHints = [];
        
        if ($modelExists) {
            $fullModelClass = "Modules\\{$module->getName()}\\App\\Models\\{$modelClass}";
            $imports[] = "use {$fullModelClass};";
            $typeHints['model'] = $modelClass;
        } else {
            $typeHints['model'] = 'mixed'; // Fallback type if model doesn't exist
        }
    
        if ($requestExists) {
            $fullRequestClass = "Modules\\{$module->getName()}\\App\\Http\\Requests\\{$requestClass}";
            $imports[] = "use {$fullRequestClass};";
            $typeHints['request'] = $requestClass;
        } else {
            $typeHints['request'] = 'Request'; // Fallback to basic Request
            $imports[] = "use Illuminate\\Http\\Request;";
        }
    
        if ($resourceExists) {
            $fullResourceClass = "Modules\\{$module->getName()}\\App\\Http\\Resources\\{$resourceClass}";
            $imports[] = "use {$fullResourceClass};";
            $typeHints['resource'] = $resourceClass;
        }
    
        if ($collectionExists) {
            $fullCollectionClass = "Modules\\{$module->getName()}\\App\\Http\\Resources\\{$collectionClass}";
            $imports[] = "use {$fullCollectionClass};";
            $typeHints['collection'] = $collectionClass;
        }
    
        // Get the namespace for the controller
        $controllerNamespace = "Modules\\{$module->getName()}\\App\\Http\\Controllers";
        if ($parentName) {
            $controllerNamespace .= "\\" . Str::studly($parentName);
        }
    
        // Build controller content with fallbacks
        $controllerContent = <<<PHP
            <?php
            
            namespace {$controllerNamespace};
            
            use Illuminate\Routing\Controller;
            {$this->formatImports($imports)}
            
            class {$controllerName} extends Controller
            {
                public function index()
                {
                    {$this->buildIndexMethod($typeHints)}
                }
            
                public function store({$typeHints['request']} \$request)
                {
                    {$this->buildStoreMethod($typeHints)}
                }
            
                public function show({$typeHints['model']} \${$this->getVariableName($modelClass)})
                {
                    {$this->buildShowMethod($typeHints, $modelClass)}
                }
            
                public function update({$typeHints['request']} \$request, {$typeHints['model']} \${$this->getVariableName($modelClass)})
                {
                    {$this->buildUpdateMethod($typeHints, $modelClass)}
                }
            
                public function destroy({$typeHints['model']} \${$this->getVariableName($modelClass)})
                {
                    {$this->buildDestroyMethod($modelClass)}
                }
            }
            PHP;
    
        File::put($fullControllerPath, $controllerContent);
    }
    
    // Helper methods for building controller parts
    protected function formatImports(array $imports): string
    {
        return implode("\n", array_unique($imports));
    }
    
    protected function buildIndexMethod(array $typeHints): string
    {
        if (isset($typeHints['collection']) && isset($typeHints['model'])) {
            return "return new {$typeHints['collection']}({$typeHints['model']}::paginate());";
        }
        return "// Collection or model not available - implement manually";
    }
    
    protected function buildStoreMethod(array $typeHints): string
    {
        if (isset($typeHints['resource']) && isset($typeHints['model'])) {
            return "\$item = {$typeHints['model']}::create(\$request->validated());\n        return new {$typeHints['resource']}(\$item);";
        }
        return "// Resource or model not available - implement manually";
    }
    
    protected function buildShowMethod(array $typeHints, string $modelClass): string
    {
        if (isset($typeHints['resource'])) {
            return "return new {$typeHints['resource']}(\${$this->getVariableName($modelClass)});";
        }
        return "return \${$this->getVariableName($modelClass)};";
    }
    
    protected function buildUpdateMethod(array $typeHints, string $modelClass): string
    {
        if (isset($typeHints['resource'])) {
            return "\${$this->getVariableName($modelClass)}->update(\$request->validated());\n        return new {$typeHints['resource']}(\${$this->getVariableName($modelClass)});";
        }
        return "\${$this->getVariableName($modelClass)}->update(\$request->all());\n        return \${$this->getVariableName($modelClass)};";
    }
    
    protected function buildDestroyMethod(string $modelClass): string
    {
        return "\${$this->getVariableName($modelClass)}->delete();\n        return response()->noContent();";
    }

    protected function getModelName(string $name, ?string $parentName = null): string
    {
        if ($parentName) {
            return Str::studly($parentName) . Str::studly($name);
        }
        return Str::studly($name);
    }



    protected function getNamespaceFromPath(string $path): string
    {
        $parts = explode('/', $path);
        array_pop($parts); // Remove controller name

        if (empty($parts)) {
            return '';
        }

        return '\\' . implode('\\', $parts);
    }

    protected function getVariableName(string $modelName): string
    {
        return Str::camel($modelName);
    }

    protected function generateResourceRoute(string $name, ?string $parentName = null): string
    {
        $routeName = Str::kebab($name);        
        $parentPath = $parentName ? Str::kebab($parentName) : '';
        $modulePrefix = strtolower($this->argument('module'));

        $routePath = $routeName === $modulePrefix && !$parentName
            ? $modulePrefix
            : $modulePrefix . '_' . ($parentPath ? $parentPath . '_' . $routeName : $routeName);

        $controllerName = Str::studly($name) . 'Controller';
        $controllerPath = $parentName
            ? Str::studly($parentName) . '\\' . $controllerName
            : $controllerName;

        $controllerClass = "Modules\\" . $this->argument('module') . "\\App\\Http\\Controllers\\" . $controllerPath;

        return "    Route::apiResource('{$routePath}', {$controllerClass}::class);";
    }

    protected function getMiddlewareKey(array $middleware): string
    {
        sort($middleware);
        return implode(':', $middleware);
    }

    protected function saveOptimizedRoutes($module, array $groups): void
    {
        $routePath = $module->getPath() . "/Routes/api.php";
        $content = "<?php\n\n";
        $content .= "use Illuminate\\Support\\Facades\\Route;\n";

        $controllers = [];

        foreach ($groups as $key => $group) {
            $groups[$key]['resources'] = [];

            foreach ($group['routes'] as $route) {
                preg_match("/Route::apiResource\\('(.+?)', (.+?)::class\\);/", $route, $matches);

                if (count($matches) >= 3) {
                    $path = $matches[1];
                    $controllerClass = $matches[2];
                    $controllers[] = $controllerClass;
                    $classParts = explode('\\', $controllerClass);
                    $className = end($classParts);
                    $groups[$key]['resources'][$path] = "$className::class";
                }
            }
        }

        foreach (array_unique($controllers) as $controller) {
            $content .= "use $controller;\n";
        }

        $content .= "\n";

        foreach ($groups as $key => $group) {
            if (empty($group['resources'])) {
                continue;
            }

            if ($key === "no_middleware") {
                $content .= "Route::apiResources([\n";
                foreach ($group['resources'] as $path => $controller) {
                    $content .= "    '$path' => $controller,\n";
                }
                $content .= "]);\n\n";
            } else {
                $middlewareString = implode("', '", $group['middleware']);
                $content .= "Route::middleware(['{$middlewareString}'])->group(function () {\n";
                $content .= "    Route::apiResources([\n";
                foreach ($group['resources'] as $path => $controller) {
                    $content .= "        '$path' => $controller,\n";
                }
                $content .= "    ]);\n";
                $content .= "});\n\n";
            }
        }

        file_put_contents($routePath, $content);
    }
}
