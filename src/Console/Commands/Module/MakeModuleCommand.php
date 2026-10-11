<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Module\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class MakeModuleCommand extends Command
{
    protected $signature = 'asasflow:make-module
                            {name : Name of the module to create}
                            {--force : Replace existing generated files}';

    protected $description = 'Create an API-focused AsasFlow module.';

    public function handle(ModuleGenerator $generator): int
    {
        try {
            $input = trim((string) $this->argument('name'));

            if (
                $input === ''
                || str_contains($input, '/')
                || str_contains($input, '\\')
                || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $input)
            ) {
                throw new InvalidArgumentException(
                    "Invalid module name [{$input}]."
                );
            }

            $name = Str::studly($input);

            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException(
                    "Invalid normalized module name [{$name}]."
                );
            }

            $modulesPath = rtrim(
                (string) config(
                    'asasflow.modules.path',
                    base_path('Modules'),
                ),
                '/\\',
            );

            $namespace = trim(
                (string) config('asasflow.modules.namespace', 'Modules'),
                '\\',
            );

            $path = $modulesPath . DIRECTORY_SEPARATOR . $name;

            if (is_dir($path) && ! $this->option('force')) {
                $this->error(
                    "Module [{$name}] already exists. "
                    . 'Use --force only if you intend to replace generated files.'
                );

                return self::FAILURE;
            }

            $module = new ModuleDefinition(
                name: $name,
                path: $path,
                namespace: $namespace . '\\' . $name,
            );

            $results = $generator->generate(
                $module,
                (bool) $this->option('force'),
            );

            $this->newLine();
            $this->info("Module [{$name}] scaffolding completed.");

            foreach ($results as $result) {
                $action = $result['action'] === 'generated'
                    ? 'Created'
                    : 'Skipped';

                $this->line(
                    "  {$action}: {$result['path']}"
                );
            }

            $this->newLine();
            $this->comment(
                'Review the generated module manifest and run '
                . '`composer dump-autoload` in the host project.'
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
}