<?php

namespace Bitsnio\AsasFlow\Generators\Migration;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;
use Illuminate\Support\Str;

class MigrationGenerator
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    public function generate(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        $migrationPath =
            $module->getPath()
            . '/database/migrations';

        $table =
            $definition->config['model']['table']
            ?? $definition->config['table']
            ?? Str::snake(
                Str::plural(
                    $definition->modelClass()
                )
            );

        $existing =
            $this->findCreateMigration(
                $migrationPath,
                $table
            );

        if ($existing) {
            return [
                'action' => 'skipped-existing',
                'path' => $existing,
            ];
        }

        $timestamp =
            date('Y_m_d_His');

        $class =
            'Create'
            . Str::studly($table)
            . 'Table';

        $filename =
            $timestamp
            . '_create_'
            . $table
            . '_table.php';

        $path =
            $migrationPath
            . '/'
            . $filename;

        $this->files->ensureDirectoryExists(
            $migrationPath
        );

        $this->files->writeFile(
            $path,
            $this->content(
                $class,
                $table,
                $schema
            ),
            true
        );

        return [
            'action' => 'created',
            'path' => $path,
        ];
    }

    protected function findCreateMigration(
        string $directory,
        string $table
    ): ?string {
        if (!$this->files->exists($directory)) {
            return null;
        }

        foreach (
            glob(
                $directory
                . '/*_create_'
                . $table
                . '_table.php'
            ) ?: []
            as $file
        ) {
            return $file;
        }

        return null;
    }

    protected function content(
        string $class,
        string $table,
        SchemaDefinition $schema
    ): string {
        $columns = [];

        foreach (
            $schema->migrationColumns()
            as $column
        ) {
            $columns[] =
                $this->column(
                    $column
                );
        }

        $body =
            implode(
                PHP_EOL,
                array_map(
                    fn ($line) =>
                        '            '
                        . $line,
                    $columns
                )
            );

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
{$body}
            \$table->timestamps();
            \$table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
    }

    protected function column(
        array $column
    ): string {
        $name = $column['name'];
        $type = $column['type'];

        if ($name === 'id') {
            return '';
        }

        if (
            in_array(
                $name,
                [
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                true
            )
        ) {
            return '';
        }

        $line = match ($type) {
            'integer' =>
                "\$table->integer('{$name}')",

            'number' =>
                "\$table->decimal('{$name}', 18, 4)",

            'boolean' =>
                "\$table->boolean('{$name}')",

            'array',
            'object' =>
                "\$table->json('{$name}')",

            default =>
                "\$table->string('{$name}')",
        };

        if ($column['nullable']) {
            $line .= '->nullable()';
        }

        if (
            $column['default'] !== null
        ) {
            $default =
                var_export(
                    $column['default'],
                    true
                );

            $line .=
                "->default({$default})";
        }

        return $line . ';';
    }
}