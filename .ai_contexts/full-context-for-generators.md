# Laravel Project Context

**Task:** Full context for generators

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
src/Console/Commands/ControllerCommands
src/Foundation
src/Foundation/Contracts
src/Foundation/Exceptions
src/Foundation/Support
src/Generators
src/Generators/Cache
src/Generators/Controller
src/Generators/Menu
src/Generators/Resource
src/Generators/Route
src/Generators/Schema
src/Generators/Stubs
```

---

## Project Files

### src/Console/Commands/ControllerCommands/GenerateControllersCommand.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Bitsnio\AsasFlow\Generators\Schema\SchemaGenerator;
use Bitsnio\AsasFlow\Generators\Route\RouteGenerator;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

class GenerateControllersCommand extends Command
{
    protected $signature =
        'asasflow:generate-controllers
        {module : The module name}
        {--force : Force regeneration}
        {--routes-only : Only generate routes}
        {--controllers-only : Only generate controllers}
        {--resources-only : Only generate resources}
        {--schemas-only : Only generate schemas}
        {--dry-run : Preview generated files}
        {--trace : Show route hierarchy}';

    protected $description =
        'Generate controllers, resources, schemas and API routes recursively from module menu configuration.';

    public function __construct(
        protected ControllerGenerator $controllerGenerator,
        protected ResourceGenerator $resourceGenerator,
        protected SchemaGenerator $schemaGenerator,
        protected RouteGenerator $routeGenerator,
        protected MenuBuilder $definitionBuilder,
        protected FileHandler $files,
        protected RepositoryInterface $moduleRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $moduleName =
                $this->argument('module');

            $module =
                $this->moduleRepository
                    ->find($moduleName);

            if (!$module) {
                $this->error(
                    "Module [{$moduleName}] does not exist!"
                );

                return self::FAILURE;
            }

            $menuPath =
                $this->files->getMenuPath(
                    $module
                );

            if (!$this->files->exists($menuPath)) {
                $this->error(
                    "Menu configuration not found at: {$menuPath}"
                );

                return self::FAILURE;
            }

            $menu = require $menuPath;

            $definitions =
                $this->definitionBuilder
                    ->build($menu);

            $options =
                $this->getOptions();

            if ($this->option('trace')) {
                $this->displayTrace(
                    $definitions
                );
            }

            if ($this->option('dry-run')) {
                $this->displayPreview(
                    $module,
                    $definitions,
                    $options
                );

                return self::SUCCESS;
            }

            $results =
                $this->generate(
                    $module,
                    $definitions,
                    $options
                );

            $this->displayResults(
                $results
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Generation failed: '
                . $e->getMessage()
            );

            if (
                $this->getOutput()
                    ->isVerbose()
            ) {
                $this->line(
                    $e->getTraceAsString()
                );
            }

            return self::FAILURE;
        }
    }

    protected function getOptions(): array
    {
        return [
            'force' =>
                (bool) $this->option('force'),

            'routesOnly' =>
                (bool) $this->option('routes-only'),

            'controllersOnly' =>
                (bool) $this->option(
                    'controllers-only'
                ),

            'resourcesOnly' =>
                (bool) $this->option(
                    'resources-only'
                ),

            'schemasOnly' =>
                (bool) $this->option(
                    'schemas-only'
                ),
        ];
    }

    protected function generate(
        $module,
        array $definitions,
        array $options
    ): array {
        $results = [
            'controllers' => [],
            'resources' => [],
            'schemas' => [],
            'routes' => [],
        ];

        $only = array_filter([
            'routes' =>
                $options['routesOnly'],

            'controllers' =>
                $options['controllersOnly'],

            'resources' =>
                $options['resourcesOnly'],

            'schemas' =>
                $options['schemasOnly'],
        ]);

        if (count($only) > 1) {
            throw new \InvalidArgumentException(
                'Only one generation-only option may be used.'
            );
        }

        if (
            !$only ||
            isset($only['controllers'])
        ) {
            $results['controllers'] =
                $this->controllerGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['resources'])
        ) {
            $results['resources'] =
                $this->resourceGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['schemas'])
        ) {
            $results['schemas'] =
                $this->schemaGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        if (
            !$only ||
            isset($only['routes'])
        ) {
            $results['routes'] =
                $this->routeGenerator
                    ->generate(
                        $module,
                        $definitions,
                        $options
                    );
        }

        return $results;
    }

    protected function displayPreview(
        $module,
        array $definitions,
        array $options
    ): void {
        $this->info(
            "Dry run - Module: {$module->getName()}"
        );

        $changes = [];

        $only = array_filter([
            'routes' =>
                $options['routesOnly'],

            'controllers' =>
                $options['controllersOnly'],

            'resources' =>
                $options['resourcesOnly'],

            'schemas' =>
                $options['schemasOnly'],
        ]);

        if (
            !$only ||
            isset($only['controllers'])
        ) {
            $changes = [
                ...$changes,
                ...$this->controllerGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['resources'])
        ) {
            $changes = [
                ...$changes,
                ...$this->resourceGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['schemas'])
        ) {
            $changes = [
                ...$changes,
                ...$this->schemaGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        if (
            !$only ||
            isset($only['routes'])
        ) {
            $changes = [
                ...$changes,
                ...$this->routeGenerator
                    ->preview(
                        $module,
                        $definitions,
                        $options
                    ),
            ];
        }

        foreach ($changes as $change) {
            $this->line(
                sprintf(
                    '  %s %s: %s',
                    strtoupper(
                        $change['action']
                        ?? 'CREATE'
                    ),
                    $change['type']
                        ?? 'file',
                    $change['file']
                        ?? ''
                )
            );
        }

        $this->info(
            'Total: '
            . count($changes)
            . ' file(s).'
        );
    }

    protected function displayTrace(
        array $definitions
    ): void {
        $this->info(
            'Route hierarchy:'
        );

        $this->traceNodes(
            $definitions,
            0
        );
    }

    protected function traceNodes(
        array $definitions,
        int $level
    ): void {
        foreach ($definitions as $definition) {
            $this->line(
                str_repeat('  ', $level)
                . '- '
                . $definition->routePath()
            );

            $this->traceNodes(
                $definition->children,
                $level + 1
            );
        }
    }

    protected function displayResults(
        array $results
    ): void {
        $this->newLine();

        $this->info(
            'Generation complete.'
        );

        foreach (
            ['controllers', 'resources', 'schemas']
            as $type
        ) {
            if (empty($results[$type])) {
                continue;
            }

            $this->line(
                "\n"
                . ucfirst($type)
                . ':'
            );

            foreach (
                $results[$type]
                as $result
            ) {
                $this->line(
                    "  {$result['action']}: "
                    . "{$result['name']}"
                );

                if (
                    $this->getOutput()
                        ->isVerbose()
                ) {
                    $this->line(
                        "    {$result['full_path']}"
                    );
                }
            }
        }

        foreach (
            $results['routes']
            as $route
        ) {
            $this->line(
                "\nRoutes: "
                . "{$route['action']} "
                . "{$route['path']}"
            );
        }
    }
}
```

