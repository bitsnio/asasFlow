<?php

namespace Bitsnio\AsasFlow\Generators\Resource;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;

class ResourceGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
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
        if (!$definition->hasResource()) {
            return [
                'action' => 'skipped',
            ];
        }

        $path =
            $this->files->getResourcePath(
                $module,
                $definition->resourceRelativePath()
            );

        if (!$this->files->exists($path)) {
            return [
                'action' => 'missing',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (!GeneratedBlock::has(
            $document,
            'generated-fields'
        )) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-fields',
            $this->buildFields($schema)
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
        if ($definition->hasResource()) {
            $relative =
                $definition->resourceRelativePath();

            $path =
                $this->files->getResourcePath(
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
                $definition->resourceClass,

                'path' => $relative,

                'full_path' => $path,

                'action' =>
                $exists
                    ? 'skipped'
                    : 'created',
            ];
        }

        foreach ($definition->children as $child) {
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
        if ($definition->hasResource()) {
            $path =
                $this->files->getResourcePath(
                    $module,
                    $definition->resourceRelativePath()
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'keep'
                    : 'create',

                'file' => $path,

                'type' => 'resource',
            ];
        }

        foreach ($definition->children as $child) {
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
            'resource.stub',
            [
                'NAMESPACE' => $this->support->resourceNamespace(
                    $module,
                    $definition
                ),

                'CLASS' => $definition->resourceClass,

                'IMPORTS' => $this->support->generatorImports(
                    'resource',
                    [
                        \Illuminate\Http\Request::class,
                        \Illuminate\Http\Resources\Json\JsonResource::class,
                    ]
                ),

                'TRAIT_IMPORTS' => $this->support->traitImports(
                    'resource'
                ),

                'TRAITS' => $this->support->traitUsage(
                    'resource'
                ),
            ]
        );
    }

    protected function buildFields(
        SchemaDefinition $schema
    ): string {

        if (!$this->support->featureEnabled('resource', 'schema_fields')) {
            return '        return [];';
        }
        $properties = $schema->properties();

        if (!$properties) {
            return '        return [];';
        }

        $lines = [
            '        return [',
        ];

        foreach (
            array_keys($properties)
            as $field
        ) {
            $lines[] =
                "            "
                . var_export(
                    $field,
                    true
                )
                . " => \$this->{$field},";
        }

        $lines[] = '        ];';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
