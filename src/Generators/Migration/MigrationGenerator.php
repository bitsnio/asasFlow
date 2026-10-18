<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Migration;

use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MigrationGenerator implements SchemaArtifactGeneratorInterface
{
    protected const IGNORED_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    public function key(): string
    {
        return 'migrations';
    }

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema,
        array $options = [],
    ): array {
        $migrationPath = $module->migrationsPath();
        $table = $definition->tableName();

        $existing = $this->findCreateMigration(
            $migrationPath,
            $table,
        );

        $force = (bool) ($options['force'] ?? false);

        if ($existing && !$force) {
            return [
                'action' => 'skipped-existing',
                'path' => $existing,
            ];
        }

        $path = $existing ?? rtrim($migrationPath, '/')
            . '/'
            . date('Y_m_d_His')
            . '_create_'
            . $table
            . '_table.php';

        $class = 'Create' . Str::studly($table) . 'Table';

        $this->files->ensureDirectoryExists($migrationPath);

        $this->files->writeFile(
            $path,
            $this->content($class, $table, $schema),
            true,
        );

        return [
            'action' => $existing ? 'regenerated' : 'created',
            'path' => $path,
        ];
    }

    protected function findCreateMigration(
        string $directory,
        string $table,
    ): ?string {
        if (!$this->files->exists($directory)) {
            return null;
        }

        $matches = glob($directory . '/*_create_' . $table . '_table.php');

        return !empty($matches) ? $matches[0] : null;
    }

    protected function content(
        string $class,
        string $table,
        SchemaDefinition $schema,
    ): string {
        $columns = [];

        foreach ($schema->migrationColumns() as $column) {
            $line = $this->column($column);

            if ($line !== '') {
                $columns[] = '            ' . $line;
            }
        }

        return $this->stubs->renderFile(
            'create-migration.stub',
            [
                'class' => $class,
                'table' => $table,
                'columns' => implode(PHP_EOL, $columns),
            ],
        );
    }

    protected function column(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];

        if (in_array($name, self::IGNORED_COLUMNS, true)) {
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
        try {
            $driver = DB::connection()->getDriverName();
        } catch (Throwable) {
            $driver = 'mysql';
        }

        return match ($driver) {
            'pgsql' => 'jsonb',
            default => 'json',
        };
    }

    protected function stringColumn(
        string $quotedName,
        array $column,
    ): string {
        return match ($column['format'] ?? null) {
            'date' => "\$table->date({$quotedName})",
            'date-time' => "\$table->dateTime({$quotedName})",
            'uuid' => "\$table->uuid({$quotedName})",
            default => "\$table->string({$quotedName})",
        };
    }
}