### src/Foundation/Contracts/GeneratorInterface.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Contracts;

interface GeneratorInterface
{
    public function generate($module, array $structure, array $options = []): array;
    public function preview($module, array $structure, array $options = []): array;
}

```

### src/Foundation/Support/FileHandler.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHandler
{
    public function exists(string $path): bool
    {
        return File::exists($path);
    }

    public function read(string $path): string
    {
        return File::get($path);
    }

    public function writeFile(
        string $path,
        string $content,
        bool $force = false
    ): void {
        if (!$force && $this->exists($path)) {
            return;
        }

        $this->ensureDirectoryExists(dirname($path));

        File::put($path, $content);
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    public function findPhpFiles(string $path): array
    {
        $files = [];

        if (!$this->exists($path)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile() &&
                $file->getExtension() === 'php'
            ) {
                $relative = str_replace(
                    $path . DIRECTORY_SEPARATOR,
                    '',
                    $file->getPathname()
                );

                $relative = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    $relative
                );

                $files[
                    preg_replace('/\.php$/', '', $relative)
                ] = $file->getMTime();
            }
        }

        return $files;
    }

    public function getMenuPath($module): string
    {
        return $module->getPath() . '/config/menu.php';
    }

    public function getRoutesPath($module): string
    {
        return $module->getPath() . '/Routes/api.php';
    }

    public function getControllerPath(
        $module,
        string $controllerPath
    ): string {
        return $module->getPath()
            . '/App/Http/Controllers/'
            . $controllerPath
            . '.php';
    }

    public function getResourcePath(
        $module,
        string $resourcePath
    ): string {
        return $module->getPath()
            . '/App/Http/Resources/'
            . $resourcePath
            . '.php';
    }

    public function getSchemaPath(
        $module,
        string $schemaName
    ): string {
        return $module->getPath()
            . '/schema/'
            . $schemaName
            . '.json';
    }
}
```

### src/Foundation/Support/StubRenderer.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use RuntimeException;

class StubRenderer
{
    public function __construct(
        protected FileHandler $files,
    ) {}

    protected function basePath(): string
    {
        return dirname(__DIR__, 2)
            . '/Generators/Stubs';
    }

    public function render(
        string $stub,
        array $replacements = []
    ): string {
        foreach ($replacements as $key => $value) {
            $stub = str_replace(
                '{{ ' . $key . ' }}',
                (string) $value,
                $stub
            );

            $stub = str_replace(
                '{{' . $key . '}}',
                (string) $value,
                $stub
            );
        }

        return $stub;
    }

    public function renderFile(
        string $stubName,
        array $replacements = []
    ): string {
        $path = rtrim($this->basePath(), '/\\')
            . '/'
            . ltrim($stubName, '/\\');

        if (!$this->files->exists($path)) {
            throw new RuntimeException(
                "Stub not found: {$path}"
            );
        }

        return $this->render(
            $this->files->read($path),
            $replacements
        );
    }
}
```

### src/Generators/Cache/CacheObserverGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Cache;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CacheObserverGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $module, string $model, array $config = []): string
    {
        $modelClass = Str::studly($model);
        $observerClass = "{$modelClass}CacheObserver";
        
        $namespace = "Modules\\{$module}\\Observers";
        $modelNamespace = "Modules\\{$module}\\Models";
        
        $path = base_path("Modules/{$module}/Observers/{$observerClass}.php");
        
        $tags = $this->buildTags($config['tags'] ?? [], $module);
        $relations = $this->buildRelations($config['relations'] ?? []);
        
        $stub = $this->getStub();
        
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model_namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ tags }}',
                '{{ relations }}',
            ],
            [
                $namespace,
                $modelNamespace,
                $observerClass,
                $modelClass,
                $tags,
                $relations,
            ],
            $stub
        );
        
        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        
        return $path;
    }

    protected function buildTags(array $tags, string $module): string
    {
        $defaults = ["'{$module}-service-{$module}'"];
        
        foreach ($tags as $tag) {
            $defaults[] = "'{$tag}'";
        }
        
        return implode(",\n        ", $defaults);
    }

    protected function buildRelations(array $relations): string
    {
        if (empty($relations)) {
            return '[]';
        }

        $items = [];
        foreach ($relations as $relation => $config) {
            $items[] = "'{$relation}' => ['on_update' => true, 'on_delete' => " . ($config['on_delete'] ?? 'false') . "]";
        }

        return "[\n            " . implode(",\n            ", $items) . "\n        ]";
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use {{ model_namespace }}\{{ model }};
use Bitsnio\AsasFlow\Features\Cache\Observers\ModelCacheObserver;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheInvalidator;
use Bitsnio\AsasFlow\Features\Cache\Services\CacheKeyGenerator;

class {{ class }} extends ModelCacheObserver
{
    protected array $cacheTags = [
        {{ tags }}
    ];

    protected array $cacheInvalidationRelations = {{ relations }};

    public function __construct(
        CacheInvalidator $invalidator,
        CacheKeyGenerator $keyGenerator,
    ) {
        parent::__construct($invalidator, $keyGenerator);
    }
}
STUB;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}

```

