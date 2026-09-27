<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Illuminate\Support\Str;

class SchemaGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
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

    protected function generateNode(
        $module,
        MenuDefinition $definition,
        array $options,
        array &$results
    ): void {
        if ($definition->model) {
            $name =
                $definition->schemaName
                ?? Str::studly(
                    $definition->name
                );

            $path =
                $this->files->getSchemaPath(
                    $module,
                    $name
                );

            $exists =
                $this->files->exists($path);

            /*
             * Schema becomes user-owned after creation.
             *
             * Never overwrite it, even with --force.
             */
            if (!$exists) {
                $this->files->writeFile(
                    $path,
                    $this->content(
                        $definition
                    ),
                    true
                );
            }

            $results[] = [
                'name' => $name,

                'path' =>
                    'schema/'
                    . $name
                    . '.json',

                'full_path' => $path,

                'action' =>
                    $exists
                        ? 'keep-user-schema'
                        : 'created',
            ];
        }

        foreach (
            $definition->children
            as $child
        ) {
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
        if ($definition->model) {
            $name =
                $definition->schemaName
                ?? Str::studly(
                    $definition->name
                );

            $path =
                $this->files->getSchemaPath(
                    $module,
                    $name
                );

            $results[] = [
                'action' =>
                    $this->files->exists($path)
                        ? 'keep-user-schema'
                        : 'create',

                'file' => $path,

                'type' => 'schema',

                'name' => $name,
            ];
        }

        foreach (
            $definition->children
            as $child
        ) {
            $this->previewNode(
                $module,
                $child,
                $results
            );
        }
    }

    protected function content(
        MenuDefinition $definition
    ): string {
        /*
         * Schema configuration here is only for
         * initial metadata/template values.
         *
         * Field definitions are NOT required in menu.php.
         */
        $schema =
            is_array(
                $definition->config['schema']
                ?? null
            )
                ? $definition->config['schema']
                : [];

        return $this->stubs->renderFile(
            'schema.stub',
            [
                'NAME' =>
                    $definition->schemaName
                    ?? Str::studly(
                        $definition->name
                    ),

                'TITLE' =>
                    $schema['title']
                    ?? $definition->title,

                'DESCRIPTION' =>
                    $schema['description']
                    ?? '',

                /*
                 * Important:
                 *
                 * Do NOT populate properties from
                 * menu.php.
                 *
                 * Schema starts empty and developer
                 * owns it after generation.
                 */
                'PROPERTIES' =>
                    $this->json([]),

                'REQUIRED' =>
                    $this->json([]),

                'ADDITIONAL_PROPERTIES' =>
                    json_encode(
                        $schema[
                            'additionalProperties'
                        ] ?? true,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    ),
            ]
        );
    }

    protected function json(
        mixed $value
    ): string {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );
    }
}