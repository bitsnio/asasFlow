<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Bitsnio\AsasFlow\Generators\Route\RouteGenerator;
use Bitsnio\AsasFlow\Generators\Schema\SchemaGenerator;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

class GenerateControllersCommand extends Command
{
    protected $signature =
        'asasflow:generate-controllers
        {module : The module name}
        {--force : Force regeneration}
        {--routes-only : Only generate routes}
        {--controllers-only : Only generate controllers}
        {--requests-only : Only generate requests}
        {--resources-only : Only generate resources}
        {--schemas-only : Only generate schemas}
        {--dry-run : Preview generated files}
        {--trace : Show route hierarchy}';

    protected $description =
        'Generate controllers, requests, resources, schemas and API routes recursively from module menu configuration.';

    public function __construct(
        protected ControllerGenerator $controllerGenerator,
        protected RequestGenerator $requestGenerator,
        protected ResourceGenerator $resourceGenerator,
        protected SchemaGenerator $schemaGenerator,
        protected RouteGenerator $routeGenerator,
        protected MenuBuilder $definitionBuilder,
        protected FileHandler $files,
        protected RepositoryInterface $moduleRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $moduleName =
                $this->argument('module');

            $module =
                $this->moduleRepository
                    ->find($moduleName);

            if (!$module) {
                $this->error(
                    "Module [{$moduleName}] does not exist!"
                );

                return self::FAILURE;
            }

            $menuPath =
                $this->files->getMenuPath(
                    $module
                );

            if (!$this->files->exists($menuPath)) {
                $this->error(
                    "Menu configuration not found at: {$menuPath}"
                );

                return self::FAILURE;
            }

            $menu =
                require $menuPath;

            $definitions =
                $this->definitionBuilder
                    ->build($menu);

            $options =
                $this->getOptions();

            if ($this->option('trace')) {
                $this->displayTrace(
                    $definitions
                );
            }

            if ($this->option('dry-run')) {
                $this->displayPreview(
                    $module,
                    $definitions,
                    $options
                );

                return self::SUCCESS;
            }

            $results =
                $this->generate(
                    $module,
                    $definitions,
                    $options
                );

            $this->displayResults(
                $results
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Generation failed: '
                . $e->getMessage()
            );

            if (
                $this->getOutput()
                    ->isVerbose()
            ) {
                $this->line(
                    $e->getTraceAsString()
                );
            }

            return self::FAILURE;
        }
    }

    protected function getOptions(): array
    {
        return [
            'force' =>
                (bool) $this->option('force'),

            'routesOnly' =>
                (bool) $this->option(
                    'routes-only'
                ),

            'controllersOnly' =>
                (bool) $this->option(
                    'controllers-only'
                ),

            'requestsOnly' =>
                (bool) $this->option(
                    'requests-only'
                ),

            'resourcesOnly' =>
                (bool) $this->option(
                    'resources-only'
                ),

            'schemasOnly' =>
                (bool) $this->option(
                    'schemas-only'
                ),
        ];
    }

    protected function generate(
        $module,
        array $definitions,
        array $options
    ): array {
        $results = [
            'controllers' => [],
            'requests' => [],
            'resources' => [],
            'schemas' => [],
            'routes' => [],
        ];

        $only = array_filter([
            'routes' =>
                $options['routesOnly'],

            'controllers' =>
                $options['controllersOnly'],

            'requests' =>
                $options['requestsOnly'],

            'resources' =>
                $options['resourcesOnly'],

            'schemas' =>
                $options['schemasOnly'],
        ]);

        if (count($only) > 1) {
            throw new \InvalidArgumentException(
                'Only one generation-only option may be used.'
            );
        }

        if (
            !$only ||
            isset($only['controllers'])
        ) {
            $results['controllers'] =
                $this->controllerGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['requests'])
        ) {
            $results['requests'] =
                $this->requestGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['resources'])
        ) {
            $results['resources'] =
                $this->resourceGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['schemas'])
        ) {
            $results['schemas'] =
                $this->schemaGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['routes'])
        ) {
            $results['routes'] =
                $this->routeGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        return $results;
    }

    protected function displayPreview(
        $module,
        array $definitions,
        array $options
    ): void {
        $this->info(
            "Dry run - Module: {$module->getName()}"
        );

        $changes = [];

        $only = array_filter([
            'routes' =>
                $options['routesOnly'],

            'controllers' =>
                $options['controllersOnly'],

            'requests' =>
                $options['requestsOnly'],

            'resources' =>
                $options['resourcesOnly'],

            'schemas' =>
                $options['schemasOnly'],
        ]);

        if (
            !$only ||
            isset($only['controllers'])
        ) {
            $changes = [
                ...$changes,
                ...$this->controllerGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['requests'])
        ) {
            $changes = [
                ...$changes,
                ...$this->requestGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['resources'])
        ) {
            $changes = [
                ...$changes,
                ...$this->resourceGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['schemas'])
        ) {
            $changes = [
                ...$changes,
                ...$this->schemaGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['routes'])
        ) {
            $changes = [
                ...$changes,
                ...$this->routeGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        foreach ($changes as $change) {
            $this->line(
                sprintf(
                    '  %s %s: %s',
                    strtoupper(
                        $change['action']
                        ?? 'CREATE'
                    ),
                    $change['type']
                        ?? 'file',
                    $change['file']
                        ?? ''
                )
            );
        }

        $this->info(
            'Total: '
            . count($changes)
            . ' file(s).'
        );
    }

    protected function displayTrace(
        array $definitions
    ): void {
        $this->info(
            'Route hierarchy:'
        );

        $this->traceNodes(
            $definitions,
            0
        );
    }

    protected function traceNodes(
        array $definitions,
        int $level
    ): void {
        foreach ($definitions as $definition) {
            $this->line(
                str_repeat(
                    '  ',
                    $level
                )
                . '- '
                . $definition->routePath()
            );

            $this->traceNodes(
                $definition->children,
                $level + 1
            );
        }
    }

    protected function displayResults(
        array $results
    ): void {
        $this->newLine();

        $this->info(
            'Generation complete.'
        );

        foreach (
            [
                'controllers',
                'requests',
                'resources',
                'schemas',
            ] as $type
        ) {
            if (
                empty($results[$type])
            ) {
                continue;
            }

            $this->line(
                "\n"
                . ucfirst($type)
                . ':'
            );

            foreach (
                $results[$type]
                as $result
            ) {
                $this->line(
                    "  {$result['action']}: "
                    . ($result['name'] ?? '')
                );

                if (
                    $this->getOutput()
                        ->isVerbose()
                ) {
                    $this->line(
                        "    "
                        . ($result['full_path']
                            ?? '')
                    );
                }
            }
        }

        foreach (
            $results['routes']
            as $route
        ) {
            $this->line(
                "\nRoutes: "
                . "{$route['action']} "
                . "{$route['path']}"
            );
        }
    }
}