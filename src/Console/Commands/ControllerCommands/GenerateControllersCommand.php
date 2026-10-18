<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Bitsnio\AsasFlow\Generators\Route\RouteGenerator;
use Bitsnio\AsasFlow\Generators\Schema\SchemaGenerator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class GenerateControllersCommand extends Command
{
    protected $signature = 'asasflow:generate-controllers
        {module : The module name}
        {--only=all : Artifact to generate: all, controllers, requests, resources, schemas, routes}
        {--force : Force regeneration}
        {--routes-only : Only generate routes}
        {--controllers-only : Only generate controllers}
        {--requests-only : Only generate requests}
        {--resources-only : Only generate resources}
        {--schemas-only : Only generate schemas}
        {--trace : Show route hierarchy}';

    protected $description = 'Generate controllers, requests, resources, schemas and API routes recursively from module menu configuration.';

    /**
     * @var array<string, GeneratorInterface>
     */
    protected array $generators = [];

    public function __construct(
        ControllerGenerator $controllerGenerator,
        RequestGenerator $requestGenerator,
        ResourceGenerator $resourceGenerator,
        SchemaGenerator $schemaGenerator,
        RouteGenerator $routeGenerator,
        protected MenuBuilder $definitionBuilder,
        protected FileHandler $files,
        protected ModuleRepositoryInterface $moduleRepository,
    ) {
        parent::__construct();

        $this->registerGenerators([
            $controllerGenerator,
            $requestGenerator,
            $resourceGenerator,
            $schemaGenerator,
            $routeGenerator,
        ]);
    }

    /**
     * @param array<GeneratorInterface> $generators
     */
    protected function registerGenerators(array $generators): void
    {
        foreach ($generators as $generator) {
            $this->generators[$generator->key()] = $generator;
        }
    }

    public function handle(): int
    {
        try {
            $moduleName = (string) $this->argument('module');
            $module = $this->moduleRepository->find($moduleName);

            if (!$module) {
                $this->error("Module [{$moduleName}] does not exist!");

                return self::FAILURE;
            }

            $menuPath = $this->files->getMenuPath($module);

            if (!$this->files->exists($menuPath)) {
                $this->error("Menu configuration not found at: {$menuPath}");

                return self::FAILURE;
            }

            $menu = require $menuPath;
            $definitions = $this->definitionBuilder->build($menu);

            if ($this->option('trace')) {
                $this->displayTrace($definitions);
            }

            $only = $this->resolveOnlyTarget();
            $options = [
                'force' => (bool) $this->option('force'),
            ];

            $results = $this->generate(
                $module,
                $definitions,
                $only,
                $options,
            );

            $this->displayResults($results);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Generation failed: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function resolveOnlyTarget(): string
    {
        $legacyFlags = array_keys(array_filter([
            'routes' => (bool) $this->option('routes-only'),
            'controllers' => (bool) $this->option('controllers-only'),
            'requests' => (bool) $this->option('requests-only'),
            'resources' => (bool) $this->option('resources-only'),
            'schemas' => (bool) $this->option('schemas-only'),
        ]));

        if (count($legacyFlags) > 1) {
            throw new InvalidArgumentException(
                'Only one generation-only option may be used.',
            );
        }

        if (count($legacyFlags) === 1) {
            return $legacyFlags[0];
        }

        $only = strtolower(trim((string) $this->option('only')));
        if ($only === '') {
            $only = 'all';
        }

        $allowed = ['all', ...array_keys($this->generators)];

        if (!in_array($only, $allowed, true)) {
            throw new InvalidArgumentException(
                'Invalid --only value. Allowed values: ' . implode(', ', $allowed),
            );
        }

        return $only;
    }

    protected function generate(
        $module,
        array $definitions,
        string $only,
        array $options,
    ): array {
        $activeGenerators = $only === 'all'
            ? $this->generators
            : [$only => $this->generators[$only]];

        $results = [];

        foreach ($activeGenerators as $key => $generator) {
            $results[$key] = $generator->generate(
                $module,
                $definitions,
                $options,
            );
        }

        return $results;
    }

    protected function displayTrace(array $definitions): void
    {
        $this->info('Route hierarchy:');

        $this->traceNodes($definitions, 0);
    }

    protected function traceNodes(array $definitions, int $level): void
    {
        foreach ($definitions as $definition) {
            $this->line(str_repeat('  ', $level) . '- ' . $definition->routePath());

            $this->traceNodes($definition->children, $level + 1);
        }
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Generation complete.');

        foreach ($results as $type => $items) {
            if (empty($items)) {
                continue;
            }

            if ($type === 'routes') {
                foreach ($items as $route) {
                    $this->line("\nRoutes: {$route['action']} {$route['path']}");
                }

                continue;
            }

            $this->line("\n" . ucfirst($type) . ':');

            foreach ($items as $result) {
                $this->line("  {$result['action']}: " . ($result['name'] ?? ''));

                if ($this->getOutput()->isVerbose() && !empty($result['full_path'])) {
                    $this->line("    {$result['full_path']}");
                }
            }
        }
    }
}