### src/Generators/Controller/ControllerGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;

class ControllerGenerator
    implements GeneratorInterface
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
        if ($definition->controllerClass) {
            $relative =
                $definition->controllerRelativePath();

            $path =
                $this->files->getControllerPath(
                    $module,
                    $relative
                );

            $exists =
                $this->files->exists($path);

            if (
                !$exists ||
                ($options['force'] ?? false)
            ) {
                $this->files->writeFile(
                    $path,
                    $this->buildContent(
                        $module,
                        $definition
                    ),
                    true
                );
            }

            $results[] = [
                'name' =>
                    $definition->controllerClass,
                'path' => $relative,
                'full_path' => $path,
                'action' =>
                    !$exists
                        ? 'created'
                        : (
                            ($options['force'] ?? false)
                                ? 'updated'
                                : 'skipped'
                        ),
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
        if ($definition->controllerClass) {
            $relative =
                $definition->controllerRelativePath();

            $path =
                $this->files->getControllerPath(
                    $module,
                    $relative
                );

            $results[] = [
                'action' =>
                    $this->files->exists($path)
                        ? 'update'
                        : 'create',
                'file' => $path,
                'type' => 'controller',
                'name' =>
                    $definition->controllerClass,
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

    protected function buildContent(
        $module,
        MenuDefinition $definition
    ): string {
        $modelClass =
            $definition->modelClass();

        $modelPath =
            $module->getPath()
            . '/App/Models/'
            . $modelClass
            . '.php';

        $requestClass =
            $definition->requestClass();

        $requestPath =
            $module->getPath()
            . '/App/Http/Requests/'
            . $requestClass
            . '.php';

        $resourceClass =
            $definition->resourceClass;

        $resourcePath = $resourceClass
            ? $this->files->getResourcePath(
                $module,
                $definition->resourceRelativePath()
            )
            : null;

        $hasModel =
            $this->files->exists($modelPath);

        $hasRequest =
            $this->files->exists($requestPath);

        $hasResource =
            $resourcePath
                ? $this->files->exists(
                    $resourcePath
                )
                : false;

        $resourceImport = $hasResource
            ? "use Modules\\{$module->getName()}"
                . "\\App\\Http\\Resources\\"
                . str_replace(
                    '/',
                    '\\',
                    $definition->resourceRelativePath()
                )
                . ';'
            : '';

        return $this->stubs->renderFile(
            'controller.stub',
            [
                'NAMESPACE' =>
                    $this->namespace(
                        $module,
                        $definition
                    ),

                'CLASS' =>
                    $definition->controllerClass,

                'MODEL_IMPORT' =>
                    $hasModel
                        ? "use Modules\\{$module->getName()}"
                            . "\\App\\Models\\{$modelClass};"
                        : '',

                'REQUEST_IMPORT' =>
                    $hasRequest
                        ? "use Modules\\{$module->getName()}"
                            . "\\App\\Http\\Requests\\{$requestClass};"
                        : '',

                'RESOURCE_IMPORT' =>
                    $resourceImport,

                'METHODS' =>
                    $this->methods(
                        $definition,
                        $hasModel,
                        $hasRequest,
                        $hasResource
                    ),
            ]
        );
    }

    protected function namespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Controllers";

        $relative =
            $definition->controllerNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function methods(
        MenuDefinition $definition,
        bool $hasModel,
        bool $hasRequest,
        bool $hasResource
    ): string {
        $methods = [];

        foreach (
            $definition->routeActions()
            as $action
        ) {
            $methods[] = match ($action) {
                'index' =>
                    $this->indexMethod(
                        $definition,
                        $hasModel,
                        $hasResource
                    ),

                'store' =>
                    $this->storeMethod(
                        $definition,
                        $hasModel,
                        $hasRequest,
                        $hasResource
                    ),

                'show' =>
                    $this->showMethod(
                        $definition,
                        $hasModel,
                        $hasResource
                    ),

                'update' =>
                    $this->updateMethod(
                        $definition,
                        $hasModel,
                        $hasRequest,
                        $hasResource
                    ),

                'destroy' =>
                    $this->destroyMethod(
                        $definition,
                        $hasModel
                    ),

                default =>
                    throw new \InvalidArgumentException(
                        "Unsupported route action [{$action}]."
                    ),
            };
        }

        return implode(
            "\n\n",
            $methods
        );
    }

    protected function indexMethod(
        MenuDefinition $d,
        bool $hasModel,
        bool $hasResource
    ): string {
        $body = $hasModel
            ? (
                $hasResource
                    ? "return {$d->resourceClass}::collection("
                        . "{$d->modelClass()}::paginate());"
                    : "return {$d->modelClass()}::paginate();"
            )
            : 'return response()->json([]);';

        return <<<PHP
    public function index()
    {
        {$body}
    }
PHP;
    }

    protected function storeMethod(
        MenuDefinition $d,
        bool $hasModel,
        bool $hasRequest,
        bool $hasResource
    ): string {
        if (!$hasModel) {
            $body =
                "return response()->json("
                . "['message' => 'Store not implemented'], "
                . "501);";
        } else {
            $request =
                $hasRequest
                    ? '$request->validated()'
                    : '$request->all()';

            $body =
                "\$item = {$d->modelClass()}"
                . "::create({$request});\n\n        "
                . (
                    $hasResource
                        ? "return new {$d->resourceClass}(\$item);"
                        : 'return $item;'
                );
        }

        return <<<PHP
    public function store(Request \$request)
    {
        {$body}
    }
PHP;
    }

    protected function showMethod(
        MenuDefinition $d,
        bool $hasModel,
        bool $hasResource
    ): string {
        $variable =
            $d->parameterName();

        $body = !$hasModel
            ? "return response()->json("
                . "['message' => 'Show not implemented'], "
                . "501);"
            : (
                $hasResource
                    ? "return new {$d->resourceClass}"
                        . "(\${$variable});"
                    : "return \${$variable};"
            );

        return <<<PHP
    public function show(\${$variable})
    {
        {$body}
    }
PHP;
    }

    protected function updateMethod(
        MenuDefinition $d,
        bool $hasModel,
        bool $hasRequest,
        bool $hasResource
    ): string {
        $variable =
            $d->parameterName();

        if (!$hasModel) {
            $body =
                "return response()->json("
                . "['message' => 'Update not implemented'], "
                . "501);";
        } else {
            $request =
                $hasRequest
                    ? '$request->validated()'
                    : '$request->all()';

            $body =
                "\${$variable}->update({$request});\n\n        "
                . (
                    $hasResource
                        ? "return new {$d->resourceClass}"
                            . "(\${$variable});"
                        : "return \${$variable};"
                );
        }

        return <<<PHP
    public function update(
        Request \$request,
        \${$variable}
    ) {
        {$body}
    }
PHP;
    }

    protected function destroyMethod(
        MenuDefinition $d,
        bool $hasModel
    ): string {
        $variable =
            $d->parameterName();

        $body = $hasModel
            ? "\${$variable}->delete();\n\n"
                . "        return response()->noContent();"
            : "return response()->json("
                . "['message' => 'Destroy not implemented'], "
                . "501);";

        return <<<PHP
    public function destroy(\${$variable})
    {
        {$body}
    }
PHP;
    }
}
```

### src/Generators/Menu/MenuBuilder.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuBuilder
{
    public function build(array $menu): array
    {
        $module = $menu['module'] ?? null;

        if (!is_array($module)) {
            throw new InvalidArgumentException(
                'Missing module configuration.'
            );
        }

        $moduleName = $module['name'] ?? null;

        if (
            !is_string($moduleName) ||
            trim($moduleName) === ''
        ) {
            throw new InvalidArgumentException(
                'Module name is required.'
            );
        }

        return [
            $this->buildNode(
                $module,
                $moduleName,
                [],
                []
            ),
        ];
    }

    public function flatten(
        array $definitions
    ): array {
        $flat = [];

        foreach ($definitions as $definition) {
            $this->flattenNode(
                $definition,
                $flat
            );
        }

        return $flat;
    }

    protected function flattenNode(
        MenuDefinition $definition,
        array &$flat
    ): void {
        $flat[$definition->permissionKey()] = $definition;

        foreach ($definition->children as $child) {
            $this->flattenNode(
                $child,
                $flat
            );
        }
    }

    protected function buildNode(
        array $config,
        string $moduleName,
        array $parentPath,
        array $inheritedMiddleware,
    ): MenuDefinition {
        $name = $config['name'] ?? null;

        if (
            !is_string($name) ||
            trim($name) === ''
        ) {
            throw new InvalidArgumentException(
                'Every menu node must contain a non-empty name.'
            );
        }

        $path = [
            ...$parentPath,
            $name,
        ];

        $middleware = $this->middleware(
            $inheritedMiddleware,
            $config['middleware'] ?? [],
            empty($inheritedMiddleware) &&
                !isset($config['middleware'])
                ? ['api']
                : []
        );

        $type = $config['type']
            ?? (
                !empty($config['children'])
                ? 'group'
                : 'resource'
            );

        if (!in_array(
            $type,
            ['group', 'resource'],
            true
        )) {
            throw new InvalidArgumentException(
                "Invalid type [{$type}] for ["
                    . implode('.', $path)
                    . ']. Use group or resource.'
            );
        }

        $controllerClass =
            $this->controllerClass(
                $config,
                $type
            );

        $resourceClass =
            $this->resourceClass(
                $config,
                $type
            );

        $schemaName = null;

        if (($config['model'] ?? false) === true) {
            $schemaName =
                $config['schema']['name']
                ?? Str::studly($name);
        }

        $children = [];

        foreach ($config['children'] ?? [] as $child) {
            if (!is_array($child)) {
                throw new InvalidArgumentException(
                    'Every children entry must be an array at ['
                        . implode('.', $path)
                        . '].'
                );
            }

            $children[] = $this->buildNode(
                $child,
                $moduleName,
                $path,
                $middleware
            );
        }

        return new MenuDefinition(
            module: $moduleName,
            name: $name,
            title: $config['title']
                ?? Str::headline($name),
            path: $path,
            type: $type,
            middleware: $middleware,
            model: (bool) (
                $config['model'] ?? false
            ),
            routes: is_array(
                $config['routes'] ?? null
            )
                ? $config['routes']
                : [],
            permissions: is_array(
                $config['permissions'] ?? null
            )
                ? $config['permissions']
                : [],
            controllerClass: $controllerClass,
            resourceClass: $resourceClass,
            schemaName: $schemaName,
            children: $children,
            config: $config,
        );
    }

    protected function controllerClass(
        array $config,
        string $type
    ): ?string {
        if (
            ($config['controller']['enabled']
                ?? true) === false
        ) {
            return null;
        }

        if (
            $type !== 'resource' &&
            !isset($config['controller']['class'])
        ) {
            return null;
        }

        return $config['controller']['class']
            ?? Str::studly($config['name'])
            . 'Controller';
    }

    protected function resourceClass(
        array $config,
        string $type
    ): ?string {
        if (
            ($config['resource']['enabled']
                ?? true) === false
        ) {
            return null;
        }

        if (
            $type !== 'resource' &&
            !isset($config['resource']['class'])
        ) {
            return null;
        }

        return $config['resource']['class']
            ?? Str::studly($config['name'])
            . 'Resource';
    }

    protected function middleware(
        array ...$sets
    ): array {
        $result = [];

        foreach ($sets as $set) {
            foreach ($set as $middleware) {
                if (!in_array(
                    $middleware,
                    $result,
                    true
                )) {
                    $result[] = $middleware;
                }
            }
        }

        return $result;
    }
}

```

### src/Generators/Menu/MenuDefinition.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Illuminate\Support\Str;

class MenuDefinition
{
    public function __construct(
        public readonly string $module,
        public readonly string $name,
        public readonly string $title,
        public readonly array $path,
        public readonly string $type,
        public readonly array $middleware = [],
        public readonly bool $model = false,
        public readonly array $routes = [],
        public readonly array $permissions = [],
        public readonly ?string $controllerClass = null,
        public readonly ?string $resourceClass = null,
        public readonly ?string $schemaName = null,
        public readonly array $children = [],
        public readonly array $config = [],
    ) {}

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isResource(): bool
    {
        return $this->type === 'resource';
    }

    public function routePath(): string
    {
        return implode(
            '/',
            array_map(
                [Str::class, 'kebab'],
                $this->path
            )
        );
    }

    public function permissionKey(): string
    {
        return implode(
            '.',
            array_map(
                [Str::class, 'kebab'],
                $this->path
            )
        );
    }

    public function controllerNamespace(): string
    {
        return $this->relativeNamespace(
            array_slice($this->path, 1, -1)
        );
    }

    public function controllerRelativePath(): string
    {
        $namespace = $this->controllerNamespace();

        return ($namespace
            ? str_replace('\\', '/', $namespace) . '/'
            : '')
            . $this->controllerClass;
    }

    public function resourceNamespace(): string
    {
        return $this->relativeNamespace(
            array_slice($this->path, 1, -1)
        );
    }

    public function resourceRelativePath(): string
    {
        $namespace = $this->resourceNamespace();

        return ($namespace
            ? str_replace('\\', '/', $namespace) . '/'
            : '')
            . $this->resourceClass;
    }

    protected function relativeNamespace(
        array $segments
    ): string {
        return implode(
            '\\',
            array_map(
                [Str::class, 'studly'],
                $segments
            )
        );
    }

    public function modelClass(): string
    {
        return $this->config['model_name']
            ?? $this->config['model']['class']
            ?? Str::studly($this->name);
    }

    public function requestClass(): string
    {
        return $this->config['request_name']
            ?? $this->config['request']['class']
            ?? $this->modelClass() . 'Request';
    }

    public function routeActions(): array
    {
        if (!empty($this->routes)) {
            return array_values(
                array_unique($this->routes)
            );
        }

        return match ($this->config['routes_type'] ?? 'full') {
            'index', 'list' => ['index'],
            'create', 'store' => ['store'],
            'show' => ['show'],
            'update' => ['update'],
            'delete', 'destroy' => ['destroy'],
            default => [
                'index',
                'store',
                'show',
                'update',
                'destroy',
            ],
        };
    }

    public function parameterName(): string
    {
        return Str::camel($this->modelClass());
    }
}

```

### src/Generators/MenuGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Console\View\Components\Factory as ComponentFactory;

class MenuGenerator
{
    protected Filesystem $filesystem;
    protected $module;
    protected string $moduleName;
    protected ?ComponentFactory $component;

    public function __construct($module, string $moduleName, ?ComponentFactory $component = null)
    {
        $this->filesystem = new Filesystem();
        $this->module = $module;
        $this->moduleName = $moduleName;
        $this->component = $component;
    }

    /**
     * Generate menu configuration file
     */
    public function generate(): void
    {

        $path = $this->module->getModulePath($this->moduleName) . '/Config/menu.php';

        try {
            if (!$this->filesystem->isDirectory(dirname($path))) {
                $this->filesystem->makeDirectory(dirname($path), 0755, true);
            }
            $this->filesystem->put($path, $this->getStubContents());
            $this->component?->info("Generated menu config at: $path");
        } catch (\Throwable $e) {
            // Re-throw so the command layer can decide whether to rollback
            throw new \RuntimeException("MenuGenerator failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get menu stub contents
     */
    protected function getStubContents(): string
    {
        $stubPath = __DIR__ . '/../Console/Commands/Stubs/menu.stub';

        if (!$this->filesystem->exists($stubPath)) {
            throw new \RuntimeException("Stub file not found at: {$stubPath}");
        }

        return $this->replaceStubPlaceholders(
            $this->filesystem->get($stubPath)
        );
    }
    /**
     * Replace stub placeholders
     */
    protected function replaceStubPlaceholders(string $stub): string
    {
        return str_replace(
            ['$MODULE_NAME$', '$LOWER_NAME$', '$STUDLY_NAME$'],
            [$this->moduleName, strtolower($this->moduleName), Str::studly($this->moduleName)],
            $stub
        );
    }
}

```

### src/Generators/ModuleSettingsGenerator.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators;

use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleSettingsGenerator
{
    protected Filesystem $filesystem;

    protected $module;

    protected string $moduleName;

    protected ?ComponentFactory $component;

    public function __construct(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ) {
        $this->filesystem = new Filesystem();

        $this->module = $module;

        $this->moduleName = Str::studly($moduleName);

        $this->component = $component;
    }

    /**
     * Generate all settings files for the module.
     */
    public function generate(): void
    {
        // $this->generateSettingsClass();

        // $this->generateSettingsMigration();

        $this->generateSettingsConfig();

        $this->updateServiceProvider();

        // $this->updateModuleComposer();
    }

    /**
     * Generate Settings class.
     */
    protected function generateSettingsClass(): void
    {
        $path = $this->getSettingsClassPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info(
                "Settings class already exists: {$path}"
            );

            return;
        }

        $this->filesystem->put(
            $path,
            $this->buildClassStub()
        );

        $this->component?->info(
            "Settings class generated: {$path}"
        );
    }

    /**
     * Generate Spatie Settings migration.
     *
     * This migration creates the two standard properties:
     *
     *     {module}.enabled
     *     {module}.values
     */
    protected function generateSettingsMigration(): void
    {
        $path = $this->getSettingsMigrationPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info("Settings migration already exists: {$path}");
            return;
        }

        $this->filesystem->put(
            $path,
            $this->buildMigrationStub()
        );

        $this->component?->info(
            "Settings migration generated: {$path}"
        );
    }

    /**
     * Generate module settings configuration.
     */
    protected function generateSettingsConfig(): void
    {
        $path = $this->getSettingsConfigPath();

        $this->ensureDirectory(dirname($path));

        if ($this->filesystem->exists($path)) {
            $this->component?->info(
                "Settings config already exists: {$path}"
            );

            return;
        }

        $stub = $this->filesystem->get(
            $this->getStubPath('settings-config.stub')
        );

        $content = str_replace(
            [
                '$CLASS$',
            ],
            [
                $this->getSettingsClass(),
            ],
            $stub
        );

        $this->filesystem->put(
            $path,
            $content
        );

        $this->component?->info(
            "Settings config generated: {$path}"
        );
    }

    /**
     * Update module ServiceProvider.
     *
     * IMPORTANT:
     *
     * We DO NOT register the settings class into
     * Spatie's settings.settings config here.
     *
     * We only load the module's config/settings.php.
     */
    protected function updateServiceProvider(): void
    {
        $providerPath = $this->getServiceProviderPath();

        if (! $this->filesystem->exists($providerPath)) {
            $this->component?->warn(
                "ServiceProvider not found: {$providerPath}"
            );

            return;
        }

        $content = $this->filesystem->get($providerPath);

        $marker = '// [MODULE-SETTINGS-CONFIG-AUTO]';

        if (str_contains($content, $marker)) {
            $this->component?->info(
                'ServiceProvider already configured for settings.'
            );

            return;
        }

        $configExpression =
            "dirname(__DIR__, 2) . '/config/settings.php'";

        $configKey =
            'modules.' .
            $this->getModuleConfigKey() .
            '.settings';

        $registration = <<<PHP
        {$marker}
        \$this->mergeConfigFrom(
            {$configExpression},
            '{$configKey}'
        );
PHP;

        if (
            preg_match(
                '/public function register\(\)[^{]*\{/',
                $content
            )
        ) {
            $content = preg_replace(
                '/(public function register\(\)[^{]*\{)/',
                "$1\n{$registration}",
                $content,
                1
            );
        } else {
            $method = <<<PHP

    public function register(): void
    {
{$registration}
    }

PHP;

            $position = strrpos($content, '}');

            if ($position === false) {
                throw new RuntimeException(
                    "Unable to update ServiceProvider: {$providerPath}"
                );
            }

            $content =
                substr($content, 0, $position) .
                $method .
                substr($content, $position);
        }

        $this->filesystem->put(
            $providerPath,
            $content
        );

        $this->component?->info(
            "ServiceProvider updated: {$providerPath}"
        );
    }

    /**
     * Update module composer.json so the Settings directory is
     * PSR-4 autoloadable.
     */
    protected function updateModuleComposer(): void
    {
        $path = $this->getModuleComposerPath();

        if (! $this->filesystem->exists($path)) {
            $this->component?->warn(
                "Module composer.json not found: {$path}"
            );

            return;
        }

        $json = json_decode(
            $this->filesystem->get($path),
            true
        );

        if (! is_array($json)) {
            throw new RuntimeException(
                "Invalid composer.json: {$path}"
            );
        }

        $prefix =
            $this->getModuleNamespace() .
            '\\Settings\\';

        $json['autoload']['psr-4'][$prefix] = 'Settings/';

        $content = json_encode(
            $json,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
        );

        if ($content === false) {
            throw new RuntimeException(
                "Unable to encode composer.json: {$path}"
            );
        }

        $this->filesystem->put(
            $path,
            $content . PHP_EOL
        );

        $this->component?->info(
            "Module composer.json updated: {$path}"
        );

        $this->component?->warn(
            'Run composer dump-autoload after module generation.'
        );
    }

    /**
     * Settings class stub.
     */
    protected function buildClassStub(): string
    {
        $stub = $this->filesystem->get(
            $this->getStubPath('settings-class.stub')
        );

        return str_replace(
            [
                '$NAMESPACE$',
                '$CLASS$',
                '$MODULE$',
            ],
            [
                $this->getSettingsNamespace(),
                $this->getSettingsClassName(),
                $this->getModuleConfigKey(),
            ],
            $stub
        );
    }

    /**
     * Settings migration stub.
     */
    protected function buildMigrationStub(): string
    {
        $stub = $this->filesystem->get(
            $this->getStubPath('settings-migration.stub')
        );

        return str_replace(
            [
                '$GROUP$',
            ],
            [
                $this->getModuleConfigKey(),
            ],
            $stub
        );
    }

    protected function getStubPath(string $stubName): string
    {
        return dirname(__DIR__)
            . '/Console/Commands/Stubs/'
            . ltrim($stubName, '/');
    }

    protected function getSettingsClassPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) .
            '/Settings/' .
            $this->getSettingsClassName() .
            '.php';
    }

    protected function getSettingsMigrationPath(): string
    {
        return $this->module->getModulePath($this->moduleName)
            . '/database/migrations/'
            . date('Y_m_d_His')
            . '_create_'
            . strtolower($this->moduleName)
            . '_settings_defaults.php';
    }

    protected function getSettingsConfigPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) . '/config/settings.php';
    }

    protected function getServiceProviderPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) .
            '/app/Providers/' .
            $this->moduleName .
            'ServiceProvider.php';
    }

    protected function getModuleComposerPath(): string
    {
        return $this->module->getModulePath(
            $this->moduleName
        ) . '/composer.json';
    }

    protected function getSettingsClassName(): string
    {
        return $this->moduleName . 'Settings';
    }

    protected function getSettingsNamespace(): string
    {
        $namespace = config(
            'modules.namespace',
            'Modules'
        );

        return $namespace .
            '\\' .
            $this->moduleName .
            '\\Settings';
    }

    protected function getModuleNamespace(): string
    {
        $namespace = config(
            'modules.namespace',
            'Modules'
        );

        return $namespace .
            '\\' .
            $this->moduleName;
    }

    protected function getSettingsClass(): string
    {
        return $this->getSettingsNamespace() .
            '\\' .
            $this->getSettingsClassName();
    }

    protected function getModuleConfigKey(): string
    {
        return Str::kebab($this->moduleName);
    }

    protected function exportPath(string $path): string
    {
        return var_export($path, true);
    }

    protected function ensureDirectory(string $path): void
    {
        if (! $this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory(
                $path,
                0755,
                true
            );
        }
    }
}

