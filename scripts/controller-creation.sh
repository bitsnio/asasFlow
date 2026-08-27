#!/bin/bash

# Controller Generation Command Setup Script
# This script creates the complete structure for the Controller Generation command

echo "🚀 Setting up Controller Generation Command..."

# Get the script's directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Base directory for the command
BASE_DIR="$PROJECT_ROOT/src/Console/Commands/ModuleCommands"

echo "📁 Project Root: $PROJECT_ROOT"
echo "📁 Target Directory: $BASE_DIR"

# Create directory structure
mkdir -p $BASE_DIR/{Services,Contracts,Traits,Stubs}

echo "✅ Directory structure created"

# Helper function to create files
create_file() {
    local file_path="$1"
    local content="$2"
    
    if [ -f "$file_path" ]; then
        echo "⚠️  File already exists: $file_path (skipping)"
        return 0
    fi
    
    echo "$content" > "$file_path"
    echo "✅ Created: $file_path"
}

# ============================================
# 1. MAIN COMMAND
# ============================================
create_file "$BASE_DIR/GenerateControllersCommand.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands;

use Illuminate\Console\Command;
use AsasFlow\Console\Commands\ModuleCommands\Services\ControllerGenerator;
use AsasFlow\Console\Commands\ModuleCommands\Services\RouteGenerator;
use AsasFlow\Console\Commands\ModuleCommands\Services\MenuParser;
use AsasFlow\Console\Commands\ModuleCommands\Services\FileHandler;
use AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;

class GenerateControllersCommand extends Command
{
    protected $signature = "module:generate-controllers 
                            {module : The module name}
                            {--force : Force regeneration even if unchanged}
                            {--routes-only : Only regenerate routes, skip controllers}
                            {--controllers-only : Only regenerate controllers, skip routes}
                            {--dry-run : Preview what would be generated}
                            {--trace : Show trace mapping for route names}";
    
    protected $description = "Generate controllers and routes from module menu configuration with PHP 8 attributes";

