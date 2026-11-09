<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Database;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Factory\FactoryGenerator;
use Illuminate\Console\Command;
use Throwable;

class MakeFactoryCommand extends Command
{
    protected $signature = 'asasflow:make-factory
                            {name : The name of the factory to create}
                            {module? : The target module name}
                            {--model= : The name of the model associated with the factory}
                            {--force : Overwrite existing factory}';

    protected $description = 'Create a new Eloquent factory for the specified module.';

    public function handle(
        FactoryGenerator $generator,
        ModuleRepositoryInterface $modules
    ): int {
        try {
            $name = (string) $this->argument('name');
            $moduleName = $this->argument('module');

            $module = $this->resolveModule($modules, $moduleName);

            if ($module === null) {
                return self::FAILURE;
            }

            $model = $this->option('model') ? (string) $this->option('model') : null;
            $force = (bool) $this->option('force');

            $result = $generator->generate($module, $name, $model, $force);

            $action = $result['action'] === 'generated'
                ? 'Created'
                : 'Skipped';

            $this->components->info(
                "{$action} factory [{$result['class']}] in module [{$module->getName()}]: {$result['path']}"
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function resolveModule(
        ModuleRepositoryInterface $modules,
        ?string $moduleName
    ): ?ModuleDefinition {
        if ($moduleName !== null && trim($moduleName) !== '') {
            $module = $modules->find(trim($moduleName));

            if (! $module) {
                $this->error("Module [{$moduleName}] does not exist.");

                return null;
            }

            return $module;
        }

        $all = $modules->all();

        if (empty($all)) {
            $this->error('No AsasFlow modules found.');

            return null;
        }

        if (count($all) === 1) {
            return reset($all);
        }

        if ($this->input->isInteractive()) {
            $selected = (string) $this->choice(
                'Select the module for this factory:',
                array_keys($all)
            );

            return $modules->find($selected);
        }

        $this->error('The [module] argument is required in non-interactive mode.');

        return null;
    }
}
