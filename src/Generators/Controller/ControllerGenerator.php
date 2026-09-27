<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;

class ControllerGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    public function generate(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $results = [];

        foreach ($definitions as $definition) {
            $this->generateNode(
                $module,
                $definition,
                $options,
                $results
            );
        }

        return $results;
    }

    public function preview(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $results = [];

        foreach ($definitions as $definition) {
            $this->previewNode(
                $module,
                $definition,
                $results
            );
        }

        return $results;
    }

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        if (!$definition->hasController()) {
            return [
                'action' => 'skipped',
            ];
        }

        $path =
            $this->files->getControllerPath(
                $module,
                $definition->controllerRelativePath()
            );

        if (!$this->files->exists($path)) {
            return [
                'action' => 'missing',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (
            !GeneratedBlock::has(
                $document,
                'methods'
            )
        ) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'methods',
            $this->methods(
                $definition
            )
        );

        return [
            'action' => 'updated',
            'path' => $path,
        ];
    }

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results
    ): void {
        if ($definition->hasController()) {
            $relative =
                $definition->controllerRelativePath();

            $path =
                $this->files->getControllerPath(
                    $module,
                    $relative
                );

            $exists =
                $this->files->exists($path);

            if (!$exists) {
                $this->files->writeFile(
                    $path,
                    $this->content(
                        $module,
                        $definition
                    ),
                    true
                );
            }

            $results[] = [
                'name' =>
                $definition->controllerClass,

                'path' => $relative,

                'full_path' => $path,

                'action' =>
                $exists
                    ? 'skipped'
                    : 'created',
            ];
        }

        foreach (
            $definition->children
            as $child
        ) {
            $this->generateNode(
                $module,
                $child,
                $options,
                $results
            );
        }
    }

    protected function previewNode(
        $module,
        MenuDefinition $definition,
        array &$results
    ): void {
        if ($definition->hasController()) {
            $path =
                $this->files->getControllerPath(
                    $module,
                    $definition->controllerRelativePath()
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'keep'
                    : 'create',

                'file' => $path,

                'type' => 'controller',
            ];
        }

        foreach (
            $definition->children
            as $child
        ) {
            $this->previewNode(
                $module,
                $child,
                $results
            );
        }
    }

    protected function content(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->stubs->renderFile(
            'controller.stub',
            [
                'NAMESPACE' =>
                $this->namespace(
                    $module,
                    $definition
                ),

                'CLASS' =>
                $definition->controllerClass,

                'IMPORTS' =>
                $this->imports(
                    $module,
                    $definition
                ),

                'METHODS' =>
                $this->methods(
                    $definition
                ),
            ]
        );
    }

    protected function namespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Controllers";

        $relative =
            $definition->controllerNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function imports(
        $module,
        MenuDefinition $definition
    ): string {
        $imports = [];

        if (
            in_array(
                'store',
                $definition->routeActions(),
                true
            ) ||
            in_array(
                'update',
                $definition->routeActions(),
                true
            )
        ) {
            $imports[] =
                'use '
                . $this->requestNamespace(
                    $module,
                    $definition
                )
                . '\\'
                . $definition->requestClass()
                . ';';
        }

        if ($definition->model) {
            $imports[] =
                'use '
                . $this->modelNamespace(
                    $module
                )
                . '\\'
                . $definition->modelClass()
                . ';';
        }

        if ($definition->hasResource()) {
            $imports[] =
                'use '
                . $this->resourceNamespace(
                    $module,
                    $definition
                )
                . '\\'
                . $definition->resourceClass
                . ';';
        }

        return implode(
            PHP_EOL,
            array_unique($imports)
        );
    }

    protected function methods(
        MenuDefinition $definition
    ): string {
        $methods = [];

        foreach (
            $definition->routeActions()
            as $action
        ) {
            $methods[] =
                match ($action) {
                    'index' =>
                    $this->indexMethod(
                        $definition
                    ),

                    'store' =>
                    $this->storeMethod(
                        $definition
                    ),

                    'show' =>
                    $this->showMethod(
                        $definition
                    ),

                    'update' =>
                    $this->updateMethod(
                        $definition
                    ),

                    'destroy' =>
                    $this->destroyMethod(
                        $definition
                    ),

                    default => '',
                };
        }

        return implode(
            PHP_EOL . PHP_EOL,
            array_filter($methods)
        );
    }

    protected function indexMethod(
        MenuDefinition $definition
    ): string {
        if (!$definition->model) {
            return <<<PHP
    public function index()
    {
        return response()->json([
            'message' => 'Index not implemented',
        ], 501);
    }
PHP;
        }

        if ($definition->hasResource()) {
            return <<<PHP
    public function index()
    {
        return {$definition->resourceClass}::collection(
            {$definition->modelClass()}::paginate()
        );
    }
PHP;
        }

        return <<<PHP
    public function index()
    {
        return {$definition->modelClass()}::paginate();
    }
PHP;
    }

    protected function storeMethod(
        MenuDefinition $definition
    ): string {
        if (!$definition->model) {
            return <<<PHP
    public function store(
        {$definition->requestClass()} \$request
    ) {
        return response()->json([
            'message' => 'Store not implemented',
        ], 501);
    }
PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . '($item);'
            : 'return $item;';

        return <<<PHP
    public function store(
        {$definition->requestClass()} \$request
    ) {
        \$item = {$definition->modelClass()}::create(
            \$request->validated()
        );

        {$return}
    }
PHP;
    }

    protected function showMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
    public function show(
        \${$parameter}
    ) {
        return response()->json([
            'message' => 'Show not implemented',
        ], 501);
    }
PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . "(\${$parameter});"
            : "return \${$parameter};";

        return <<<PHP
    public function show(
        {$definition->modelClass()} \${$parameter}
    ) {
        {$return}
    }
PHP;
    }

    protected function updateMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
    public function update(
        {$definition->requestClass()} \$request,
        \${$parameter}
    ) {
        return response()->json([
            'message' => 'Update not implemented',
        ], 501);
    }
PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . "(\${$parameter});"
            : "return \${$parameter};";

        return <<<PHP
    public function update(
        {$definition->requestClass()} \$request,
        {$definition->modelClass()} \${$parameter}
    ) {
        \${$parameter}->update(
            \$request->validated()
        );

        {$return}
    }
PHP;
    }

    protected function destroyMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
    public function destroy(
        \${$parameter}
    ) {
        return response()->json([
            'message' => 'Destroy not implemented',
        ], 501);
    }
PHP;
        }

        return <<<PHP
    public function destroy(
        {$definition->modelClass()} \${$parameter}
    ) {
        \${$parameter}->delete();

        return response()->noContent();
    }
PHP;
    }

    protected function requestNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Requests";

        $relative =
            $definition->controllerNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function modelNamespace(
        $module
    ): string {
        return
            "Modules\\{$module->getName()}"
            . "\\App\\Models";
    }

    protected function resourceNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Resources";

        $relative =
            $definition->resourceNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }
}
