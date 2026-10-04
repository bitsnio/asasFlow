<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Illuminate\Support\Str;

class ControllerGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
        protected GeneratorSupport $support
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
                'NAMESPACE' => $this->support->controllerNamespace(
                    $module,
                    $definition
                ),

                'CLASS' => $definition->controllerClass,

                'IMPORTS' => $this->imports(
                    $module,
                    $definition
                ),

                'TRAIT_IMPORTS' => $this->support->traitImports(
                    'controller'
                ),

                'TRAITS' => $this->support->traitUsage(
                    'controller'
                ),

                'METHODS' => $this->methods($definition),
            ]
        );
    }


    protected function imports(
        $module,
        MenuDefinition $definition
    ): string {
        $imports = [];

        $actions = $definition->routeActions();

        if (
            in_array('store', $actions, true) ||
            in_array('update', $actions, true)
        ) {
            $imports[] = $this->support->requestNamespace(
                $module,
                $definition
            ) . '\\' . $definition->requestClass();
        }

        if ($definition->model) {
            $imports[] = $this->support->modelNamespace(
                $module
            ) . '\\' . $definition->modelClass();
        }

        if ($definition->hasResource()) {
            $imports[] = $this->support->resourceNamespace(
                $module,
                $definition
            ) . '\\' . $definition->resourceClass;
        }


        if ($definition->customActions()) {
            $imports[] = \Illuminate\Http\Request::class;
        }
        return $this->support->generatorImports(
            'controller',
            $imports
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

        foreach (array_keys($definition->customActions()) as $actionName) {
            $methods[] = $this->customActionMethod($actionName);
        }


        return implode(
            PHP_EOL . PHP_EOL,
            array_filter($methods)
        );
    }


    protected function customActionMethod(string $actionName): string
    {
        $method = Str::camel($actionName);

        return <<<PHP
                public function {$method}(Request \$request)
                {
                    return response()->json([
                        'message' => '{$method} not implemented',
                    ], 501);
                }
                PHP;
    }

    protected function indexMethod(
        MenuDefinition $definition
    ): string {

        if (!$definition->model) {
            return <<<'PHP'
                public function index()
                {
                    return response()->json([
                        'message' => 'Index not implemented',
                    ], 501);
                }
            PHP;
        }

        $paginationEnabled = $this->support->featureEnabled(
            'controller',
            'pagination'
        );

        $paginationConfig = $this->support->featureConfig(
            'controller',
            'pagination',
            []
        );

        $perPage = is_array($paginationConfig)
            ? filter_var(
                $paginationConfig['per_page'] ?? 20,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : 20;

        $perPage = $perPage ?: 20;

        $pagination = $paginationEnabled
            ? "paginate({$perPage})"
            : 'paginate()';

        $query = $definition->modelClass() . "::{$pagination}";

        if ($definition->hasResource()) {
            return <<<PHP
                public function index()
                {
                    return {$definition->resourceClass}::collection(
                        {$query}
                    );
                }
            PHP;
        }

        return <<<PHP
                    public function index()
                    {
                        return {$query};
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
}