```

### src/Generators/Resource/ResourceGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Resource;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;

class ResourceGenerator
implements GeneratorInterface
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
        if ($definition->resourceClass) {
            $relative =
                $definition->resourceRelativePath();

            $path =
                $this->files->getResourcePath(
                    $module,
                    $relative
                );

            $exists =
                $this->files->exists($path);

            if (
                !$exists ||
                ($options['force'] ?? false)
            ) {
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
                $definition->resourceClass,
                'path' => $relative,
                'full_path' => $path,
                'action' =>
                !$exists
                    ? 'created'
                    : (
                        ($options['force'] ?? false)
                        ? 'updated'
                        : 'skipped'
                    ),
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
        if ($definition->resourceClass) {
            $relative =
                $definition->resourceRelativePath();

            $path =
                $this->files->getResourcePath(
                    $module,
                    $relative
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'update'
                    : 'create',
                'file' => $path,
                'type' => 'resource',
                'name' =>
                $definition->resourceClass,
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

    protected function content(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->stubs->renderFile(
            'resource.stub',
            [
                'NAMESPACE' =>
                $this->namespace(
                    $module,
                    $definition
                ),

                'CLASS' =>
                $definition->resourceClass,

                'FIELDS' =>
                $this->fields(
                    $definition->config['resource']['fields'] ?? null
                ),
            ]
        );
    }

    protected function namespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Resources";

        $relative =
            $definition->resourceNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function fields(
        ?array $fields
    ): string {
        if (!$fields) {
            return
                '        return parent::toArray($request);';
        }

        $lines = [
            '        return [',
        ];

        foreach ($fields as $field) {
            if (is_string($field)) {
                $lines[] =
                    "            '{$field}' => "
                    . "\$this->{$field},";
                continue;
            }

            if (
                is_array($field) &&
                isset($field['name'])
            ) {
                $name = $field['name'];
                $source =
                    $field['source'] ?? $name;

                $lines[] =
                    "            '{$name}' => "
                    . "\$this->{$source},";
            }
        }

        $lines[] = '        ];';

        return implode(
            "\n",
            $lines
        );
    }
}

```

