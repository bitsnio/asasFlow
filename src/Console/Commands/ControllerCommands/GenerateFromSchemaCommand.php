<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Facades\Menu;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Schema\SchemaArtifactGenerator;
use Illuminate\Console\Command;
use Throwable;

class GenerateFromSchemaCommand extends Command
{
    protected $signature = 'asasflow:generate-from-schema
        {module?* : One or more module names (space or comma separated; omit or pass --all for all modules)}
        {--all : Generate schema artifacts for all modules with a menu.php}
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

            $targetModules = $this->resolveTargetModules();
            if ($targetModules === null) {
                return self::FAILURE;
            }

            if ($targetModules === []) {
                $this->warn('No modules found to generate schema artifacts.');

                return self::SUCCESS;
            }

            $schemaOption = $this->option('schema');

            foreach ($targetModules as $module) {
                $menuPath = $this->files->getMenuPath($module);

                if (!$this->files->exists($menuPath)) {
                    $this->warn("Skipping [{$module->getName()}]: Menu configuration not found at {$menuPath}");

                    continue;
                }

                $this->info("Processing module [{$module->getName()}]...");

                $menu = require $menuPath;
                $definitions = $this->definitionBuilder->build($menu);

                if ($this->option('trace')) {
                    $this->displayTrace($definitions);
                }

                if ($this->option('dry-run')) {
                    $this->info("Dry run [{$module->getName()}]: schema artifacts were not generated.");

                    continue;
                }

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
                            ? "No matching model definition found for schema [{$schemaOption}] in [{$module->getName()}]."
                            : "No model definitions found to generate in [{$module->getName()}].",
                    );

                    continue;
                }

                $this->displayResults($results);
            }

            Menu::clearCache();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Schema artifact generation failed: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, ModuleDefinition>|null
     */
    protected function resolveTargetModules(): ?array
    {
        $rawArgs = (array) $this->argument('module');
        $names = [];

        foreach ($rawArgs as $arg) {
            foreach (explode(',', (string) $arg) as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $names[] = $trimmed;
                }
            }
        }

        $names = array_values(array_unique($names));

        if ($this->option('all') || $names === [] || in_array('all', array_map('strtolower', $names), true)) {
            return array_values($this->moduleRepository->all());
        }

        $resolved = [];
        foreach ($names as $name) {
            $module = $this->moduleRepository->find($name);
            if (!$module) {
                $this->error("Module [{$name}] does not exist!");

                return null;
            }
            $resolved[] = $module;
        }

        return $resolved;
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

                if (!empty($item['children']) && is_array($item['children'])) {
                    foreach ($item['children'] as $childItem) {
                        if (is_array($childItem)) {
                            $this->line('    ↳ ' . ($childItem['action'] ?? 'processed') . ': ' . ($childItem['path'] ?? ''));
                        }
                    }
                }
            }
        }
    }
}
