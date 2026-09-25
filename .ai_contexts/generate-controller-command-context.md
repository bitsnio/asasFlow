# Laravel Project Context

**Task:** Generate Controller Command Context

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
src/Console/Commands/ControllerCommands
src/Console/Commands/ControllerCommands/Contracts
src/Console/Commands/ControllerCommands/Services
src/Console/Commands/ControllerCommands/Services/Parsers
```

---

## Project Files

### src/Console/Commands/ControllerCommands/Contracts/GeneratorInterface.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Contracts;

interface GeneratorInterface
{
    public function generate($module, array $structure, array $options = []): array;
    public function preview($module, array $structure, array $options = []): array;
}

```

### src/Console/Commands/ControllerCommands/Contracts/ParserInterface.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Contracts;

interface ParserInterface
{
    public function parse(array $data): array;
    public function validate(array $data): bool;
}

```

### src/Console/Commands/ControllerCommands/GenerateControllersCommand.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Illuminate\Console\Command;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Controller\RouteGenerator;
use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services\Parsers\MenuParser;
use Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services\FileHandler;
use Bitsnio\Modules\Contracts\RepositoryInterface;


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
        protected RepositoryInterface $moduleRepository
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
}

```

### src/Console/Commands/ControllerCommands/Services/FileHandler.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services;

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
}

```

### src/Console/Commands/ControllerCommands/Services/Parsers/MenuParser.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Services\Parsers;

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
            $this->errors[] = "Missing module key";
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
}

```

