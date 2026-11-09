<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Database;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Seeder\SeederGenerator;
use Illuminate\Console\Command;
use Throwable;

class MakeSeederCommand extends Command
{
    protected $signature = 'asasflow:make-seeder
                            {name : The name of the seeder to create}
                            {module? : The target module name}
                            {--master : Create a master DatabaseSeeder for the module}
                            {--force : Overwrite existing seeder}';

    protected $description = 'Create a new database seeder for the specified module.';

    protected $aliases = [
        'asasflow:make-seed',
    ];

    public function handle(
        SeederGenerator $generator,
        ModuleRepositoryInterface $modules
    ): int {
        try {
            $name = (string) $this->argument('name');
            $moduleName = $this->argument('module');

            $module = $this->resolveModule($modules, $moduleName);

            if ($module === null) {
                return self::FAILURE;
            }

            $master = (bool) $this->option('master');
            $force = (bool) $this->option('force');

            $result = $generator->generate($module, $name, $master, $force);

            $action = $result['action'] === 'generated'
                ? 'Created'
                : 'Skipped';

            $this->components->info(
                "{$action} seeder [{$result['class']}] in module [{$module->getName()}]: {$result['path']}"
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
                'Select the module for this seeder:',
                array_keys($all)
            );

            return $modules->find($selected);
        }

        $this->error('The [module] argument is required in non-interactive mode.');

        return null;
    }
}
