<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ControllerGenerator implements GeneratorInterface, SchemaArtifactGeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
        protected GeneratorSupport $support,
        protected ControllerActionSynchronizer $actionSynchronizer,
    ) {}

    public function key(): string
    {
        return 'controllers';
    }

    public function generate(
        $module,
        array $definitions,
        array $options = [],
    ): array {
        $results = [];

        foreach ($definitions as $definition) {$this->generateNode(
                $module,$definition,
                $options,$results,
            );
        }

        return $results;
    }

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
        array $options = [],
    ): array {
        if (!$definition->hasController()) {
            return ['action' => 'skipped'];
        }

        return $this->createOrSyncController(
            $module,$definition,
            (bool) ($options['force'] ?? false),
            $schema,
        );
    }

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results,
    ): void {
        if ($definition->hasController()) {
            $result =$this->createOrSyncController(
                $module,$definition,
                (bool) ($options['force'] ?? false),
            );

            $results[] = [
                'name' => $definition->controllerClass,
                'path' => $definition->controllerRelativePath(),
                'full_path' => $result['path'],
                'action' => $result['action'],
            ];
        }

        foreach ($definition->children as $child) {$this->generateNode(
                $module,$child,
                $options,$results,
            );
        }
    }

    protected function createOrSyncController(
        $module,
        MenuDefinition $definition,
        bool $force = false,
        ?SchemaDefinition $schema = null,
    ): array {
        $relative =$definition->controllerRelativePath();
        $path =$this->files->getControllerPath($module,$relative);
        $exists =$this->files->exists($path);$useModel = $this->shouldUseModel($module, $definition,$schema);

        if (!$exists || $force) {
            $this->files->writeFile($path,
                $this->content($module, $definition,$useModel),
                true,
            );

            return [
                'action' => $exists ? 'regenerated' : 'created',
                'path' => $path,
            ];
        }

        $document =$this->files->read($path);$modelImportRemoved = false;

        // If the schema disabled model generation, strip the model import if Phase 1 added it
        if (!$useModel) {
            $modelFqcn =$this->support->modelNamespace($module) . '\\' .$definition->modelClass();
            $cleanedDocument =$this->removeImport($document,$modelFqcn);

            if ($cleanedDocument !==$document) {
                $document =$cleanedDocument;
                $modelImportRemoved = true;
            }
        }

        [$updatedDocument, $addedMethods] =$this->actionSynchronizer->sync(
            $document,$this->methodMap($definition,$useModel),
        );

        if (!empty($addedMethods) || $modelImportRemoved) {$this->files->writeFile($path,$updatedDocument, true);

            $details = [];
            if ($modelImportRemoved) {$details[] = 'removed model import';
            }
            if (!empty($addedMethods)) {
                $details[] = 'added: ' . implode(', ', $addedMethods);
            }

            return [
                'action' => 'updated (' . implode('; ', $details) . ')',
                'path' => $path,
            ];
        }

        if (GeneratedBlock::has($document, 'methods')) {
            $this->files->updateGeneratedBlock($path,
                'methods',
                $this->methods($definition,$useModel),
            );

            return [
                'action' => 'updated',
                'path' => $path,
            ];
        }

        return [
            'action' => 'skipped',
            'path' => $path,
        ];
    }

    /**
     * Determines if this controller should import and use the generated module model.
     * Returns false if menu has `model => false` OR if the schema has `generator.model => false`.
     */
    protected function shouldUseModel(
        $module,
        MenuDefinition $definition,
        ?SchemaDefinition $schema = null,
    ): bool {
        if (!$definition->model) {
            return false;
        }

        if ($schema !== null) {
            return $schema->shouldGenerate('models');
        }

        $schemaPath = $module->getPath() . '/schema/' .$definition->schemaName() . '.json';

        if (is_file($schemaPath)) {
            try {
                return SchemaDefinition::fromFile($schemaPath)->shouldGenerate('models');
            } catch (Throwable) {
                return true;
            }
        }

        return true;
    }

    protected function removeImport(string $document, string$fqcn): string
    {
        $pattern = '/^use\s+' . preg_quote($fqcn, '/') . '\s*;\R?/m';

        return (string) preg_replace($pattern, '',$document);
    }

    protected function content(
        $module,
        MenuDefinition $definition,
        bool $useModel = true,
    ): string {
        return $this->stubs->renderFile(
            'controller.stub',
            [
                'NAMESPACE' => $this->support->controllerNamespace($module,$definition),
                'CLASS' => $definition->controllerClass,
                'IMPORTS' => $this->imports($module, $definition,$useModel),
                'TRAIT_IMPORTS' => $this->support->traitImports('controller'),
                'TRAITS' => $this->support->traitUsage('controller'),
                'METHODS' => $this->methods($definition,$useModel),
            ],
        );
    }

    protected function imports(
        $module,
        MenuDefinition $definition,
        bool $useModel = true,
    ): string {
        $imports = [];
        $actions =$definition->routeActions();

        if (in_array('store', $actions, true) || in_array('update',$actions, true)) {
            $imports[] =$this->support->requestNamespace($module,$definition)
                . '\\'
                . $definition->requestClass();
        }

        if ($useModel) {$imports[] = $this->support->modelNamespace($module)
                . '\\'
                . $definition->modelClass();
        }

        if ($definition->hasResource()) {
            $imports[] =$this->support->resourceNamespace($module,$definition)
                . '\\'
                . $definition->resourceClass;
        }

        if ($definition->customActions()) {$imports[] = Request::class;
        }

        return $this->support->generatorImports('controller',$imports);
    }

    protected function methodMap(MenuDefinition $definition, bool$useModel = true): array
    {
        $map = [];

        foreach ($definition->routeActions() as$action) {
            $code = match ($action) {
                'index' => $this->indexMethod($definition,$useModel),
                'store' => $this->storeMethod($definition,$useModel),
                'show' => $this->showMethod($definition,$useModel),
                'update' => $this->updateMethod($definition,$useModel),
                'destroy' => $this->destroyMethod($definition,$useModel),
                default => '',
            };

            if ($code !== '') {$map[$action] =$code;
            }
        }

        foreach (array_keys($definition->customActions()) as$actionName) {
            $methodName = Str::camel($actionName);
            $map[$methodName] = $this->customActionMethod($actionName);
        }

        return $map;
    }

    protected function methods(MenuDefinition $definition, bool$useModel = true): string
    {
        return implode(
            PHP_EOL . PHP_EOL,
            array_values($this->methodMap($definition,$useModel)),
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

    protected function indexMethod(MenuDefinition $definition, bool$useModel = true): string
    {
        if (!$useModel) {
            return <<<'PHP'
                public function index()
                {
                    return response()->json([
                        'message' => 'Index not implemented',
                    ], 501);
                }
            PHP;
        }

        $paginationEnabled =$this->support->featureEnabled('controller', 'pagination');
        $paginationConfig =$this->support->featureConfig('controller', 'pagination', []);

        $perPage = is_array($paginationConfig)
            ? filter_var(
                $paginationConfig['per_page'] ?? 20,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            )
            : 20;

        $perPage =$perPage ?: 20;

        $pagination =$paginationEnabled
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

    protected function storeMethod(MenuDefinition $definition, bool$useModel = true): string
    {
        if (!$useModel) {
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

        $return =$definition->hasResource()
            ? "return new {$definition->resourceClass}(\$item);"
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

    protected function showMethod(MenuDefinition $definition, bool$useModel = true): string
    {
        $parameter =$definition->parameterName();

        if (!$useModel) {
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

        $return =$definition->hasResource()
            ? "return new {$definition->resourceClass}(\${$parameter});"
            : "return \${$parameter};";

        return <<<PHP
            public function show(
                {$definition->modelClass()} \${$parameter}
            ) {
                {$return}
            }
        PHP;
    }

    protected function updateMethod(MenuDefinition $definition, bool$useModel = true): string
    {
        $parameter =$definition->parameterName();

        if (!$useModel) {
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

        $return =$definition->hasResource()
            ? "return new {$definition->resourceClass}(\${$parameter});"
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

    protected function destroyMethod(MenuDefinition $definition, bool$useModel = true): string
    {
        $parameter =$definition->parameterName();

        if (!$useModel) {
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