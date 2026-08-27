<?php

namespace AsasFlow\Features\ControllerGeneration\Commands;

use Illuminate\Console\Command;
use AsasFlow\Features\ControllerGeneration\Services\ControllerGenerationService;
use AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;

class GenerateControllersCommand extends Command
{
    protected $signature = 'module:generate-controllers 
                            {module : The module name}
                            {--force : Force regeneration even if unchanged}
                            {--routes-only : Only regenerate routes, skip controllers}
                            {--controllers-only : Only regenerate controllers, skip routes}
                            {--dry-run : Preview what would be generated}';
    
    protected $description = 'Generate controllers and routes from module menu configuration with PHP 8 attributes';

    public function __construct(
        protected ControllerGenerationService $generationService,
        protected ModuleRepositoryInterface $moduleRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $moduleName = $this->argument('module');
            $module = $this->moduleRepository->find($moduleName);

            if (!$module) {
                $this->error("Module [{$moduleName}] does not exist!");
                return 1;
            }

            $options = $this->getOptions();

            if ($this->option('dry-run')) {
                return $this->dryRun($module, $options);
            }

            $result = $this->generationService->generate($module, $options);
            $this->displayResults($result);
            
            return 0;

        } catch (\Exception $e) {
            $this->error('Generation failed: ' . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }

    protected function getOptions(): array
    {
        return [
            'force' => $this->option('force'),
            'routesOnly' => $this->option('routes-only'),
            'controllersOnly' => $this->option('controllers-only'),
        ];
    }

    protected function dryRun($module, array $options): int
    {
        $this->info("🔍 Dry Run - Module: {$module->getName()}");
        $this->line("Would generate:");
        
        $changes = $this->generationService->previewChanges($module, $options);
        
        if (empty($changes)) {
            $this->line("  No changes needed.");
            return 0;
        }
        
        foreach ($changes as $change) {
            $action = strtoupper($change['action'] ?? 'CREATE');
            $this->line("  {$action}: {$change['file']}");
        }
        
        $this->info("Total: " . count($changes) . " file(s) would be affected");
        return 0;
    }

    protected function displayResults(array $result): void
    {
        $this->newLine();
        $this->info('✅ Generation complete!');
        
        if (!empty($result['controllers'])) {
            $this->line("\n📝 Controllers:");
            foreach ($result['controllers'] as $controller) {
                $status = $controller['action'] ?? 'created';
                $icon = $status === 'created' ? '✨' : '🔄';
                $this->line("  {$icon} {$controller['name']} ({$status})");
                if ($this->getOutput()->isVerbose()) {
                    $this->line("     {$controller['full_path']}");
                }
            }
        }
        
        if (!empty($result['routes'])) {
            $this->line("\n🚏 Routes:");
            foreach ($result['routes'] as $route) {
                $this->line("  📄 {$route['path']}");
            }
        }
        
        if (!empty($result['errors'])) {
            $this->newLine();
            $this->error('⚠️  Warnings:');
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }
        
        $this->newLine();
        $this->info('💡 Tip: Run "php artisan route:cache" to cache routes.');
    }
}
