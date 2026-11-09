<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Factory;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FactoryGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate a new Eloquent factory for the given module.
     *
     * @return array{action: string, path: string, class: string, namespace: string, fqcn: string, model: string}
     */
    public function generate(
        ModuleDefinition $module,
        string $name,
        ?string $model = null,
        bool $force = false,
    ): array {
        $trimmedName = trim($name);

        if ($trimmedName === '' || $trimmedName === 'Factory') {
            throw new InvalidArgumentException(
                "The name [{$name}] resolves to the class [Factory], which clashes with the Illuminate\\Database\\Eloquent\\Factories\\Factory base class."
            );
        }

        $className = $this->resolveClassName($trimmedName);
        $namespace = $module->namespace . '\\Database\\Factories';
        $fqcn = $namespace . '\\' . $className;

        [$modelClass, $modelFqcn] = $this->resolveModel($module, $className, $model);

        $factoryDir = $module->getPath() . '/database/factories';
        $this->files->ensureDirectoryExists($factoryDir);
        $path = $factoryDir . '/' . $className . '.php';

        if ($this->files->exists($path) && ! $force) {
            return [
                'action' => 'skipped-existing',
                'path' => $path,
                'class' => $className,
                'namespace' => $namespace,
                'fqcn' => $fqcn,
                'model' => $modelFqcn,
            ];
        }

        $content = $this->stubs->renderFile('Database/factory.stub', [
            'NAMESPACE' => $namespace,
            'CLASS' => $className,
            'MODEL_IMPORT' => 'use ' . $modelFqcn . ';',
            'MODEL_CLASS' => $modelClass,
            'MODEL_FQCN' => $modelFqcn,
        ]);

        $this->files->writeFile($path, $content, $force);

        return [
            'action' => 'generated',
            'path' => $path,
            'class' => $className,
            'namespace' => $namespace,
            'fqcn' => $fqcn,
            'model' => $modelFqcn,
        ];
    }

    protected function resolveClassName(string $name): string
    {
        $studly = Str::studly($name);

        return str_ends_with($studly, 'Factory')
            ? $studly
            : $studly . 'Factory';
    }

    /**
     * @return array{0: string, 1: string} [ModelClassName, FullyQualifiedModelClassName]
     */
    protected function resolveModel(
        ModuleDefinition $module,
        string $factoryClass,
        ?string $model
    ): array {
        if ($model !== null && trim($model) !== '') {
            $trimmed = trim($model);

            if (str_contains($trimmed, '\\')) {
                $fqcn = ltrim($trimmed, '\\');

                return [class_basename($fqcn), $fqcn];
            }

            $modelName = Str::studly($trimmed);

            return [$modelName, $module->namespace . '\\Models\\' . $modelName];
        }

        // Derive model from factory class name by removing 'Factory' suffix
        $derived = preg_replace('/Factory$/', '', $factoryClass);
        $modelName = $derived !== '' ? $derived : $factoryClass;

        return [$modelName, $module->namespace . '\\Models\\' . $modelName];
    }
}
