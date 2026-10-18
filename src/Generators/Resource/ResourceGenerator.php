<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Resource;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceGenerator implements GeneratorInterface, SchemaArtifactGeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
        protected StubRenderer $stubs,
    ) {}

    public function key(): string
    {
        return 'resources';
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
        if (!$definition->hasResource()) {
            return ['action' => 'skipped'];
        }

        $path = $this->files->getResourcePath(
            $module,
            $definition->resourceRelativePath(),
        );

        $exists = $this->files->exists($path);
        $force = (bool) ($options['force'] ?? false);

        if (!$exists || $force) {
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

        $document = $this->files->read($path);

        if (!GeneratedBlock::has($document, 'generated-fields')) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-fields',
            $this->buildFields($schema),
        );

        return [
            'action' => 'updated',
            'path' => $path,
        ];
    }

    protected function generatedContent(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): string {
        return GeneratedBlock::replace(
            $this->content($module, $definition),
            'generated-fields',
            $this->buildFields($schema),
        );
    }

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results,
    ): void {
        if ($definition->hasResource()) {
            $relative = $definition->resourceRelativePath();
            $path = $this->files->getResourcePath($module, $relative);
            $exists = $this->files->exists($path);
            $force = (bool) ($options['force'] ?? false);

            if (!$exists || $force) {
                $this->files->writeFile(
                    $path,
                    $this->content($module, $definition),
                    true,
                );
            }

            $results[] = [
                'name' => $definition->resourceClass,
                'path' => $relative,
                'full_path' => $path,
                'action' => $exists ? 'skipped' : 'created',
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

    protected function content(
        $module,
        MenuDefinition $definition,
    ): string {
        return $this->stubs->renderFile(
            'resource.stub',
            [
                'NAMESPACE' => $this->support->resourceNamespace($module, $definition),
                'CLASS' => $definition->resourceClass,
                'IMPORTS' => $this->support->generatorImports(
                    'resource',
                    [
                        Request::class,
                        JsonResource::class,
                    ],
                ),
                'TRAIT_IMPORTS' => $this->support->traitImports('resource'),
                'TRAITS' => $this->support->traitUsage('resource'),
            ],
        );
    }

    protected function buildFields(SchemaDefinition $schema): string
    {
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

        foreach (array_keys($properties) as $field) {
            $lines[] = '            '
                . var_export($field, true)
                . " => \$this->{$field},";
        }

        $lines[] = '        ];';

        return implode(PHP_EOL, $lines);
    }
}
