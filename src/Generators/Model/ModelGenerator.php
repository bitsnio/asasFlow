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

    protected function buildFillable(SchemaDefinition $schema): string
    {
        $lines = [];

        foreach (array_keys($schema->properties()) as $field) {
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