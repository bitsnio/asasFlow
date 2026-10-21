<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Migration\MigrationGenerator;
use Bitsnio\AsasFlow\Generators\Model\ModelGenerator;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use InvalidArgumentException;

class SchemaArtifactGenerator
{
    /**
     * @var array<string, SchemaArtifactGeneratorInterface>
     */
    protected array $generators = [];

    public function __construct(
        ModelGenerator $modelGenerator,
        MigrationGenerator $migrationGenerator,
        RequestGenerator $requestGenerator,
        ResourceGenerator $resourceGenerator,
        ControllerGenerator $controllerGenerator,
    ) {
        $this->register([
            $modelGenerator,
            $migrationGenerator,
            $requestGenerator,
            $resourceGenerator,
            $controllerGenerator,
        ]);
    }

    /**
     * @param array<SchemaArtifactGeneratorInterface> $generators
     */
    public function register(array $generators): void
    {
        foreach ($generators as $generator) {
            $this->generators[$generator->key()] = $generator;
        }
    }

    public function allowedTypes(): array
    {
        return ['all', ...array_keys($this->generators)];
    }

    public function generate(
        $module,
        array $definitions,
        array $options = [],
    ): array {
        $only = strtolower(trim((string) ($options['only'] ?? 'all')));
        $force = (bool) ($options['force'] ?? false);
        $targetSchema = $this->normalizeSchemaOption($options['schema'] ?? null);

        if (!in_array($only, $this->allowedTypes(), true)) {
            throw new InvalidArgumentException(
                'Unsupported schema artifact type: ' . $only . '. Allowed values: ' . implode(', ', $this->allowedTypes()),
            );
        }

        $activeGenerators = $only === 'all'
            ? $this->generators
            : [$only => $this->generators[$only]];

        $results = [];

        foreach ($definitions as $definition) {
            $this->generateNode(
                $module,
                $definition,
                $activeGenerators,
                [
                    'force' => $force,
                    'schema' => $targetSchema,
                ],
                $results,
            );
        }

        return $results;
    }

    /**
     * @param array<string, SchemaArtifactGeneratorInterface> $activeGenerators
     */
    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $activeGenerators,
        array $options,
        array &$results,
    ): void {
        if ($definition->model && $this->matchesTargetSchema($definition, $options['schema'] ?? null)) {
            $schemaName = $definition->schemaName();
            $schemaPath = $module->getPath() . '/schema/' . $schemaName . '.json';

            if (!is_file($schemaPath)) {
                $results['schemas'][] = [
                    'action' => 'missing',
                    'name' => $schemaName,
                    'path' => $schemaPath,
                ];
            } else {
                $schema = SchemaDefinition::fromFile($schemaPath);

                foreach ($activeGenerators as $key => $generator) {
                    
                    // ModelGenerator and MigrationGenerator are automatically skipped when disabled in the schema:
                    if (!$schema->shouldGenerate($key)) {
                        $results[$key][] = [
                            'action' => 'skipped (disabled-in-schema)',
                            'path' => $schemaPath,
                        ];

                        continue;
                    }
                    $results[$key][] = $generator->syncFromSchema(
                        $module,
                        $definition,
                        $schema,
                        $options,
                    );
                }
            }
        }

        foreach ($definition->children as $child) {
            $this->generateNode(
                $module,
                $child,
                $activeGenerators,
                $options,
                $results,
            );
        }
    }

    protected function normalizeSchemaOption(?string $schema): ?string
    {
        if ($schema === null) {
            return null;
        }

        $trimmed = trim($schema);

        if ($trimmed === '') {
            return null;
        }

        return preg_replace('/\.json$/i', '', $trimmed);
    }

    protected function matchesTargetSchema(
        MenuDefinition $definition,
        ?string $targetSchema,
    ): bool {
        if ($targetSchema === null) {
            return true;
        }

        return strcasecmp($definition->schemaName(), $targetSchema) === 0
            || strcasecmp($definition->modelClass(), $targetSchema) === 0
            || strcasecmp($definition->name, $targetSchema) === 0;
    }
}