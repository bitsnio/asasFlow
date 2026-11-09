<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Seeder;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SeederGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate a new database seeder for the given module.
     *
     * @return array{action: string, path: string, class: string, namespace: string, fqcn: string}
     */
    public function generate(
        ModuleDefinition $module,
        string $name,
        bool $master = false,
        bool $force = false,
    ): array {
        $trimmedName = trim($name);

        if ($trimmedName === '' || $trimmedName === 'Seeder') {
            throw new InvalidArgumentException(
                "The name [{$name}] resolves to the class [Seeder], which clashes with the Illuminate\\Database\\Seeder base class."
            );
        }

        $className = $this->resolveClassName($module, $trimmedName, $master);
        $namespace = $module->namespace . '\\Database\\Seeders';
        $fqcn = $namespace . '\\' . $className;

        $seederDir = $module->getPath() . '/database/seeders';
        $this->files->ensureDirectoryExists($seederDir);
        $path = $seederDir . '/' . $className . '.php';

        if ($this->files->exists($path) && ! $force) {
            return [
                'action' => 'skipped-existing',
                'path' => $path,
                'class' => $className,
                'namespace' => $namespace,
                'fqcn' => $fqcn,
            ];
        }

        $content = $this->stubs->renderFile('Database/seeder.stub', [
            'NAMESPACE' => $namespace,
            'CLASS' => $className,
        ]);

        $this->files->writeFile($path, $content, $force);

        return [
            'action' => 'generated',
            'path' => $path,
            'class' => $className,
            'namespace' => $namespace,
            'fqcn' => $fqcn,
        ];
    }

    protected function resolveClassName(
        ModuleDefinition $module,
        string $name,
        bool $master
    ): string {
        if ($master) {
            if ($name === '' || strcasecmp($name, $module->getName()) === 0) {
                return Str::studly($module->getName()) . 'DatabaseSeeder';
            }

            $studly = Str::studly($name);

            return str_ends_with($studly, 'DatabaseSeeder')
                ? $studly
                : $studly . 'DatabaseSeeder';
        }

        $studly = Str::studly($name);

        return str_ends_with($studly, 'Seeder')
            ? $studly
            : $studly . 'Seeder';
    }
}
