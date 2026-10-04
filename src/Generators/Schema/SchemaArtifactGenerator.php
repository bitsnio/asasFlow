<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Migration\MigrationGenerator;
use Bitsnio\AsasFlow\Generators\Model\ModelGenerator;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;

class SchemaArtifactGenerator
{
    public function __construct(
        protected ModelGenerator $modelGenerator,
        protected MigrationGenerator $migrationGenerator,
        protected RequestGenerator $requestGenerator,
        protected ResourceGenerator $resourceGenerator,
        protected ControllerGenerator $controllerGenerator,
    ) {}

    public function generate(
        $module,
        array $definitions
    ): array {
        $results = [];

        foreach ($definitions as $definition) {
            $this->generateNode(
                $module,
                $definition,
                $results
            );
        }

        return $results;
    }

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array &$results
    ): void {
        if ($definition->model) {
            $schemaName =
                $definition->schemaName
                ?? $definition->modelClass();

            $schemaPath =
                $module->getPath()
                . '/schema/'
                . $schemaName
                . '.json';

            if (!is_file($schemaPath)) {
                $results[] = [
                    'type' => 'schema',
                    'action' => 'missing',
                    'name' => $schemaName,
                    'path' => $schemaPath,
                ];
            } else {
                $schema = SchemaDefinition::fromFile($schemaPath);

                $results['models'][] =
                    $this->modelGenerator->generate(
                        $module,
                        $definition,
                        $schema
                    );

                $results['migrations'][] =
                    $this->migrationGenerator->generate(
                        $module,
                        $definition,
                        $schema
                    );

                $results['requests'][] =
                    $this->requestGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );

                $results['resources'][] =
                    $this->resourceGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );

                $results['controllers'][] =
                    $this->controllerGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );
            }
        }

        foreach ($definition->children as $child) {
            $this->generateNode(
                $module,
                $child,
                $results
            );
        }
    }
}
