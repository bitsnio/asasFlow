<?php

namespace Bitsnio\AsasFlow\Console\Commands\ModuleCommands;

use Bitsnio\Modules\Commands\Make\ModuleMakeCommand as BaseModuleMakeCommand;
use Bitsnio\AsasFlow\Generators\MenuGenerator;
use Bitsnio\AsasFlow\Generators\ModuleSettingsGenerator;

class ModuleMakeCommand extends BaseModuleMakeCommand
{
    protected $name = 'module:make';

    public function handle(): int
    {
        $names = (array) $this->argument('name');
        $created = []; // track successfully created modules

        foreach ($names as $moduleName) {
            try {
                // 1. Run parent generation
                $exitCode = parent::handle();
                if ($exitCode !== 0) {
                    throw new \RuntimeException("Module scaffolding failed for [{$moduleName}].");
                }
                $created[] = $moduleName;

                // 2. Run your additional generators
                $this->runPostGenerators($moduleName);
            } catch (\Exception $e) {
                $this->components->error($e->getMessage());

                // Rollback only what was successfully created
                $this->handleRollback($created);

                return E_ERROR;
            }
        }

        return 0;
    }

    protected function runPostGenerators(string $moduleName): void
    {
        // Each generator throws on failure — caller handles rollback
        (new MenuGenerator(
            $this->laravel['modules'],
            $moduleName,
            $this->components
        ))->generate();

        (new ModuleSettingsGenerator(
            $this->laravel['modules'],
            $moduleName,
            $this->components
        ))->generate();
        
    }

    protected function handleRollback(array $createdModules): void
    {
        if (empty($createdModules)) return;

        $moduleList = implode(', ', $createdModules);

        if ($this->confirm("Generation failed. Rollback created module(s) [{$moduleList}]?", false)) {
            foreach ($createdModules as $moduleName) {
                $this->call('module:delete', [
                    'module' => [$moduleName],
                    '--force' => true,
                ]);
                $this->components->warn("Rolled back: [{$moduleName}]");
            }
        } else {
            $this->components->warn("Skipping rollback. Modules [{$moduleList}] may be in an incomplete state.");
        }
    }
}
