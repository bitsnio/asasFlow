<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Request;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Foundation\Http\FormRequest;

class RequestGenerator implements GeneratorInterface, SchemaArtifactGeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
        protected StubRenderer $stubs,
    ) {}

    public function key(): string
    {
        return 'requests';
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
        if (!$definition->isResource() || !$definition->hasController()) {
            return [
                'action' => 'skipped',
                'reason' => 'not-a-controller-resource',
            ];
        }

        $path = $this->files->getRequestPath(
            $module,
            $this->requestRelativePath($definition),
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

        if (!GeneratedBlock::has($document, 'generated-rules')) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-rules',
            $this->buildRules($schema),
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
        $content = $this->content($module, $definition);

        return GeneratedBlock::replace(
            $content,
            'generated-rules',
            $this->buildRules($schema),
        );
    }

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results,
    ): void {
        if ($definition->isResource() && $definition->hasController()) {
            $relative = $this->requestRelativePath($definition);
            $path = $this->files->getRequestPath($module, $relative);
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
                'name' => $definition->requestClass(),
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

    protected function requestRelativePath(MenuDefinition $definition): string
    {
        $namespace = $definition->controllerNamespace();

        return ($namespace ? str_replace('\\', '/', $namespace) . '/' : '')
            . $definition->requestClass();
    }

    protected function content(
        $module,
        MenuDefinition $definition,
    ): string {
        return $this->stubs->renderFile(
            'Request.stub',
            [
                'NAMESPACE' => $this->support->requestNamespace($module, $definition),
                'CLASS' => $definition->requestClass(),
                'IMPORTS' => $this->support->generatorImports(
                    'request',
                    [FormRequest::class],
                ),
                'TRAIT_IMPORTS' => $this->support->traitImports('request'),
                'TRAITS' => $this->support->traitUsage('request'),
            ],
        );
    }

    protected function buildRules(SchemaDefinition $schema): string
    {
        if (!$this->support->featureEnabled('request', 'schema_rules')) {
            return '        return [];';
        }

        $rules = $schema->validationRules();

        if ($rules === []) {
            return '        return [];';
        }

        $lines = [
            '        return [',
        ];

        foreach ($rules as $field => $fieldRules) {
            $exportedRules = implode(
                ', ',
                array_map(
                    static fn($rule) => var_export($rule, true),
                    array_values($fieldRules),
                ),
            );

            $lines[] = '            '
                . var_export($field, true)
                . ' => ['
                . $exportedRules
                . '],';
        }

        $lines[] = '        ];';

        return implode(PHP_EOL, $lines);
    }
}