    public function __construct(
        protected ControllerGenerator $controllerGenerator,
        protected RouteGenerator $routeGenerator,
        protected MenuParser $menuParser,
        protected FileHandler $fileHandler,
        protected ModuleRepositoryInterface $moduleRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $moduleName = $this->argument("module");
            $module = $this->moduleRepository->find($moduleName);

            if (!$module) {
                $this->error("Module [{$moduleName}] does not exist!");
                return 1;
            }

            $menuPath = $this->fileHandler->getMenuPath($module);
            if (!$this->fileHandler->exists($menuPath)) {
                $this->error("Menu configuration not found at: {$menuPath}");
                return 1;
            }

            $menu = require $menuPath;
            
            if (!$this->menuParser->validate($menu)) {
                $this->error("Invalid menu structure!");
                foreach ($this->menuParser->getErrors() as $error) {
                    $this->line("  - {$error}");
                }
                return 1;
            }

            $structure = $this->menuParser->parse($menu);
            $options = $this->getOptions();

            if ($this->option("trace")) {
                $this->displayRouteTrace($structure);
            }

            if ($this->option("dry-run")) {
                return $this->dryRun($module, $structure, $options);
            }

            $results = $this->generate($module, $structure, $options);
            $this->displayResults($results);
            
            return 0;

        } catch (\Exception $e) {
            $this->error("Generation failed: " . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    protected function getOptions(): array
    {
        return [
            "force" => $this->option("force"),
            "routesOnly" => $this->option("routes-only"),
            "controllersOnly" => $this->option("controllers-only"),
        ];
    }

    protected function displayRouteTrace(array $structure): void
    {
        $this->info("\n🔍 Route Name Trace:");
        $this->line("  Route names are generated deterministically and can be traced back to menu.php");
        $this->line("  Pattern: {prefix}_{hash} where hash is based on the full path");
        $this->line("");
        
        foreach ($structure["routes"] as $route) {
            $path = implode("/", $route["path_parts"]);
            $generated = $route["generated_path"] ?? $path;
            $this->line("  {$path} → {$generated}");
        }
    }

    protected function dryRun($module, array $structure, array $options): int
    {
        $this->info("🔍 Dry Run - Module: {$module->getName()}");
        $this->line("");
        
        $changes = [];
        
        if (!$options["routesOnly"] ?? false) {
            $controllerChanges = $this->controllerGenerator->preview($module, $structure, $options);
            $changes = array_merge($changes, $controllerChanges);
        }

        if (!$options["controllersOnly"] ?? false) {
            $routeChanges = $this->routeGenerator->preview($module, $structure, $options);
            $changes = array_merge($changes, $routeChanges);
        }
        
        if (empty($changes)) {
            $this->line("  No changes needed.");
            return 0;
        }
        
        foreach ($changes as $change) {
            $action = strtoupper($change["action"] ?? "CREATE");
            $icon = $action === "CREATE" ? "✨" : "🔄";
            $this->line("  {$icon} {$action}: {$change["file"]}");
        }
        
        $this->line("");
        $this->info("Total: " . count($changes) . " file(s) would be affected");
        return 0;
    }

    protected function generate($module, array $structure, array $options): array
    {
        $results = [
            "controllers" => [],
            "routes" => [],
            "warnings" => [],
        ];

        if (!$options["routesOnly"] ?? false) {
            $results["controllers"] = $this->controllerGenerator->generate($module, $structure, $options);
        }

        if (!$options["controllersOnly"] ?? false) {
            $results["routes"] = $this->routeGenerator->generate($module, $structure, $options);
        }

        return $results;
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info("✅ Generation complete!");
        
        if (!empty($results["controllers"])) {
            $this->line("\n📝 Controllers:");
            foreach ($results["controllers"] as $controller) {
                $status = $controller["action"] ?? "created";
                $icon = $status === "created" ? "✨" : ($status === "updated" ? "🔄" : "⏭️");
                $this->line("  {$icon} {$controller["name"]} ({$status})");
                if ($this->getOutput()->isVerbose()) {
                    $this->line("     {$controller["full_path"]}");
                }
            }
        }
        
        if (!empty($results["routes"])) {
            $this->line("\n🚏 Routes:");
            foreach ($results["routes"] as $route) {
                $this->line("  📄 {$route["path"]}");
            }
        }
        
        if (!empty($results["warnings"])) {
            $this->newLine();
            $this->warn("⚠️  Warnings:");
            foreach ($results["warnings"] as $warning) {
                $this->line("  - {$warning}");
            }
        }
        
        $this->newLine();
        $this->info("💡 Tip: Run \"php artisan route:cache\" to cache routes.");
    }
}'

# ============================================
# 2. ROUTE NAME GENERATOR
# ============================================
create_file "$BASE_DIR/Services/RouteNameGenerator.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Services;

use Illuminate\Support\Str;

class RouteNameGenerator
{
    protected const MAX_ROUTE_LENGTH = 64;
    protected const MAX_NESTING_LEVEL = 3;

    public function generateRoutePath(array $pathParts): string
    {
        if (count($pathParts) > self::MAX_NESTING_LEVEL) {
            $pathParts = $this->reduceNestingDepth($pathParts);
        }

        $fullPath = implode("/", array_map([Str::class, "kebab"], $pathParts));
        
        if (strlen($fullPath) <= self::MAX_ROUTE_LENGTH) {
            return $fullPath;
        }

        return $this->generateTraceablePath($pathParts);
    }

    protected function generateTraceablePath(array $pathParts): string
    {
        $prefix = implode("_", array_map(function($part) {
            return substr(Str::kebab($part), 0, 1);
        }, $pathParts));
        
        $hash = $this->generateDeterministicHash($pathParts);
        
        $path = $prefix . "_" . $hash;
        
        if (strlen($path) > self::MAX_ROUTE_LENGTH) {
            $hash = substr($hash, 0, 8);
            $path = $prefix . "_" . $hash;
        }
        
        return $path;
    }

    protected function generateDeterministicHash(array $pathParts): string
    {
        $fullPath = implode("|", array_map([Str::class, "kebab"], $pathParts));
        return substr(md5($fullPath), 0, 10);
    }

    protected function reduceNestingDepth(array $parts): array
    {
        $length = count($parts);
        if ($length <= self::MAX_NESTING_LEVEL) {
            return $parts;
        }

        $reduced = [$parts[0]];
        
        if ($length > 3) {
            $middleIndex = floor($length / 2);
            $reduced[] = $parts[$middleIndex];
        }
        
        $reduced[] = end($parts);
        
        return $reduced;
    }

    public function generateRouteName(array $pathParts): string
    {
        $fullName = implode(".", array_map([Str::class, "kebab"], $pathParts));
        
        if (strlen($fullName) <= 60) {
            return $fullName;
        }

        $prefix = implode("_", array_map(function($part) {
            return substr(Str::kebab($part), 0, 1);
        }, $pathParts));
        
        $hash = substr(md5(implode("|", $pathParts)), 0, 8);
        
        return $prefix . "_" . $hash;
    }

    public function getTraceInfo(string $generatedPath, array $pathParts): array
    {
        return [
            "generated" => $generatedPath,
            "original_parts" => $pathParts,
            "hash" => $this->generateDeterministicHash($pathParts),
            "nesting_level" => count($pathParts),
            "was_truncated" => $generatedPath !== implode("/", array_map([Str::class, "kebab"], $pathParts)),
        ];
    }
}'

# ============================================
# 3. CONTROLLER GENERATOR
# ============================================
create_file "$BASE_DIR/Services/ControllerGenerator.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

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
            implode(",", array_map(fn($m) => "'{$m}'", $middleware)) : "";

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
}'

# ============================================
# 4. ROUTE GENERATOR
# ============================================
create_file "$BASE_DIR/Services/RouteGenerator.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Services;

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
            $content .= "    Route::apiResource('{$path}', {$controller}::class);\n";
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
            return "'{$m}'";
        }, $middleware));
    }
}'