### src/Generators/Route/RouteGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Illuminate\Support\Str;

class RouteGenerator
    implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected RouteNameGenerator $routeNameGenerator,
    ) {}

    public function generate(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $path =
            $this->files->getRoutesPath($module);

        $exists =
            $this->files->exists($path);

        $content =
            $this->buildRouteFile(
                $module,
                $definitions
            );

        $this->files->writeFile(
            $path,
            $content,
            true
        );

        return [[
            'path' => $path,
            'action' =>
                $exists ? 'updated' : 'created',
        ]];
    }

    public function preview(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $path =
            $this->files->getRoutesPath($module);

        return [[
            'action' =>
                $this->files->exists($path)
                    ? 'update'
                    : 'create',
            'file' => $path,
            'type' => 'routes',
        ]];
    }

    protected function buildRouteFile(
        $module,
        array $definitions
    ): string {
        $controllers =
            $this->collectControllers(
                $definitions
            );

        $content =
            "<?php\n\n"
            . "use Illuminate\\Support\\Facades\\Route;\n";

        foreach ($controllers as $definition) {
            $namespace =
                $this->controllerNamespace(
                    $module,
                    $definition
                );

            $content .=
                "use {$namespace}\\"
                . "{$definition->controllerClass};\n";
        }

        $content .= "\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode(
                $definition,
                [],
                0
            );
        }

        return rtrim($content) . "\n";
    }

    protected function buildNode(
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $segment =
            Str::kebab($definition->name);

        $additionalMiddleware =
            array_values(
                array_diff(
                    $definition->middleware,
                    $parentMiddleware
                )
            );

        $content = '';

        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $content .= $this->resourceRoute(
                $definition,
                $segment,
                $level,
                $additionalMiddleware
            );
        }

        if (!empty($definition->children)) {
            $content .=
                $indent
                . "Route::prefix('{$segment}')";

            if ($additionalMiddleware) {
                $content .=
                    "\n"
                    . $indent
                    . "    ->middleware("
                    . $this->phpArray(
                        $additionalMiddleware
                    )
                    . ')';
            }

            $content .=
                "\n"
                . $indent
                . "    ->group(function () {\n";

            foreach (
                $definition->children
                as $child
            ) {
                $content .= $this->buildNode(
                    $child,
                    $definition->middleware,
                    $level + 1
                );
            }

            $content .=
                $indent
                . "});\n\n";
        }

        return $content;
    }

    protected function resourceRoute(
        MenuDefinition $definition,
        string $segment,
        int $level,
        array $additionalMiddleware
    ): string {
        $indent =
            str_repeat('    ', $level);

        $actions =
            $definition->routeActions();

        $resource =
            "Route::apiResource("
            . "'{$segment}', "
            . "{$definition->controllerClass}::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .=
                '->only('
                . $this->phpArray($actions)
                . ')';
        }

        $resource .=
            '->names('
            . $this->routeNames(
                $definition,
                $actions
            )
            . ');';

        if (!$additionalMiddleware) {
            return
                $indent
                . $resource
                . "\n\n";
        }

        return
            $indent
            . 'Route::middleware('
            . $this->phpArray(
                $additionalMiddleware
            )
            . ")->group(function () {\n"
            . $indent
            . '    '
            . $resource
            . "\n"
            . $indent
            . "});\n\n";
    }

    protected function routeNames(
        MenuDefinition $definition,
        array $actions
    ): string {
        $names = [];

        foreach ($actions as $action) {
            $names[$action] =
                $this->routeNameGenerator
                    ->generateRouteName(
                        $definition->path,
                        $action
                    );
        }

        return $this->phpArray($names);
    }

    protected function collectControllers(
        array $definitions
    ): array {
        $result = [];

        foreach ($definitions as $definition) {
            if ($definition->controllerClass) {
                $result[] = $definition;
            }

            $result = [
                ...$result,
                ...$this->collectControllers(
                    $definition->children
                ),
            ];
        }

        return $result;
    }

    protected function controllerNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        $namespace =
            "Modules\\{$module->getName()}"
            . "\\App\\Http\\Controllers";

        $relative =
            $definition->controllerNamespace();

        return $relative
            ? $namespace . '\\' . $relative
            : $namespace;
    }

    protected function phpArray(
        array $values
    ): string {
        $parts = [];

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $parts[] =
                    var_export($value, true);
            } else {
                $parts[] =
                    var_export($key, true)
                    . ' => '
                    . var_export($value, true);
            }
        }

        return '['
            . implode(', ', $parts)
            . ']';
    }
}
```

### src/Generators/Route/RouteNameGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Route;

use Illuminate\Support\Str;

class RouteNameGenerator
{
    protected const MAX_ROUTE_NAME_LENGTH = 60;
    protected const HASH_LENGTH = 8;

    public function generateRouteName(
        array $pathParts,
        ?string $action = null
    ): string {
        $parts = array_map(
            [Str::class, 'kebab'],
            $pathParts
        );

        $full =
            implode('.', $parts)
            . (
                $action
                    ? '.' . Str::kebab($action)
                    : ''
            );

        if (
            strlen($full) <=
            self::MAX_ROUTE_NAME_LENGTH
        ) {
            return $full;
        }

        $prefix = implode(
            '_',
            array_map(
                fn ($part) =>
                    Str::substr($part, 0, 1),
                $parts
            )
        );

        $hash = substr(
            md5(implode('|', $parts)),
            0,
            self::HASH_LENGTH
        );

        return $prefix
            . '_'
            . $hash
            . (
                $action
                    ? '.' . Str::kebab($action)
                    : ''
            );
    }

    public function getTraceInfo(
        string $generatedName,
        array $pathParts,
        ?string $action = null
    ): array {
        return [
            'generated' => $generatedName,
            'original_parts' => $pathParts,
            'action' => $action,
            'hash' => substr(
                md5(
                    implode(
                        '|',
                        array_map(
                            [Str::class, 'kebab'],
                            $pathParts
                        )
                    )
                ),
                0,
                self::HASH_LENGTH
            ),
            'nesting_level' =>
                count($pathParts),
            'was_shortened' =>
                $generatedName !==
                $this->generateRouteName(
                    $pathParts,
                    $action
                ),
        ];
    }
}
```

