<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;

class SchemaGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    public function key(): string
    {
        return 'schemas';
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

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results,
    ): void {
        if ($definition->model) {
            $name = $definition->schemaName();
            $path = $this->files->getSchemaPath($module, $name);
            $exists = $this->files->exists($path);

            /*
             * Schema becomes user-owned after creation.
             * Never overwrite it, even with --force.
             */
            if (!$exists) {
                $this->files->writeFile(
                    $path,
                    $this->content($definition),
                    true,
                );
            }

            $results[] = [
                'name' => $name,
                'path' => 'schema/' . $name . '.json',
                'full_path' => $path,
                'action' => $exists ? 'keep-user-schema' : 'created',
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

    protected function content(MenuDefinition $definition): string
    {
        $schema = is_array($definition->config['schema'] ?? null)
            ? $definition->config['schema']
            : [];

        return $this->stubs->renderFile(
            'schema.stub',
            [
                'NAME' => $definition->schemaName(),
                'TITLE' => $schema['title'] ?? $definition->title,
                'DESCRIPTION' => $schema['description'] ?? '',
                'PROPERTIES' => $this->json([]),
                'REQUIRED' => $this->json([]),
                'ADDITIONAL_PROPERTIES' => json_encode(
                    $schema['additionalProperties'] ?? true,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ),
            ],
        );
    }

    protected function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}