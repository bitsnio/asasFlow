<?php

namespace Bitsnio\AsasFlow\Generators\Request;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;

class RequestGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
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
        if (!$definition->resourceClass()) {
            return [
                'action' => 'skipped',
                'reason' => 'resource-disabled',
            ];
        }

        $path = $this->files->getRequestPath(
            $module,
            $this->requestRelativePath(
                $definition
            )
        );

        if (!$this->files->exists($path)) {
            return [
                'action' => 'missing',
                'path' => $path,
            ];
        }

        if (!GeneratedBlock::has(
            $this->files->read($path),
            'generated-rules'
        )) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $content =
            $this->buildRules(
                $schema
            );

        $this->files->updateGeneratedBlock(
            $path,
            'generated-rules',
            $content
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
        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $relative =
                $this->requestRelativePath(
                    $definition
                );

            $path =
                $this->files->getRequestPath(
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
                $definition->requestClass(),

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
        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $path =
                $this->files->getRequestPath(
                    $module,
                    $this->requestRelativePath(
                        $definition
                    )
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'keep'
                    : 'create',

                'file' => $path,

                'type' => 'request',
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

    protected function requestRelativePath(
        MenuDefinition $definition
    ): string {
        $namespace =
            $definition->controllerNamespace();

        return (
            $namespace
            ? str_replace(
                '\\',
                '/',
                $namespace
            ) . '/'
            : ''
        )
            . $definition->requestClass();
    }

    protected function content(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}\\App\\Http\\Requests";

        $relative =
            $definition->controllerNamespace();

        if ($relative) {
            $namespace .= '\\'
                . $relative;
        }

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$definition->requestClass()} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // @asasflow:generated-rules:start
        return [];
        // @asasflow:generated-rules:end
    }
}

PHP;
    }

    protected function buildRules(
        SchemaDefinition $schema
    ): string {
        $rules = $schema->validationRules();

        if (!$rules) {
            return '        return [];';
        }

        $lines = [
            '        return [',
        ];

        foreach ($rules as $field => $fieldRules) {
            $encoded = implode(
                '|',
                $fieldRules
            );

            $lines[] =
                "            "
                . var_export(
                    $field,
                    true
                )
                . " => "
                . var_export(
                    $encoded,
                    true
                )
                . ",";
        }

        $lines[] = '        ];';

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
