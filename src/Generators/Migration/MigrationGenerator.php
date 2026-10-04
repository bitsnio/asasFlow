<?php

namespace Bitsnio\AsasFlow\Generators\Migration;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
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
        $migrationPath = $module->getPath(). '/database/migrations';

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
                    fn($line) =>
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


    protected function column(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];

        if (in_array($name, [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ], true)) {
            return '';
        }

        $quotedName = var_export($name, true);

        $line = match ($type) {
            'integer' => "\$table->integer({$quotedName})",
            'number' => "\$table->decimal({$quotedName}, 18, 4)",
            'boolean' => "\$table->boolean({$quotedName})",
            'array', 'object' => "\$table->{$this->jsonColumnMethod()}({$quotedName})",
            default => $this->stringColumn($quotedName, $column),
        };

        if ($column['nullable'] ?? false) {
            $line .= '->nullable()';
        }

        if (array_key_exists('default', $column) && $column['default'] !== null) {
            $line .= '->default(' . var_export($column['default'], true) . ')';
        }

        return $line . ';';
    }


    protected function jsonColumnMethod(): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()
            ->getDriverName();

        return match ($driver) {
            'pgsql' => 'jsonb',
            'mysql' => 'json',
            default => 'json',
        };
    }
    
    protected function stringColumn(
        string $quotedName,
        array $column
    ): string {
        return match ($column['format'] ?? null) {
            'date' => "\$table->date({$quotedName})",
            'date-time' => "\$table->dateTime({$quotedName})",
            default => "\$table->string({$quotedName})",
        };
    }

}
