<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Model;

use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;

class ModelGenerator implements SchemaArtifactGeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
        protected StubRenderer $stubs,
    ) {}

    public function key(): string
    {
        return 'models';
    }

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
        array $options = [],
    ): array {
        $force = (bool) ($options['force'] ?? false);
        $table = $definition->tableName();
        $modelClass = $definition->modelClass();
        $relations = $schema->relations($table, $modelClass);

        $parentResult = $this->syncSingleModel(
            module: $module,
            modelClass: $modelClass,
            fillableContent: $this->buildFillable($definition, $schema),
            castsContent: $this->buildCasts($schema),
            relationsContent: $this->buildParentRelations($relations),
            force: $force,
        );

        if ($relations === []) {
            return $parentResult;
        }

        $children = [];

        foreach ($relations as $relation) {
            /** @var SchemaDefinition $childSchema */
            $childSchema = $relation['schema'];
            $childModelClass = (string) $relation['model'];
            $childTable = (string) $relation['table'];
            $foreignKey = (string) $relation['foreign_key'];

            $children[] = $this->syncSingleModel(
                module: $module,
                modelClass: $childModelClass,
                fillableContent: $this->buildFillable(
                    $definition,
                    $childSchema,
                    [$foreignKey],
                    $childTable,
                ),
                castsContent: $this->buildCasts($childSchema),
                relationsContent: $this->buildChildRelations($relation),
                force: $force,
            );
        }

        $parentResult['children'] = $children;

        return $parentResult;
    }

    /**
     * Synchronize or generate a single Eloquent model file.
     *
     * @return array{action: string, path: string}
     */
    protected function syncSingleModel(
        $module,
        string $modelClass,
        string $fillableContent,
        string $castsContent,
        string $relationsContent,
        bool $force,
    ): array {
        $path = $this->files->getModelPath(
            $module,
            $modelClass,
        );

        $exists = $this->files->exists($path);

        if ($exists && !$force) {
            $document = $this->files->read($path);

            if (
                !GeneratedBlock::has($document, 'generated-fillable')
                || !GeneratedBlock::has($document, 'generated-casts')
            ) {
                return [
                    'action' => 'skipped-no-marker',
                    'path' => $path,
                ];
            }

            $this->files->updateGeneratedBlock(
                $path,
                'generated-fillable',
                $fillableContent,
            );

            $this->files->updateGeneratedBlock(
                $path,
                'generated-casts',
                $castsContent,
            );

            $updatedDoc = $this->files->read($path);
            if (GeneratedBlock::has($updatedDoc, 'generated-relations')) {
                $this->files->updateGeneratedBlock(
                    $path,
                    'generated-relations',
                    $relationsContent,
                );
            }

            return [
                'action' => 'updated',
                'path' => $path,
            ];
        }

        $this->files->writeFile(
            $path,
            $this->renderModelContent(
                $module,
                $modelClass,
                $fillableContent,
                $castsContent,
                $relationsContent,
            ),
            true,
        );

        return [
            'action' => $exists ? 'regenerated' : 'created',
            'path' => $path,
        ];
    }

    protected function generatedContent(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): string {
        $relations = $schema->relations($definition->tableName(), $definition->modelClass());

        return $this->renderModelContent(
            module: $module,
            modelClass: $definition->modelClass(),
            fillableContent: $this->buildFillable($definition, $schema),
            castsContent: $this->buildCasts($schema),
            relationsContent: $this->buildParentRelations($relations),
        );
    }

    protected function renderModelContent(
        $module,
        string $modelClass,
        string $fillableContent,
        string $castsContent,
        string $relationsContent = '',
    ): string {
        $content = $this->stubs->renderFile(
            'model.stub',
            [
                'NAMESPACE' => $this->support->modelNamespace($module),
                'CLASS' => $modelClass,
            ],
        );

        $content = GeneratedBlock::replace(
            $content,
            'generated-fillable',
            $fillableContent,
        );

        $content = GeneratedBlock::replace(
            $content,
            'generated-casts',
            $castsContent,
        );

        if (GeneratedBlock::has($content, 'generated-relations')) {
            $content = GeneratedBlock::replace(
                $content,
                'generated-relations',
                $relationsContent,
            );
        }

        return $content;
    }

    /**
     * @param  array<int, string>  $extraFields
     */
    protected function buildFillable(
        MenuDefinition $definition,
        SchemaDefinition $schema,
        array $extraFields = [],
        ?string $overrideTable = null,
    ): string {
        $fields = array_values(array_unique([
            ...$extraFields,
            ...array_keys($schema->fillableProperties()),
        ]));
        $table = $overrideTable ?? $definition->tableName();

        if (
            config('asasflow.tenancy.database_strategy', 'single') === 'single'
            && ($definition->config['tenancy'] ?? true) !== false
            && !in_array($table, config('asasflow.tenancy.exempt_tables', ['tenants', 'tenant_domains']), true)
        ) {
            $tenantCol = (string) config('asasflow.tenancy.tenant_column', 'tenant_id');
            $prepend = [$tenantCol];

            $siteEnabled = (bool) config('asasflow.tenancy.site_enabled', true);
            $siteExempt = config('asasflow.tenancy.site_exempt_tables', ['sites']);

            if ($siteEnabled && !in_array($table, $siteExempt, true) && ($definition->config['site'] ?? true) !== false) {
                $prepend[] = (string) config('asasflow.tenancy.site_column', 'site_id');
            }

            $fields = array_values(array_unique([...$prepend, ...$fields]));
        }

        $lines = [];

        foreach ($fields as $field) {
            $lines[] = '        ' . var_export($field, true) . ',';
        }

        return implode(PHP_EOL, $lines);
    }

    protected function buildCasts(SchemaDefinition $schema): string
    {
        $casts = $schema->casts();

        // Laravel-specific convention; does not modify the JSON Schema.
        foreach ($schema->fillableProperties() as $field => $property) {
            if ($field === 'password') {
                $casts[$field] = 'hashed';
            }
        }

        $lines = [];

        foreach ($casts as $field => $cast) {
            $lines[] = '        '
                . var_export($field, true)
                . ' => '
                . var_export($cast, true)
                . ',';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Build Eloquent relation methods (`HasMany` / `HasOne`) on the parent model.
     *
     * @param  array<string, array<string, mixed>>  $relations
     */
    protected function buildParentRelations(array $relations): string
    {
        if ($relations === []) {
            return '';
        }

        $methods = [];

        foreach ($relations as $relation) {
            $method = (string) $relation['method'];
            $childModel = (string) $relation['model'];
            $foreignKey = var_export((string) $relation['foreign_key'], true);
            $isMany = ($relation['type'] ?? 'hasMany') === 'hasMany';
            $returnType = $isMany
                ? '\Illuminate\Database\Eloquent\Relations\HasMany'
                : '\Illuminate\Database\Eloquent\Relations\HasOne';
            $eloquentCall = $isMany ? 'hasMany' : 'hasOne';

            $methods[] = <<<PHP
    public function {$method}(): {$returnType}
    {
        return \$this->{$eloquentCall}({$childModel}::class, {$foreignKey});
    }
PHP;
        }

        return implode(PHP_EOL . PHP_EOL, $methods);
    }

    /**
     * Build the inverse `BelongsTo` relation method on a child model.
     *
     * @param  array<string, mixed>  $relation
     */
    protected function buildChildRelations(array $relation): string
    {
        $method = (string) $relation['inverse_method'];
        $parentModel = (string) $relation['parent_model'];
        $foreignKey = var_export((string) $relation['foreign_key'], true);

        return <<<PHP
    public function {$method}(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return \$this->belongsTo({$parentModel}::class, {$foreignKey});
    }
PHP;
    }
}
