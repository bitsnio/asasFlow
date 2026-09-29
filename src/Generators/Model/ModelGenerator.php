<?php

namespace Bitsnio\AsasFlow\Generators\Model;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;

class ModelGenerator
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    public function generate(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        $path =
            $this->files->getModelPath(
                $module,
                $definition->modelClass()
            );

        if (!$this->files->exists($path)) {
            $this->files->writeFile(
                $path,
                $this->content(
                    $module,
                    $definition,
                    $schema
                ),
                true
            );

            return [
                'action' => 'created',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (!GeneratedBlock::has(
            $document,
            'generated-fillable'
        )) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-fillable',
            $this->buildFillable($schema)
        );

        $this->files->updateGeneratedBlock(
            $path,
            'generated-casts',
            $this->buildCasts($schema)
        );

        return [
            'action' => 'updated',
            'path' => $path,
        ];
    }

    protected function content(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): string {
        $namespace =
            "Modules\\{$module->getName()}\\App\\Models";

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {$definition->modelClass()} extends Model
{
    use SoftDeletes;

    protected \$fillable = [
        // @asasflow:generated-fillable:start
        // @asasflow:generated-fillable:end
    ];

    protected \$casts = [
        // @asasflow:generated-casts:start
        // @asasflow:generated-casts:end
    ];
}

PHP;
    }

    protected function buildFillable(
        SchemaDefinition $schema
    ): string {
        $lines = [];

        foreach (
            array_keys($schema->properties())
            as $field
        ) {
            $lines[] =
                '        '
                . var_export(
                    $field,
                    true
                )
                . ',';
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }

    protected function buildCasts(
        SchemaDefinition $schema
    ): string {
        $casts = $schema->casts();

        $lines = [];

        foreach ($casts as $field => $cast) {
            $lines[] =
                '        '
                . var_export(
                    $field,
                    true
                )
                . ' => '
                . var_export(
                    $cast,
                    true
                )
                . ',';
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }
}