<?php

namespace AsasFlow\Features\ControllerGeneration\Services;

use AsasFlow\Features\ControllerGeneration\Contracts\ControllerGeneratorInterface;
use AsasFlow\Features\ControllerGeneration\Contracts\RouteGeneratorInterface;
use AsasFlow\Features\ControllerGeneration\Contracts\MenuParserInterface;

class ControllerGenerationService
{
    public function __construct(
        protected ControllerGeneratorInterface $controllerGenerator,
        protected RouteGeneratorInterface $routeGenerator,
        protected MenuParserInterface $menuParser,
        protected FileHandlerService $fileHandler,
        protected RouteNameTruncator $routeNameTruncator
    ) {}

    public function generate($module, array $options = []): array
    {
        $results = [
            'controllers' => [],
            'routes' => [],
            'errors' => [],
        ];

        // Load and validate menu
        $menu = $this->loadMenu($module);
        if (!$menu) {
            return array_merge($results, ['errors' => ['Menu file not found or invalid']]);
        }

        // Parse menu structure
        $structure = $this->menuParser->parse($menu);

        // Generate controllers
        if (!$options['routesOnly'] ?? false) {
            $controllerResults = $this->controllerGenerator->generate($module, $structure, $options);
            $results['controllers'] = $controllerResults;
        }

        // Generate routes
        if (!$options['controllersOnly'] ?? false) {
            $routeResults = $this->routeGenerator->generate($module, $structure, $options);
            $results['routes'] = $routeResults;
        }

        return $results;
    }

    public function previewChanges($module, array $options = []): array
    {
        $changes = [];
        $menu = $this->loadMenu($module);
        
        if (!$menu) {
            return $changes;
        }

        $structure = $this->menuParser->parse($menu);
        
        if (!$options['routesOnly'] ?? false) {
            $controllerChanges = $this->controllerGenerator->preview($module, $structure, $options);
            $changes = array_merge($changes, $controllerChanges);
        }

        if (!$options['controllersOnly'] ?? false) {
            $routeChanges = $this->routeGenerator->preview($module, $structure, $options);
            $changes = array_merge($changes, $routeChanges);
        }

        return $changes;
    }

    protected function loadMenu($module): ?array
    {
        $path = $module->getPath() . '/config/menu.php';
        if (!$this->fileHandler->exists($path)) {
            return null;
        }
        return require $path;
    }
}
