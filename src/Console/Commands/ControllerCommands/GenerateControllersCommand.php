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