# ============================================
# 5. MENU PARSER
# ============================================
create_file "$BASE_DIR/Services/MenuParser.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Services;

use Illuminate\Support\Str;

class MenuParser
{
    protected array $errors = [];

    public function parse(array $menu): array
    {
        $structure = [
            "controllers" => [],
            "routes" => [],
            "module_name" => $menu["module"]["name"] ?? "Default"
        ];

        $moduleName = $menu["module"]["name"];
        $mainMiddleware = $menu["module"]["middleware"] ?? ["api"];

        $structure["controllers"][] = $this->parseControllerConfig(
            $moduleName,
            $mainMiddleware,
            null,
            [$moduleName]
        );

        $structure["routes"][] = $this->parseRouteConfig(
            $moduleName,
            $mainMiddleware,
            [$moduleName],
            Str::studly($moduleName) . "Controller"
        );

        foreach ($menu["module"]["sub_module"] ?? [] as $subModule) {
            $subName = $subModule["name"];
            $subMiddleware = $subModule["middleware"] ?? $mainMiddleware;
            
            $structure["controllers"][] = $this->parseControllerConfig(
                $subName,
                $subMiddleware,
                null,
                [$moduleName, $subName]
            );

            $structure["routes"][] = $this->parseRouteConfig(
                $subName,
                $subMiddleware,
                [$moduleName, $subName],
                Str::studly($subName) . "Controller",
                $subName
            );

            foreach ($subModule["actions"] ?? [] as $action) {
                $actionName = $action["name"];
                $actionMiddleware = array_merge(
                    $subMiddleware,
                    $action["middleware"] ?? []
                );
                
                $structure["controllers"][] = $this->parseControllerConfig(
                    $actionName,
                    $actionMiddleware,
                    $subName,
                    [$moduleName, $subName, $actionName]
                );

                $structure["routes"][] = $this->parseRouteConfig(
                    $actionName,
                    $actionMiddleware,
                    [$moduleName, $subName, $actionName],
                    Str::studly($actionName) . "Controller",
                    $subName
                );
            }
        }

        return $structure;
    }

