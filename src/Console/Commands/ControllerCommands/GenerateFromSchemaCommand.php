<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Schema\SchemaArtifactGenerator;
use Illuminate\Console\Command;
use Throwable;

class GenerateFromSchemaCommand extends Command
{
    protected $signature = 'asasflow:generate-from-schema
        {module : The module name}
        {--schema= : Specific schema or model name to generate (optional)}
        {--only=all : Artifact to generate: all, models, migrations, requests, resources, controllers}
        {--force : Fully regenerate selected artifacts}
        {--trace : Show schema generation hierarchy}
        {--dry-run : Preview schema-driven generation}';

    protected $description = 'Generate models, migrations, validation, resources and controller updates from module schemas.';

    public function __construct(
        protected SchemaArtifactGenerator $generator,
        protected MenuBuilder $definitionBuilder,
        protected FileHandler $files,
        protected ModuleRepositoryInterface $moduleRepository,
    ) {
        parent::__construct();
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

            if ($this->option('dry-run')) {
                $this->info('Dry run: schema artifacts were not generated.');

                return self::SUCCESS;
            }

            $only = strtolower(trim((string) $this->option('only')));

            if ($only === '') {
                $only = 'all';
            }

            $allowed = $this->generator->allowedTypes();

            if (!in_array($only, $allowed, true)) {
                $this->error(
                    'Invalid --only value. Allowed values: ' . implode(', ', $allowed),
                );

                return self::FAILURE;
            }

            $schemaOption = $this->option('schema');

            $results = $this->generator->generate(
                $module,
                $definitions,
                [
                    'only' => $only,
                    'force' => (bool) $this->option('force'),
                    'schema' => is_string($schemaOption) ? $schemaOption : null,
                ],
            );

            if (empty($results)) {
                $this->warn(
                    $schemaOption
                        ? "No matching model definition found for schema [{$schemaOption}]."
                        : 'No model definitions found to generate.',
                );

                return self::SUCCESS;
            }

            $this->displayResults($results);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Schema artifact generation failed: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function displayTrace(array $definitions): void
    {
        $this->info('Schema hierarchy:');

        $this->traceNodes($definitions, 0);
    }

    protected function traceNodes(array $definitions, int $level): void
    {
        foreach ($definitions as $definition) {
            $this->line(str_repeat('  ', $level) . '- ' . $definition->permissionKey());

            $this->traceNodes($definition->children, $level + 1);
        }
    }

    protected function displayResults(array $results): void
    {
        $this->info('Schema artifact generation complete.');

        foreach ($results as $type => $items) {
            if (!is_array($items)) {
                continue;
            }

            $this->line("\n" . ucfirst($type) . ':');

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $this->line('  ' . ($item['action'] ?? 'processed') . ': ' . ($item['path'] ?? ''));
            }
        }
    }
}