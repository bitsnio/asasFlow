<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Database;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SeedCommand extends Command
{
    protected $signature = 'asasflow:seed
                            {module? : The module to seed (omit to seed all modules)}
                            {--class= : The class name of the root seeder}
                            {--database= : The database connection to use}
                            {--force : Force the operation in production}';

    protected $description = 'Run database seeders for the specified module or all enabled modules.';

    protected $aliases = [
        'asasflow:module:seed',
        'asasflow:db:seed',
    ];

    public function handle(ModuleRepositoryInterface $modules): int
    {
        try {
            $moduleName = $this->argument('module');

            if ($moduleName !== null && trim((string) $moduleName) !== '') {
                $module = $modules->find(trim((string) $moduleName));

                if (! $module) {
                    $this->error("Module [{$moduleName}] does not exist.");

                    return self::FAILURE;
                }

                return $this->seedModule($module) ? self::SUCCESS : self::FAILURE;
            }

            $all = $modules->all();

            if (empty($all)) {
                $this->warn('No AsasFlow modules found to seed.');

                return self::SUCCESS;
            }

            $hasFailure = false;

            foreach ($all as $module) {
                if (! $this->seedModule($module)) {
                    $hasFailure = true;
                }
            }

            return $hasFailure ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function seedModule(ModuleDefinition $module): bool
    {
        $this->components->info("Seeding module [{$module->getName()}]...");

        $seeders = $this->resolveSeeders($module);

        if (empty($seeders)) {
            $this->components->warn("Module [{$module->getName()}] has no seeders found.");

            return true;
        }

        $allSuccessful = true;

        foreach ($seeders as $seederClass) {
            $params = ['--class' => $seederClass];

            if ($database = $this->option('database')) {
                $params['--database'] = $database;
            }

            if ($this->option('force')) {
                $params['--force'] = true;
            }

            $exitCode = $this->call('db:seed', $params);

            if ($exitCode !== self::SUCCESS) {
                $this->components->error("Failed seeding [{$seederClass}] in module [{$module->getName()}].");
                $allSuccessful = false;
            } else {
                $this->components->info("Seeded [{$seederClass}] successfully.");
            }
        }

        return $allSuccessful;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSeeders(ModuleDefinition $module): array
    {
        if ($classOption = $this->option('class')) {
            $trimmed = trim((string) $classOption);

            if (class_exists($trimmed)) {
                return [$trimmed];
            }

            $namespaced = $module->namespace . '\\Database\\Seeders\\' . Str::studly($trimmed);

            if (class_exists($namespaced)) {
                return [$namespaced];
            }

            return [$trimmed];
        }

        $masterName = Str::studly($module->getName()) . 'DatabaseSeeder';
        $masterClass = $module->namespace . '\\Database\\Seeders\\' . $masterName;

        if (class_exists($masterClass)) {
            return [$masterClass];
        }

        $defaultClass = $module->namespace . '\\Database\\Seeders\\DatabaseSeeder';

        if (class_exists($defaultClass)) {
            return [$defaultClass];
        }

        // Scan the module's database/seeders directory for *Seeder.php files
        $seederDir = $module->getPath() . '/database/seeders';

        if (! is_dir($seederDir)) {
            return [];
        }

        $seeders = [];
        $files = glob($seederDir . '/*Seeder.php') ?: [];

        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $fqcn = $module->namespace . '\\Database\\Seeders\\' . $basename;

            if (class_exists($fqcn)) {
                $seeders[] = $fqcn;
            }
        }

        return $seeders;
    }
}
