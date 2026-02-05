<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Bitsnio\Modules\Contracts\RepositoryInterface;

class  MakeJsonSchemaCommand extends Command
{
    protected $signature = 'module:make-jsonschemas {module : The module name}';
    protected $description = 'Generate JSON Schema files for the given module menu configuration';

    protected $repository;

    public function __construct(RepositoryInterface $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    public function handle(): void
    {
        $moduleName = $this->argument('module');
        $module = $this->repository->find($moduleName);

        if (!$module) {
            $this->error("Module [{$moduleName}] does not exist!");
            return;
        }

        $menuPath = $module->getPath() . '/config/menu.php';
        if (!file_exists($menuPath)) {
            $this->error("Menu configuration not found for module [{$moduleName}]!");
            return;
        }

        $menu = require $menuPath;
        $this->generateJsonSchemas($module, $menu['module']);
    }

    protected function generateJsonSchemas($module, array $menuStructure, string $parentPath = ''): void
    {
        $moduleName = Str::lower($module->getName());
        $schemasPath = $module->getPath() . '/Schemas';

        if (!file_exists($schemasPath)) {
            mkdir($schemasPath, 0755, true);
        }

        $currentPath = $parentPath
            ? $parentPath . '.' . Str::lower($menuStructure['name'])
            : Str::lower($menuStructure['name']);

        // 🔥 Strip module name from start of dot path
        $relativePath = Str::startsWith($currentPath, $moduleName . '.')
            ? Str::after($currentPath, $moduleName . '.')
            : $currentPath;

        if (($menuStructure['schema'] ?? false) === true) {
            $this->createSchemaFile(
                $schemasPath,
                $moduleName,
                $relativePath,
                $menuStructure
            );
        }

        if (isset($menuStructure['sub_module'])) {
            foreach ($menuStructure['sub_module'] as $subModule) {
                $this->generateJsonSchemas(
                    $module,
                    $subModule,
                    $currentPath
                );
            }
        }

        if (isset($menuStructure['actions'])) {
            foreach ($menuStructure['actions'] as $action) {
                if (($action['schema'] ?? false) === true) {
                    $actionPath = $currentPath . '.' . Str::lower($action['name']);

                    $relativeActionPath = Str::startsWith($actionPath, $moduleName . '.')
                        ? Str::after($actionPath, $moduleName . '.')
                        : $actionPath;

                    $this->createSchemaFile(
                        $schemasPath,
                        $moduleName,
                        $relativeActionPath,
                        $action
                    );
                }
            }
        }
    }


    protected function createSchemaFile(
        string $schemasPath,
        string $moduleName,
        string $dotPath,
        array $config
    ): void {
        $filename = str_replace('.', '_', $dotPath);
        $schemaFile = "{$schemasPath}/{$filename}.json";

        if (!file_exists($schemaFile)) {
            $schema = [
                '$schema' => "https://json-schema.org/draft-07/schema#",
                'title' => $config['title'] ?? Str::title(str_replace(['.', '_'], ' ', $dotPath)),
                'type' => 'object',
                'route_key' => $dotPath,
                'properties' => $this->generateSchemaProperties($config),
                'required' => ['name'],
                'metadata' => [
                    'module' => $moduleName,
                    'created_at' => now()->toISOString(),
                    'menu_item' => $config
                ]
            ];

            file_put_contents($schemaFile, json_encode($schema, JSON_PRETTY_PRINT));
            $this->info("Schema file created: {$filename}.json");
        }
    }

    protected function generateSchemaProperties(array $config): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'description' => 'Unique identifier'
            ],
            'name' => [
                'type' => 'string',
                'maxLength' => 255,
                'description' => $config['title'] ?? 'Name'
            ],
            'created_at' => [
                'type' => 'string',
                'format' => 'date-time'
            ]
        ];
    }
}
