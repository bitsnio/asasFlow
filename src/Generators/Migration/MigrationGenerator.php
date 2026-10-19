<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Migration;

use Bitsnio\AsasFlow\Foundation\Contracts\SchemaArtifactGeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $this->files->ensureDirectoryExists($migrationPath);

        $table = $definition->tableName();
        $force = (bool) ($options['force'] ?? false);

        $existingCreateFile = $this->findCreateMigration($migrationPath, $table);

        // 1. If no create migration exists yet, or --force is passed: create/overwrite the create migration
        if (!$existingCreateFile || $force) {
            $path = $existingCreateFile ?? rtrim($migrationPath, '/')
                . '/'
                . date('Y_m_d_His')
                . '_create_'
                . $table
                . '_table.php';

            $class = 'Create' . Str::studly($table) . 'Table';

            $this->files->writeFile(
                $path,
                $this->buildCreateContent($class, $table, $definition, $schema),
                true,
            );

            return [
                'action' => $existingCreateFile ? 'regenerated' : 'created',
                'path' => $path,
            ];
        }

        // 2. If the create migration exists, check if it has already been executed in the DB
        if (!$this->isMigrationExecuted($existingCreateFile, $table)) {
            $class = 'Create' . Str::studly($table) . 'Table';
            $newContent = $this->buildCreateContent($class, $table, $definition, $schema);
            $currentContent = $this->files->read($existingCreateFile);

            if ($newContent === $currentContent) {
                return [
                    'action' => 'skipped (up-to-date)',
                    'path' => $existingCreateFile,
                ];
            }

            $this->files->writeFile($existingCreateFile, $newContent, true);

            return [
                'action' => 'updated-unrun-migration',
                'path' => $existingCreateFile,
            ];
        }

        // 3. The create migration HAS already been run!
        // Check if there are new columns in the schema that do not exist in the DB table yet.
        $missingColumns = $this->findMissingDatabaseColumns($table, $definition, $schema);

        if (empty($missingColumns)) {
            return [
                'action' => 'skipped (up-to-date)',
                'path' => $existingCreateFile,
            ];
        }

        // Reuse an un-run alter migration for this table if one is already pending,
        // otherwise create a new timestamped add_columns_to_{table}_table.php migration.
        $pendingAlterFile = $this->findPendingAlterMigration($migrationPath, $table);

        $alterPath = $pendingAlterFile ?? rtrim($migrationPath, '/')
            . '/'
            . date('Y_m_d_His')
            . '_add_columns_to_'
            . $table
            . '_table.php';

        $this->files->writeFile(
            $alterPath,
            $this->buildAlterContent($table, $missingColumns),
            true,
        );

        $addedNames = implode(', ', array_keys($missingColumns));

        return [
            'action' => ($pendingAlterFile ? 'updated-alter-migration' : 'created-alter-migration')
                . " (added: {$addedNames})",
            'path' => $alterPath,
        ];
    }

    /**
     * Builds the full column metadata list, prepending tenant_id and site_id
     * when single-database tenancy is configured.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function resolveAllColumns(
        string $table,
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): array {
        $columns = [];

        foreach ($this->tenancyColumns($table, $definition) as $name => $meta) {
            $columns[$name] = $meta;
        }

        foreach ($schema->migrationColumns() as $name => $column) {
            if (in_array($name, self::IGNORED_COLUMNS, true)) {
                continue;
            }

            // Do not overwrite tenancy columns if they were already prepended
            if (isset($columns[$name])) {
                continue;
            }

            $columns[$name] = $column;
        }

        return $columns;
    }

    /**
     * Determines if tenant_id and site_id should be injected for this table.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function tenancyColumns(string $table, MenuDefinition $definition): array
    {
        if (config('asasflow.tenancy.database_strategy', 'single') !== 'single') {
            return [];
        }

        // Allow per-menu override: `'tenancy' => false` in menu.php
        if (($definition->config['tenancy'] ?? true) === false) {
            return [];
        }

        $exemptTables = config('asasflow.tenancy.exempt_tables', [
            'tenants',
            'tenant_domains',
            'migrations',
        ]);

        if (in_array($table, $exemptTables, true)) {
            return [];
        }

        $columns = [];
        $tenantCol = (string) config('asasflow.tenancy.tenant_column', 'tenant_id');

        $columns[$tenantCol] = [
            'name' => $tenantCol,
            'type' => 'foreignId',
            'nullable' => false,
            'index' => true,
        ];

        $siteEnabled = (bool) config('asasflow.tenancy.site_enabled', true);
        $siteExemptTables = config('asasflow.tenancy.site_exempt_tables', ['sites']);

        if (
            $siteEnabled
            && !in_array($table, $siteExemptTables, true)
            && ($definition->config['site'] ?? true) !== false
        ) {
            $siteCol = (string) config('asasflow.tenancy.site_column', 'site_id');

            $columns[$siteCol] = [
                'name' => $siteCol,
                'type' => 'foreignId',
                'nullable' => (bool) config('asasflow.tenancy.site_nullable', true),
                'index' => true,
            ];
        }

        return $columns;
    }

    /**
     * Checks whether a migration file has already been executed in the database.
     */
    protected function isMigrationExecuted(string $filePath, string $table): bool
    {
        $migrationName = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            $migrationsTable = config('database.migrations', 'migrations');
            if (is_array($migrationsTable)) {
                $migrationsTable = $migrationsTable['table'] ?? 'migrations';
            }

            if (Schema::hasTable($migrationsTable)) {
                $recorded = DB::table($migrationsTable)
                    ->where('migration', $migrationName)
                    ->exists();

                if ($recorded) {
                    return true;
                }
            }

            // Fallback: if the table itself already exists in the database, treat create migration as executed
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Compares expected schema columns against live database columns (and already-executed alter migrations).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function findMissingDatabaseColumns(
        string $table,
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): array {
        $allColumns = $this->resolveAllColumns($table, $definition, $schema);

        try {
            if (!Schema::hasTable($table)) {
                return [];
            }

            $existingColumns = array_map(
                'strtolower',
                Schema::getColumnListing($table),
            );
        } catch (Throwable) {
            return [];
        }

        $missing = [];

        foreach ($allColumns as $name => $columnMeta) {
            if (!in_array(strtolower($name), $existingColumns, true)) {
                $missing[$name] = $columnMeta;
            }
        }

        return $missing;
    }

    protected function findCreateMigration(string $directory, string $table): ?string
    {
        if (!$this->files->exists($directory)) {
            return null;
        }

        $matches = glob($directory . '/*_create_' . $table . '_table.php');

        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Finds any *_add_columns_to_{table}_table.php migration that has NOT been run yet.
     */
    protected function findPendingAlterMigration(string $directory, string $table): ?string
    {
        if (!$this->files->exists($directory)) {
            return null;
        }

        $matches = glob($directory . '/*_add_columns_to_' . $table . '_table.php') ?: [];

        foreach (array_reverse($matches) as $file) {
            if (!$this->isAlterMigrationRecorded($file)) {
                return $file;
            }
        }

        return null;
    }

    protected function isAlterMigrationRecorded(string $filePath): bool
    {
        $migrationName = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            $migrationsTable = config('database.migrations', 'migrations');
            if (is_array($migrationsTable)) {
                $migrationsTable = $migrationsTable['table'] ?? 'migrations';
            }

            if (!Schema::hasTable($migrationsTable)) {
                return false;
            }

            return DB::table($migrationsTable)
                ->where('migration', $migrationName)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function buildCreateContent(
        string $class,
        string $table,
        MenuDefinition $definition,
        SchemaDefinition $schema,
    ): string {
        $lines = [];

        foreach ($this->resolveAllColumns($table, $definition, $schema) as $column) {
            $line = $this->column($column);

            if ($line !== '') {
                $lines[] = '            ' . $line;
            }
        }

        return $this->stubs->renderFile(
            'create-migration.stub',
            [
                'class' => $class,
                'table' => $table,
                'columns' => implode(PHP_EOL, $lines),
            ],
        );
    }

    /**
     * Builds the content for an incremental `Schema::table` alter migration.
     *
     * @param array<string, array<string, mixed>> $missingColumns
     */
    protected function buildAlterContent(string $table, array $missingColumns): string
    {
        $upLines = [];
        $downLines = [];

        foreach ($missingColumns as $name => $column) {
            $line = $this->column($column);

            if ($line !== '') {
                $upLines[] = '            ' . $line;
                $downLines[] = '                ' . var_export($name, true) . ',';
            }
        }

        return $this->stubs->renderFile(
            'update-migration.stub',
            [
                'table' => $table,
                'up_columns' => implode(PHP_EOL, $upLines),
                'down_columns' => implode(PHP_EOL, $downLines),
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
            'foreignId' => "\$table->foreignId({$quotedName})",
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

        if ($column['index'] ?? false) {
            $line .= '->index()';
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
