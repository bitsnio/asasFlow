<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        foreach ($definitions as $definition) {
            $this->generateNode(
                $module,
                $definition,
                $options,
                $results,
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
            $module,
            $definition,
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
            $result = $this->createOrSyncController(
                $module,
                $definition,
                (bool) ($options['force'] ?? false),
            );

            $results[] = [
                'name' => $definition->controllerClass,
                'path' => $definition->controllerRelativePath(),
                'full_path' => $result['path'],
                'action' => $result['action'],
            ];
        }

        foreach ($definition->children as $child) {
            $this->generateNode(
                $module,
                $child,
                $options,
                $results,
            );
        }
    }

    protected function createOrSyncController(
        $module,
        MenuDefinition $definition,
        bool $force = false,
        ?SchemaDefinition $schema = null,
    ): array {
        $schema ??= $this->resolveSchema($module, $definition);
        $relative = $definition->controllerRelativePath();
        $path = $this->files->getControllerPath($module, $relative);
        $exists = $this->files->exists($path);
        $useModel = $this->shouldUseModel($module, $definition, $schema);

        if (!$exists || $force) {
            $this->files->writeFile(
                $path,
                $this->content($module, $definition, $useModel, $schema),
                true,
            );

            return [
                'action' => $exists ? 'regenerated' : 'created',
                'path' => $path,
            ];
        }

        $document = $this->files->read($path);
        $modelImportRemoved = false;

        // If the schema disabled model generation, strip the model import if Phase 1 added it
        if (!$useModel) {
            $modelFqcn = $this->support->modelNamespace($module) . '\\' . $definition->modelClass();
            $cleanedDocument = $this->removeImport($document, $modelFqcn);

            if ($cleanedDocument !== $document) {
                $document = $cleanedDocument;
                $modelImportRemoved = true;
            }
        }

        [$updatedDocument, $addedMethods] = $this->actionSynchronizer->sync(
            $document,
            $this->methodMap($definition, $useModel, $schema),
        );

        if (!empty($addedMethods) || $modelImportRemoved) {
            $this->files->writeFile($path, $updatedDocument, true);

            $details = [];
            if ($modelImportRemoved) {
                $details[] = 'removed model import';
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
            $this->files->updateGeneratedBlock(
                $path,
                'methods',
                $this->methods($definition, $useModel, $schema),
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

    protected function resolveSchema($module, MenuDefinition $definition): ?SchemaDefinition
    {
        $schemaPath = $module->getPath() . '/schema/' . $definition->schemaName() . '.json';

        if (!is_file($schemaPath)) {
            return null;
        }

        try {
            return SchemaDefinition::fromFile($schemaPath);
        } catch (Throwable) {
            return null;
        }
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

        $schemaPath = $module->getPath() . '/schema/' . $definition->schemaName() . '.json';

        if (is_file($schemaPath)) {
            try {
                return SchemaDefinition::fromFile($schemaPath)->shouldGenerate('models');
            } catch (Throwable) {
                return true;
            }
        }

        return true;
    }

    protected function removeImport(string $document, string $fqcn): string
    {
        $pattern = '/^use\s+' . preg_quote($fqcn, '/') . '\s*;\R?/m';

        return (string) preg_replace($pattern, '', $document);
    }

    protected function content(
        $module,
        MenuDefinition $definition,
        bool $useModel = true,
        ?SchemaDefinition $schema = null,
    ): string {
        return $this->stubs->renderFile(
            'controller.stub',
            [
                'NAMESPACE' => $this->support->controllerNamespace($module, $definition),
                'CLASS' => $definition->controllerClass,
                'IMPORTS' => $this->imports($module, $definition, $useModel, $schema),
                'TRAIT_IMPORTS' => $this->support->traitImports('controller', [JsonResponseTrait::class]),
                'TRAITS' => $this->support->traitUsage('controller', [JsonResponseTrait::class]),
                'METHODS' => $this->methods($definition, $useModel, $schema),
            ],
        );
    }

    protected function imports(
        $module,
        MenuDefinition $definition,
        bool $useModel = true,
        ?SchemaDefinition $schema = null,
    ): string {
        $imports = [
            JsonResponse::class,
        ];
        $actions = $definition->routeActions();

        if (in_array('store', $actions, true) || in_array('update', $actions, true)) {
            $imports[] = $this->support->requestNamespace($module, $definition)
                . '\\'
                . $definition->requestClass();
        }

        if ($useModel) {
            $imports[] = $this->support->modelNamespace($module)
                . '\\'
                . $definition->modelClass();
        }

        if ($definition->hasResource()) {
            $imports[] = $this->support->resourceNamespace($module, $definition)
                . '\\'
                . $definition->resourceClass;
        }

        if ($definition->customActions()) {
            $imports[] = Request::class;
        }

        if ($useModel && $schema !== null && $schema->relationNames() !== []) {
            $imports[] = Arr::class;
            $imports[] = DB::class;
        }

        return $this->support->generatorImports('controller', $imports);
    }

    protected function methodMap(
        MenuDefinition $definition,
        bool $useModel = true,
        ?SchemaDefinition $schema = null,
    ): array {
        $map = [];
        $relations = ($useModel && $schema !== null)
            ? $schema->relations($definition->tableName(), $definition->modelClass())
            : [];

        foreach ($definition->routeActions() as $action) {
            $code = match ($action) {
                'index' => $this->indexMethod($definition, $useModel, $relations),
                'store' => $this->storeMethod($definition, $useModel, $relations),
                'show' => $this->showMethod($definition, $useModel, $relations),
                'update' => $this->updateMethod($definition, $useModel, $relations),
                'destroy' => $this->destroyMethod($definition, $useModel),
                default => '',
            };

            if ($code !== '') {
                $map[$action] = $code;
            }
        }

        foreach (array_keys($definition->customActions()) as $actionName) {
            $methodName = Str::camel($actionName);
            $map[$methodName] = $this->customActionMethod($actionName);
        }

        return $map;
    }

    protected function methods(
        MenuDefinition $definition,
        bool $useModel = true,
        ?SchemaDefinition $schema = null,
    ): string {
        return implode(
            PHP_EOL . PHP_EOL,
            array_values($this->methodMap($definition, $useModel, $schema)),
        );
    }

    protected function customActionMethod(string $actionName): string
    {
        $method = Str::camel($actionName);

        return <<<PHP
            public function {$method}(Request \$request): JsonResponse
            {
                return \$this->notImplementedResponse(
                    '{$method} not implemented'
                );
            }
        PHP;
    }

    /**
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function indexMethod(
        MenuDefinition $definition,
        bool $useModel = true,
        array $relations = [],
    ): string {
        if (!$useModel) {
            return <<<'PHP'
                public function index(): JsonResponse
                {
                    return $this->notImplementedResponse(
                        'Index not implemented'
                    );
                }
            PHP;
        }

        $paginationEnabled = $this->support->featureEnabled('controller', 'pagination');
        $paginationConfig = $this->support->featureConfig('controller', 'pagination', []);

        $perPage = is_array($paginationConfig)
            ? filter_var(
                $paginationConfig['per_page'] ?? 15,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            )
            : 15;

        $perPage = $perPage ?: 15;
        $modelClass = $definition->modelClass();
        $queryExpr = $relations !== []
            ? "{$modelClass}::query()->with(" . $this->exportRelationMethods($relations) . ')'
            : "{$modelClass}::query()";

        $fetchExpression = $paginationEnabled
            ? "\$this->paginateQuery({$queryExpr}, {$perPage})"
            : "{$queryExpr}->get()";

        $payload = $definition->hasResource()
            ? "{$definition->resourceClass}::collection(\$items)"
            : '$items';

        return <<<PHP
            public function index(): JsonResponse
            {
                \$items = {$fetchExpression};

                return \$this->paginatedResponse(
                    {$payload}
                );
            }
        PHP;
    }

    /**
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function storeMethod(
        MenuDefinition $definition,
        bool $useModel = true,
        array $relations = [],
    ): string {
        if (!$useModel) {
            return <<<PHP
                public function store(
                    {$definition->requestClass()} \$request
                ): JsonResponse {
                    return \$this->notImplementedResponse(
                        'Store not implemented'
                    );
                }
            PHP;
        }

        $payload = $definition->hasResource()
            ? "new {$definition->resourceClass}(\$item)"
            : '$item';

        if ($relations === []) {
            return <<<PHP
                public function store(
                    {$definition->requestClass()} \$request
                ): JsonResponse {
                    \$item = {$definition->modelClass()}::create(
                        \$request->validated()
                    );

                    return \$this->createdResponse(
                        {$payload}
                    );
                }
            PHP;
        }

        $pullLines = [];
        $syncLines = [];
        foreach ($relations as $relation) {
            $prop = (string) $relation['property'];
            $method = (string) $relation['method'];
            $var = '$' . Str::camel($prop);
            $default = ($relation['type'] ?? 'hasMany') === 'hasMany' ? '[]' : 'null';
            $pullLines[] = "            {$var} = Arr::pull(\$data, " . var_export($prop, true) . ", {$default});";

            if (($relation['type'] ?? 'hasMany') === 'hasMany') {
                $syncLines[] = "            if (!empty({$var})) {\n                \$item->{$method}()->createMany({$var});\n            }";
            } else {
                $syncLines[] = "            if (is_array({$var}) && {$var} !== []) {\n                \$item->{$method}()->create({$var});\n            }";
            }
        }

        $pullBlock = implode(PHP_EOL, $pullLines);
        $syncBlock = implode(PHP_EOL, $syncLines);
        $withList = $this->exportRelationMethods($relations);

        return <<<PHP
            public function store(
                {$definition->requestClass()} \$request
            ): JsonResponse {
                \$item = DB::transaction(function () use (\$request) {
                    \$data = \$request->validated();
        {$pullBlock}

                    \$item = {$definition->modelClass()}::create(\$data);
        {$syncBlock}

                    return \$item->load({$withList});
                });

                return \$this->createdResponse(
                    {$payload}
                );
            }
        PHP;
    }

    /**
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function showMethod(
        MenuDefinition $definition,
        bool $useModel = true,
        array $relations = [],
    ): string {
        $parameter = $definition->parameterName();

        if (!$useModel) {
            return <<<PHP
                public function show(
                    \${$parameter}
                ): JsonResponse {
                    return \$this->notImplementedResponse(
                        'Show not implemented'
                    );
                }
            PHP;
        }

        $payload = $definition->hasResource()
            ? "new {$definition->resourceClass}(\${$parameter})"
            : "\${$parameter}";

        if ($relations === []) {
            return <<<PHP
                public function show(
                    {$definition->modelClass()} \${$parameter}
                ): JsonResponse {
                    return \$this->successResponse(
                        {$payload}
                    );
                }
            PHP;
        }

        $withList = $this->exportRelationMethods($relations);

        return <<<PHP
            public function show(
                {$definition->modelClass()} \${$parameter}
            ): JsonResponse {
                \${$parameter}->load({$withList});

                return \$this->successResponse(
                    {$payload}
                );
            }
        PHP;
    }

    /**
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function updateMethod(
        MenuDefinition $definition,
        bool $useModel = true,
        array $relations = [],
    ): string {
        $parameter = $definition->parameterName();

        if (!$useModel) {
            return <<<PHP
                public function update(
                    {$definition->requestClass()} \$request,
                    \${$parameter}
                ): JsonResponse {
                    return \$this->notImplementedResponse(
                        'Update not implemented'
                    );
                }
            PHP;
        }

        $payload = $definition->hasResource()
            ? "new {$definition->resourceClass}(\${$parameter})"
            : "\${$parameter}";

        if ($relations === []) {
            return <<<PHP
                public function update(
                    {$definition->requestClass()} \$request,
                    {$definition->modelClass()} \${$parameter}
                ): JsonResponse {
                    \${$parameter}->update(
                        \$request->validated()
                    );

                    return \$this->successResponse(
                        {$payload}
                    );
                }
            PHP;
        }

        $pullLines = [];
        $syncLines = [];
        foreach ($relations as $relation) {
            $prop = (string) $relation['property'];
            $method = (string) $relation['method'];
            $hasVar = '$has' . Str::studly($prop);
            $var = '$' . Str::camel($prop);
            $quotedProp = var_export($prop, true);
            $default = ($relation['type'] ?? 'hasMany') === 'hasMany' ? '[]' : 'null';

            $pullLines[] = "            {$hasVar} = array_key_exists({$quotedProp}, \$data);";
            $pullLines[] = "            {$var} = Arr::pull(\$data, {$quotedProp}, {$default});";

            if (($relation['type'] ?? 'hasMany') === 'hasMany') {
                $syncLines[] = "            if ({$hasVar} && is_array({$var})) {\n                \${$parameter}->{$method}()->delete();\n                if ({$var} !== []) {\n                    \${$parameter}->{$method}()->createMany({$var});\n                }\n            }";
            } else {
                $syncLines[] = "            if ({$hasVar}) {\n                \${$parameter}->{$method}()->delete();\n                if (is_array({$var}) && {$var} !== []) {\n                    \${$parameter}->{$method}()->create({$var});\n                }\n            }";
            }
        }

        $pullBlock = implode(PHP_EOL, $pullLines);
        $syncBlock = implode(PHP_EOL, $syncLines);
        $withList = $this->exportRelationMethods($relations);

        return <<<PHP
            public function update(
                {$definition->requestClass()} \$request,
                {$definition->modelClass()} \${$parameter}
            ): JsonResponse {
                DB::transaction(function () use (\$request, \${$parameter}): void {
                    \$data = \$request->validated();
        {$pullBlock}

                    \${$parameter}->update(\$data);
        {$syncBlock}
                });

                \${$parameter}->load({$withList});

                return \$this->successResponse(
                    {$payload}
                );
            }
        PHP;
    }

    /**
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function exportRelationMethods(array $relations): string
    {
        $methods = array_values(array_map(
            static fn(array $rel): string => var_export((string) $rel['method'], true),
            $relations,
        ));

        return '[' . implode(', ', $methods) . ']';
    }

    protected function destroyMethod(MenuDefinition $definition, bool $useModel = true): string
    {
        $parameter = $definition->parameterName();

        if (!$useModel) {
            return <<<PHP
                public function destroy(
                    \${$parameter}
                ): JsonResponse {
                    return \$this->notImplementedResponse(
                        'Destroy not implemented'
                    );
                }
            PHP;
        }

        return <<<PHP
            public function destroy(
                {$definition->modelClass()} \${$parameter}
            ): JsonResponse {
                \${$parameter}->delete();

                return \$this->successResponse(
                    null,
                    'Deleted successfully'
                );
            }
        PHP;
    }
}