    public function validate(array $menu): bool
    {
        $this->errors = [];

        if (!isset($menu["module"])) {
            $this->errors[] = "Missing 'module' key";
            return false;
        }

        if (!isset($menu["module"]["name"])) {
            $this->errors[] = "Module name is required";
        }

        if (!isset($menu["module"]["sub_module"]) || !is_array($menu["module"]["sub_module"])) {
            $this->errors[] = "Sub-modules must be an array";
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function parseControllerConfig(string $name, array $middleware, ?string $parent, array $pathParts): array
    {
        $controllerName = Str::studly($name) . "Controller";
        $controllerPath = $parent ? Str::studly($parent) . "/" . $controllerName : $controllerName;
        
        return [
            "name" => $name,
            "controller_name" => $controllerName,
            "controller_path" => $controllerPath,
            "middleware" => $middleware,
            "parent" => $parent,
            "model_name" => $parent ? Str::studly($parent) . Str::studly($name) : Str::studly($name),
            "route_parts" => $pathParts,
            "resource" => true
        ];
    }

    protected function parseRouteConfig(string $name, array $middleware, array $pathParts, string $controller, ?string $parent = null): array
    {
        return [
            "name" => $name,
            "path_parts" => $pathParts,
            "middleware" => $middleware,
            "controller" => $controller,
            "parent" => $parent,
            "action" => "apiResource"
        ];
    }
}'

# ============================================
# 6. FILE HANDLER
# ============================================
create_file "$BASE_DIR/Services/FileHandler.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Services;

use Illuminate\Support\Facades\File;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class FileHandler
{
    public function exists(string $path): bool
    {
        return File::exists($path);
    }

    public function read(string $path): string
    {
        return File::get($path);
    }

    public function writeFile(string $path, string $content, bool $force = false): void
    {
        if (!$force && $this->exists($path)) {
            return;
        }
        File::put($path, $content);
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    public function findPhpFiles(string $path): array
    {
        $files = [];
        if (!File::exists($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === "php") {
                $relativePath = str_replace($path . "/", "", $file->getPathname());
                $files[rtrim($relativePath, ".php")] = $file->getMTime();
            }
        }
        
        return $files;
    }

    public function getMenuPath($module): string
    {
        return $module->getPath() . "/config/menu.php";
    }

    public function getRoutesPath($module): string
    {
        return $module->getPath() . "/Routes/api.php";
    }

    public function getControllerPath($module, string $controllerPath): string
    {
        return $module->getPath() . "/App/Http/Controllers/" . $controllerPath . ".php";
    }
}'

# ============================================
# 7. CONTRACTS
# ============================================
create_file "$BASE_DIR/Contracts/GeneratorInterface.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Contracts;

interface GeneratorInterface
{
    public function generate($module, array $structure, array $options = []): array;
    public function preview($module, array $structure, array $options = []): array;
}'

create_file "$BASE_DIR/Contracts/ParserInterface.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Contracts;

interface ParserInterface
{
    public function parse(array $data): array;
    public function validate(array $data): bool;
}'

# ============================================
# 8. TRAITS
# ============================================
create_file "$BASE_DIR/Traits/HandlesFileOperations.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Traits;

trait HandlesFileOperations
{
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    protected function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    protected function readFile(string $path): string
    {
        return file_get_contents($path);
    }
}'

create_file "$BASE_DIR/Traits/ValidatesMenuStructure.php" '<?php

namespace AsasFlow\Console\Commands\ModuleCommands\Traits;

trait ValidatesMenuStructure
{
    protected array $validationErrors = [];

    protected function validateMenuStructure(array $menu): bool
    {
        $this->validationErrors = [];

        if (!isset($menu["module"])) {
            $this->validationErrors[] = "Missing 'module' key in menu configuration";
            return false;
        }

        if (!isset($menu["module"]["name"])) {
            $this->validationErrors[] = "Module name is required";
        }

        if (!isset($menu["module"]["sub_module"]) || !is_array($menu["module"]["sub_module"])) {
            $this->validationErrors[] = "Sub-modules must be an array";
            return false;
        }

        foreach ($menu["module"]["sub_module"] as $index => $subModule) {
            if (empty($subModule["name"])) {
                $this->validationErrors[] = "Sub-module at index {$index} has no name";
            }
        }

        return empty($this->validationErrors);
    }

    protected function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}'

# ============================================
# 9. STUBS
# ============================================
create_file "$BASE_DIR/Stubs/controller-attribute.stub" '<?php

namespace {{namespace}};

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Route;
use Illuminate\Routing\Attributes\Middleware;
{{imports}}

#[Route("{{route_path}}", name: "{{route_name}}")]
#[Middleware([{{middleware}}])]
class {{class_name}} extends Controller
{
    #[Route("", name: "index")]
    public function index()
    {
        {{index_method}}
    }

    #[Route("", name: "store")]
    public function store(Request $request)
    {
        {{store_method}}
    }

    #[Route("{id}", name: "show")]
    public function show($id)
    {
        {{show_method}}
    }

    #[Route("{id}", name: "update")]
    public function update(Request $request, $id)
    {
        {{update_method}}
    }

    #[Route("{id}", name: "destroy")]
    public function destroy($id)
    {
        {{destroy_method}}
    }
}'

create_file "$BASE_DIR/Stubs/controller-api.stub" '<?php

namespace {{namespace}};

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
{{imports}}

class {{class_name}} extends Controller
{
    public function index()
    {
        {{index_method}}
    }

    public function store(Request $request)
    {
        {{store_method}}
    }

    public function show($id)
    {
        {{show_method}}
    }

    public function update(Request $request, $id)
    {
        {{update_method}}
    }

    public function destroy($id)
    {
        {{destroy_method}}
    }
}'

create_file "$BASE_DIR/Stubs/routes-api.stub" '<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Attributes\Route as RouteAttribute;
use Illuminate\Routing\Attributes\Middleware;

{{imports}}

{{route_groups}}'

# ============================================
# 10. COMPLETION
# ============================================
echo ""
echo "✅ Controller Generation Command structure created successfully!"
echo ""
echo "📁 Location: $BASE_DIR"
echo ""
echo "📊 Structure created:"
echo "  - Commands:     " $(ls -1 $BASE_DIR/*.php 2>/dev/null | wc -l) "files"
echo "  - Services:     " $(find $BASE_DIR/Services -type f -name "*.php" | wc -l) "files"
echo "  - Contracts:    " $(find $BASE_DIR/Contracts -type f -name "*.php" | wc -l) "files"
echo "  - Traits:       " $(find $BASE_DIR/Traits -type f -name "*.php" | wc -l) "files"
echo "  - Stubs:        " $(find $BASE_DIR/Stubs -type f | wc -l) "files"
echo ""
echo "📋 Next steps:"
echo "1. Update AsasFlowServiceProvider to register the command:"
echo ""
echo "   In boot() method:"
echo "   if (\$this->app->runningInConsole()) {"
echo "       \$this->commands(["
echo "           \\AsasFlow\\Console\\Commands\\ModuleCommands\\GenerateControllersCommand::class,"
echo "       ]);"
echo "   }"
echo ""
echo "2. Run composer dump-autoload"
echo ""
echo "3. Test the command:"
echo "   php artisan module:generate-controllers YourModule --dry-run"
echo "   php artisan module:generate-controllers YourModule --trace"
echo ""
echo "🔍 The --trace option shows how route names are generated and can be traced back to menu.php"