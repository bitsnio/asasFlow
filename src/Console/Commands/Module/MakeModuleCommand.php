<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Generators\Module\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Composer\Semver\VersionParser;
use InvalidArgumentException;
use Throwable;

final class MakeModuleCommand extends Command
{
    protected $signature = 'asasflow:make-module
                            {name* : Name(s) of the module(s) to create (space or comma-separated)}
                            {--module-version=1.0.0 : The initial module version}
                            {--force : Replace existing generated files}';

    protected $description = 'Create one or more API-focused AsasFlow modules.';

    public function handle(ModuleGenerator $generator): int
    {
        try {
            // Updated option name to avoid collision with Symfony's global --version
            $version = (string)$this->option('module-version');

            try {
                (new VersionParser())->normalize($version);
            } catch (\UnexpectedValueException $exception) {
                throw new InvalidArgumentException(
                    "Invalid module version [{$version}]. Use a valid version such as 1.0.0.",
                    previous: $exception,
                );
            }

            $rawNames = (array) $this->argument('name');
            $names = [];

            foreach ($rawNames as $entry) {
                foreach (explode(',', (string) $entry) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $names[] = $part;
                    }
                }
            }

            $names = array_values(array_unique($names));

            if (empty($names)) {
                throw new InvalidArgumentException('Please specify at least one module name.');
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

            $force = (bool) $this->option('force');
            $scaffoldedCount = 0;
            $hasFailure = false;

            foreach ($names as $input) {
                if (
                    str_contains($input, '/')
                    || str_contains($input, '\\')
                    || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $input)
                ) {
                    $this->error("Invalid module name [{$input}].");
                    $hasFailure = true;
                    continue;
                }

                $name = Str::studly($input);

                if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                    $this->error("Invalid normalized module name [{$name}].");
                    $hasFailure = true;
                    continue;
                }

                $path = $modulesPath . DIRECTORY_SEPARATOR . $name;

                if (is_dir($path) && ! $force) {
                    $this->error(
                        "Module [{$name}] already exists. "
                            . 'Use --force only if you intend to replace generated files.'
                    );
                    $hasFailure = true;
                    continue;
                }

                $module = new ModuleDefinition(
                    name: $name,
                    path: $path,
                    namespace: $namespace . '\\' . $name,
                );

                $results = $generator->generate($module, $force);

                $this->newLine();
                $this->info("Module [{$name}] scaffolding completed.");

                foreach ($results as $result) {
                    $action = $result['action'] === 'generated'
                        ? 'Created'
                        : 'Skipped';

                    $this->line("  {$action}: {$result['path']}");
                }

                $scaffoldedCount++;
            }

            if ($scaffoldedCount > 0) {
                $this->newLine();
                $this->comment(
                    'Review the generated module manifest and run '
                        . '`composer dump-autoload` in the host project.'
                );
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
}