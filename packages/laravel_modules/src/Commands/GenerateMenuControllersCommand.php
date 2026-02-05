<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class GenerateMenuControllersCommand extends Command
{
    protected $signature = 'module:generate-menu_controllers {module : The module name}';
    protected $description = 'Generate controllers based on module menu configuration';

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

        $this->generateControllers($module, $menu);
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

    protected function handleControllerGeneration($module, string $name, ?string $subFolder = null): void
    {
        $controllerName = Str::studly($name) . 'Controller';
        $subFolderStudly = $subFolder ? Str::studly($subFolder) : null;
        $controllerPath = $subFolderStudly ? "{$subFolderStudly}/{$controllerName}" : $controllerName;

        if (
            !isset($this->existingControllers[$controllerPath]) ||
            $this->existingControllers[$controllerPath] < $this->menuLastModified
        ) {
            $this->generateController($module, $name, $subFolderStudly);
            $this->info("Controller [{$controllerPath}] " .
                (isset($this->existingControllers[$controllerPath]) ? "updated" : "created") . ".");
        } else {
            $this->info("Controller [{$controllerPath}] already exists, skipping.");
        }
    }

    protected function generateController($module, string $name, ?string $subFolder = null): void
    {
        $controllerName = Str::studly($name) . 'Controller';
        $controllerPath = $subFolder ? "{$subFolder}/{$controllerName}" : $controllerName;

        $this->call('module:make-controller', [
            'controller' => $controllerPath,
            'module' => $module->getName(),
            '--api' => true
        ]);
    }
}