### src/Generators/Schema/SchemaGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Generators\Menu\MenuDefinition;
use Illuminate\Support\Str;

class SchemaGenerator
    implements GeneratorInterface
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
                ?? Str::studly($definition->name);

            $path =
                $this->files->getSchemaPath(
                    $module,
                    $name
                );

            $exists =
                $this->files->exists($path);

            if (
                !$exists ||
                ($options['force'] ?? false)
            ) {
                $this->files->writeFile(
                    $path,
                    $this->content($definition),
                    true
                );
            }

            $results[] = [
                'name' => $name,
                'path' =>
                    'schema/' . $name . '.json',
                'full_path' => $path,
                'action' =>
                    !$exists
                        ? 'created'
                        : (
                            ($options['force'] ?? false)
                                ? 'updated'
                                : 'skipped'
                        ),
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
        if ($definition->model) {
            $name =
                $definition->schemaName
                ?? Str::studly($definition->name);

            $path =
                $this->files->getSchemaPath(
                    $module,
                    $name
                );

            $results[] = [
                'action' =>
                    $this->files->exists($path)
                        ? 'update'
                        : 'create',
                'file' => $path,
                'type' => 'schema',
                'name' => $name,
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

    protected function content(
        MenuDefinition $definition
    ): string {
        $schema =
            $definition->config['schema'] ?? [];

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

                'PROPERTIES' =>
                    $this->json(
                        $schema['properties']
                        ?? []
                    ),

                'REQUIRED' =>
                    $this->json(
                        array_values(
                            $schema['required']
                            ?? []
                        )
                    ),

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
```

### src/Generators/Stubs/Controller.stub

```
<?php

namespace {{ NAMESPACE }};

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
{{ MODEL_IMPORT }}
{{ REQUEST_IMPORT }}
{{ RESOURCE_IMPORT }}

class {{ CLASS }} extends Controller
{
{{ METHODS }}
}
```

### src/Generators/Stubs/Resource.stub

```
<?php

namespace {{ NAMESPACE }};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {{ CLASS }} extends JsonResource
{
    public function toArray(Request $request): array
    {
{{ FIELDS }}
    }
}
```

### src/Generators/Stubs/Schema.stub

```
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "$id": "{{ NAME }}",
    "title": "{{ TITLE }}",
    "description": "{{ DESCRIPTION }}",
    "type": "object",
    "properties": {{ PROPERTIES }},
    "required": {{ REQUIRED }},
    "additionalProperties": {{ ADDITIONAL_PROPERTIES }}
}
```

