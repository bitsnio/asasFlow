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
        $path = $this->files->getModelPath(
            $module,
            $definition->modelClass(),
        );

        $exists = $this->files->exists($path);
        $force = (bool) ($options['force'] ?? false);

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
                $this->buildFillable($schema),
            );

            $this->files->updateGeneratedBlock(
                $path,
                'generated-casts',
                $this->buildCasts($schema),
            );

            return [
                'action' => 'updated',
                'path' => $path,
            ];
        }

        $this->files->writeFile(
            $path,
            $this->generatedContent($module, $definition, $schema),
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
        $content = $this->stubs->renderFile(
            'model.stub',
            [
                'NAMESPACE' => $this->support->modelNamespace($module),
                'CLASS' => $definition->modelClass(),
            ],
        );

        $content = GeneratedBlock::replace(
            $content,
            'generated-fillable',
            $this->buildFillable($schema),
        );

        return GeneratedBlock::replace(
            $content,
            'generated-casts',
            $this->buildCasts($schema),
        );
    }

    // Inside ModelGenerator::syncFromSchema() and generatedContent(), pass $definition to buildFillable:
    // $this->buildFillable($definition, $schema)

    protected function buildFillable(
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): string {
        $fields = array_keys($schema->properties());
        $table = $definition->tableName();

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
        $lines = [];

        foreach ($schema->casts() as $field => $cast) {
            $lines[] = '        '
                . var_export($field, true)
                . ' => '
                . var_export($cast, true)
                . ',';
        }

        return implode(PHP_EOL, $lines);
    }
}
