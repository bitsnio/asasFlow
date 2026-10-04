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
src/Generators/Migration
src/Generators/Model
src/Generators/Request
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
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Bitsnio\AsasFlow\Generators\Route\RouteGenerator;
use Bitsnio\AsasFlow\Generators\Schema\SchemaGenerator;
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
        {--requests-only : Only generate requests}
        {--resources-only : Only generate resources}
        {--schemas-only : Only generate schemas}
        {--dry-run : Preview generated files}
        {--trace : Show route hierarchy}';

    protected $description =
        'Generate controllers, requests, resources, schemas and API routes recursively from module menu configuration.';

    public function __construct(
        protected ControllerGenerator $controllerGenerator,
        protected RequestGenerator $requestGenerator,
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

            $menu =
                require $menuPath;

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
                (bool) $this->option(
                    'routes-only'
                ),

            'controllersOnly' =>
                (bool) $this->option(
                    'controllers-only'
                ),

            'requestsOnly' =>
                (bool) $this->option(
                    'requests-only'
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
            'requests' => [],
            'resources' => [],
            'schemas' => [],
            'routes' => [],
        ];

        $only = array_filter([
            'routes' =>
                $options['routesOnly'],

            'controllers' =>
                $options['controllersOnly'],

            'requests' =>
                $options['requestsOnly'],

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
            isset($only['requests'])
        ) {
            $results['requests'] =
                $this->requestGenerator
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

            'requests' =>
                $options['requestsOnly'],

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
            isset($only['requests'])
        ) {
            $changes = [
                ...$changes,
                ...$this->requestGenerator
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
                str_repeat(
                    '  ',
                    $level
                )
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
            [
                'controllers',
                'requests',
                'resources',
                'schemas',
            ] as $type
        ) {
            if (
                empty($results[$type])
            ) {
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
                    . ($result['name'] ?? '')
                );

                if (
                    $this->getOutput()
                        ->isVerbose()
                ) {
                    $this->line(
                        "    "
                        . ($result['full_path']
                            ?? '')
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

### src/Console/Commands/ControllerCommands/GenerateFromSchemaCommand.php

```php
<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use Bitsnio\AsasFlow\Generators\Schema\SchemaArtifactGenerator;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

class GenerateFromSchemaCommand extends Command
{
    protected $signature =
        'asasflow:generate-from-schema
        {module : The module name}
        {--trace : Show schema generation hierarchy}
        {--dry-run : Preview schema-driven generation}';

    protected $description =
        'Generate models, migrations, validation, resources and controller updates from module schemas.';

    public function __construct(
        protected SchemaArtifactGenerator $generator,
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

            $menu =
                require $menuPath;

            $definitions =
                $this->definitionBuilder
                    ->build($menu);

            if ($this->option('trace')) {
                $this->displayTrace(
                    $definitions
                );
            }

            if ($this->option('dry-run')) {
                $this->info(
                    'Dry run: schema artifacts were not generated.'
                );

                return self::SUCCESS;
            }

            $results =
                $this->generator->generate(
                    $module,
                    $definitions
                );

            $this->displayResults(
                $results
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(
                'Schema artifact generation failed: '
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

    protected function displayTrace(
        array $definitions
    ): void {
        $this->info(
            'Schema hierarchy:'
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
                str_repeat(
                    '  ',
                    $level
                )
                . '- '
                . $definition->permissionKey()
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
        $this->info(
            'Schema artifact generation complete.'
        );

        foreach ($results as $type => $items) {
            if (!is_array($items)) {
                continue;
            }

            $this->line(
                "\n"
                . ucfirst($type)
                . ':'
            );

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $this->line(
                    '  '
                    . ($item['action'] ?? 'processed')
                    . ': '
                    . ($item['path'] ?? '')
                );
            }
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
        return $module->getPath()
            . '/config/menu.php';
    }

    public function getRoutesPath($module): string
    {
        return $module->getPath()
            . '/Routes/api.php';
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

    public function getRequestPath(
        $module,
        string $requestPath
    ): string {
        return $module->getPath()
            . '/App/Http/Requests/'
            . $requestPath
            . '.php';
    }

    public function getModelPath(
        $module,
        string $modelPath
    ): string {
        return $module->getPath()
            . '/App/Models/'
            . $modelPath
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

    public function updateGeneratedBlock(
        string $path,
        string $block,
        string $content
    ): bool {
        if (!$this->exists($path)) {
            return false;
        }

        $document = $this->read($path);

        if (!GeneratedBlock::has($document, $block)) {
            return false;
        }

        $document = GeneratedBlock::replace(
            $document,
            $block,
            $content
        );

        File::put($path, $document);

        return true;
    }
}
```

### src/Foundation/Support/GeneratedBlock.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use RuntimeException;

class GeneratedBlock
{
    public const PREFIX = '@asasflow:';

    public static function wrap(
        string $name,
        string $content,
        string $indent = ''
    ): string {
        return $indent . '// @asasflow:' . $name . ':start'
            . PHP_EOL
            . $content
            . (str_ends_with($content, PHP_EOL) ? '' : PHP_EOL)
            . $indent . '// @asasflow:' . $name . ':end';
    }

    public static function replace(
        string $document,
        string $name,
        string $content
    ): string {
        $pattern = self::pattern($name);

        $updated = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($content): string {
                return $matches[1]
                    . $content
                    . $matches[3];
            },
            $document,
            1
        );

        if ($updated === null) {
            throw new RuntimeException(
                "Unable to update generated block [{$name}]."
            );
        }

        return $updated;
    }

    public static function has(
        string $document,
        string $name
    ): bool {
        return preg_match(
            self::pattern($name),
            $document
        ) === 1;
    }

    protected static function pattern(string $name): string
    {
        $marker = preg_quote($name, '/');

        return '/(^[ \t]*\/\/ @asasflow:'
            . $marker
            . ':start[^\r\n]*\r?\n)'
            . '(.*?)'
            . '(^[ \t]*\/\/ @asasflow:'
            . $marker
            . ':end[^\r\n]*$)/ms';
    }
}
```

### src/Foundation/Support/GeneratorSupport.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

class GeneratorSupport
{
    public function moduleNamespace($module): string
    {
        return "Modules\\{$module->getName()}";
    }

    public function controllerNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Controllers',
            $definition->controllerNamespace()
        );
    }

    public function requestNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Requests',
            $definition->controllerNamespace()
        );
    }

    public function resourceNamespace(
        $module,
        MenuDefinition $definition
    ): string {
        return $this->appendNamespace(
            $this->moduleNamespace($module) . '\\Http\\Resources',
            $definition->resourceNamespace()
        );
    }

    public function modelNamespace($module): string
    {
        return $this->moduleNamespace($module) . '\\Models';
    }

    protected function appendNamespace(
        string $base,
        ?string $relative
    ): string {
        $relative = trim((string) $relative, '\\');

        return $relative !== ''
            ? $base . '\\' . $relative
            : $base;
    }

    /**
     * Return normalized, unique fully qualified class names.
     */
    public function normalizeClasses(array $classes): array
    {
        $normalized = [];

        foreach ($classes as $class) {
            if (!is_string($class) || trim($class) === '') {
                continue;
            }

            $class = ltrim(trim($class), '\\');

            if (!in_array($class, $normalized, true)) {
                $normalized[] = $class;
            }
        }

        return $normalized;
    }

    /**
     * Format fully qualified class names as PHP import statements.
     */
    public function imports(array $classes): string
    {
        $lines = array_map(
            static fn(string $class): string => "use {$class};",
            $this->normalizeClasses($classes)
        );

        return implode(PHP_EOL, $lines);
    }

    public function configuredImports(string $generator): array
    {
        $imports = config(
            "asasFlow.generators.{$generator}.imports",
            []
        );

        return is_array($imports)
            ? $this->normalizeClasses($imports)
            : [];
    }

    /**
     * Required imports are supplied by the generator.
     * Configured imports are optional project-wide additions.
     */
    public function generatorImports(
        string $generator,
        array $requiredImports = []
    ): string {
        return $this->imports([
            ...$requiredImports,
            ...$this->configuredImports($generator),
        ]);
    }

    public function configuredTraits(string $generator): array
    {
        $traits = config(
            "asasFlow.generators.{$generator}.traits",
            []
        );

        return is_array($traits)
            ? $this->normalizeClasses($traits)
            : [];
    }

    public function traitImports(string $generator): string
    {
        return $this->imports(
            $this->configuredTraits($generator)
        );
    }

    /**
     * Generate trait usage statements for inside a PHP class.
     */
    public function traitUsage(string $generator): string
    {
        $traits = $this->configuredTraits($generator);

        if ($traits === []) {
            return '';
        }

        $shortNames = [];

        foreach ($traits as $trait) {
            $shortName = class_basename($trait);

            if (in_array($shortName, $shortNames, true)) {
                throw new \InvalidArgumentException(
                    "Configured {$generator} traits contain duplicate "
                        . "short name [{$shortName}]. Use traits with unique names."
                );
            }

            $shortNames[] = $shortName;
        }

        return implode(
            PHP_EOL,
            array_map(
                static fn(string $trait): string =>
                '    use ' . class_basename($trait) . ';',
                $traits
            )
        );
    }

    public function featureEnabled(
        string $generator,
        string $feature
    ): bool {
        return (bool) config(
            "asasFlow.generators.{$generator}.features.{$feature}.enabled",
            false
        );
    }

    public function featureConfig(
        string $generator,
        string $feature,
        mixed $default = []
    ): mixed {
        return config(
            "asasFlow.generators.{$generator}.features.{$feature}",
            $default
        );
    }
}

```

### src/Foundation/Support/MenuDefinition.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Str;

class MenuDefinition
{
    public function __construct(
        public readonly string $module,
        public readonly string $name,
        public readonly string $title,
        public readonly array $path,
        public readonly string $type = 'group',
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

    /*
    |--------------------------------------------------------------------------
    | Type
    |--------------------------------------------------------------------------
    */

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isResource(): bool
    {
        return $this->type === 'resource';
    }

    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    /*
    |--------------------------------------------------------------------------
    | Generated Components
    |--------------------------------------------------------------------------
    */

    public function hasController(): bool
    {
        return $this->controllerClass !== null;
    }

    public function hasResource(): bool
    {
        return $this->resourceClass !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Menu / Permission Path
    |--------------------------------------------------------------------------
    */

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

    public function routePath(): string
    {
        $segments = array_map(
            [Str::class, 'kebab'],
            $this->path
        );

        if ($this->isAction()) {
            array_pop($segments);

            $actionPath = $this->actionPath();

            if ($actionPath !== '') {
                $segments[] = $actionPath;
            }
        }

        return implode('/', $segments);
    }

    public function routeSegment(): string
    {
        return $this->isAction()
            ? $this->actionPath()
            : Str::kebab($this->name);
    }

    /*
    |--------------------------------------------------------------------------
    | Action
    |--------------------------------------------------------------------------
    */

    public function actionMethod(): ?string
    {
        if (!$this->isAction()) {
            return null;
        }

        return strtoupper(
            $this->config['method'] ?? 'POST'
        );
    }

    public function actionPath(): string
    {
        if (!$this->isAction()) {
            return Str::kebab($this->name);
        }

        return trim(
            (string) (
                $this->config['path']
                ?? Str::kebab($this->name)
            ),
            '/'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Controller / Resource Namespace
    |--------------------------------------------------------------------------
    */

    public function controllerNamespace(): string
    {
        return $this->relativeNamespace($this->path);
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
        return $this->relativeNamespace($this->path);
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

    /*
    |--------------------------------------------------------------------------
    | Model / Request
    |--------------------------------------------------------------------------
    */

    public function modelClass(): string
    {
        $model = $this->config['model'] ?? null;

        if (is_array($model)) {
            return $model['class']
                ?? Str::studly($this->name);
        }

        return $this->config['model_name']
            ?? Str::studly($this->name);
    }

    public function requestClass(): string
    {
        $request = $this->config['request'] ?? null;

        if (is_array($request)) {
            return $request['class']
                ?? $this->modelClass() . 'Request';
        }

        return $this->config['request_name']
            ?? $this->modelClass() . 'Request';
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Routes
    |--------------------------------------------------------------------------
    */

    public function routeActions(): array
    {
        if (!empty($this->routes)) {
            return array_values(
                array_unique($this->routes)
            );
        }

        return match (
            $this->config['routes_type'] ?? 'full'
        ) {
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
        return Str::camel(
            $this->modelClass()
        );
    }
}
```

### src/Foundation/Support/MenuService.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\Modules\Contracts\RepositoryInterface;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MenuService
{
    public function __construct(
        protected RepositoryInterface $repository,
        protected MenuBuilder $builder,
    ) {}

    /**
     * Return MenuDefinition[] for one module or all modules.
     */
    public function getDefinitions(
        ?string $moduleName = null
    ): array {
        $modules = $moduleName
            ? [$this->repository->find($moduleName)]
            : $this->repository->all();

        $definitions = [];

        foreach ($modules as $module) {
            if (!$module) {
                continue;
            }

            $menuPath = $module->getPath()
                . '/config/menu.php';

            if (!is_file($menuPath)) {
                continue;
            }

            $menu = require $menuPath;

            if (!is_array($menu)) {
                continue;
            }

            foreach ($this->builder->build($menu) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Return menu definitions filtered by user permissions.
     *
     * Groups remain visible when at least one child
     * remains visible.
     */
    public function getMenus(
        ?string $moduleName = null,
        bool $filterByUserPermissions = false,
        $user = null,
    ): array {
        $definitions = $this->getDefinitions(
            $moduleName
        );

        if (!$filterByUserPermissions) {
            return $definitions;
        }

        $user ??= $this->authenticatedUser();

        if (!$user) {
            return [];
        }

        return $this->filterDefinitions(
            $definitions,
            $user
        );
    }

    protected function filterDefinitions(
        array $definitions,
        $user
    ): array {
        $result = [];

        foreach ($definitions as $definition) {
            $children = $this->filterDefinitions(
                $definition->children,
                $user
            );

            if ($definition->isGroup()) {
                if (empty($children)) {
                    continue;
                }

                $result[] = new MenuDefinition(
                    module: $definition->module,
                    name: $definition->name,
                    title: $definition->title,
                    path: $definition->path,
                    type: $definition->type,
                    middleware: $definition->middleware,
                    model: $definition->model,
                    routes: $definition->routes,
                    permissions: $definition->permissions,
                    controllerClass: $definition->controllerClass,
                    resourceClass: $definition->resourceClass,
                    schemaName: $definition->schemaName,
                    children: $children,
                    config: $definition->config,
                );

                continue;
            }

            $permission = $this->viewPermission(
                $definition
            );

            if (
                $permission &&
                $user->can($permission)
            ) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    protected function viewPermission(
        MenuDefinition $definition
    ): ?string {
        return $definition->permissionKey()
            . '.view';
    }

    protected function authenticatedUser()
    {
        try {
            return JWTAuth::parseToken()
                ->authenticate();
        } catch (\Throwable) {
            return null;
        }
    }

    public function allModules(): array
    {
        $modules = $this->repository->all();

        $names = [];

        foreach ($modules as $module) {
            $names[] = $module->getName();
        }

        return $names;
    }
}

```

### src/Foundation/Support/PermissionDefinition.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

class PermissionDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $guard = 'api',
        public readonly ?string $module = null,
        public readonly ?string $menuPath = null,
        public readonly ?string $method = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'guard_name' => $this->guard,
            'module' => $this->module,
            'menu_path' => $this->menuPath,
            'method' => $this->method,
        ];
    }
}

```

### src/Foundation/Support/PermissionResolver.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionResolver
{
    protected array $methodMap = [
        'GET' => 'view',
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    public function resolve(
        string $uri,
        string $method
    ): ?string {
        $action = $this->actionForMethod($method);

        if (!$action) {
            return null;
        }

        $uri = $this->normalizeUri($uri);

        if ($uri === '') {
            return null;
        }

        $parts = collect(
            explode('/', $uri)
        )
            ->filter()
            ->map(function ($part) {
                if ($this->isParameter($part)) {
                    return null;
                }

                return Str::kebab($part);
            })
            ->filter()
            ->values()
            ->all();

        if (empty($parts)) {
            return null;
        }

        return implode('.', $parts)
            . '.'
            . $action;
    }

    public function resolveRequest(
        Request $request
    ): ?string {
        return $this->resolve(
            $request->path(),
            $request->method()
        );
    }

    public function actionForMethod(
        string $method
    ): ?string {
        return $this->methodMap[
            strtoupper($method)
        ] ?? null;
    }

    protected function normalizeUri(
        string $uri
    ): string {
        $uri = trim($uri, '/');

        if (Str::startsWith($uri, 'api/')) {
            $uri = substr($uri, 4);
        }

        return trim($uri, '/');
    }

    protected function isParameter(
        string $part
    ): bool {
        return preg_match(
            '/^\{[^}]+\}$/',
            $part
        ) === 1;
    }
}
```

### src/Foundation/Support/PermissionService.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Bitsnio\Modules\Contracts\RepositoryInterface;

class PermissionService
{
    public function __construct(
        protected MenuService $menuService,
    ) {}

    /**
     * Build all permission definitions.
     *
     * @return PermissionDefinition[]
     */
    public function getDefinitions(
        ?string $moduleName = null
    ): array {
        $definitions = [];

        foreach (
            $this->menuService->getDefinitions(
                $moduleName
            ) as $menu
        ) {
            $this->buildPermissions(
                $menu,
                $definitions
            );
        }

        return $definitions;
    }

    protected function buildPermissions(
        MenuDefinition $definition,
        array &$permissions
    ): void {
        if ($definition->isResource()) {
            foreach (
                [
                    'view' => 'View',
                    'create' => 'Create',
                    'update' => 'Update',
                    'delete' => 'Delete',
                ] as $action => $label
            ) {
                $permissions[] =
                    $this->makePermission(
                        $definition,
                        $action,
                        $label
                    );
            }
        }

        if ($definition->isAction()) {
            $permissions[] =
                $this->makePermission(
                    $definition,
                    'execute',
                    'Execute'
                );
        }

        foreach (
            $definition->children
            as $child
        ) {
            $this->buildPermissions(
                $child,
                $permissions
            );
        }
    }

    protected function makePermission(
        MenuDefinition $definition,
        string $action,
        string $label
    ): PermissionDefinition {
        $name =
            $definition->permissionKey()
            . '.'
            . $action;

        $custom =
            $definition->permissions[$action]
            ?? null;

        $description =
            is_string($custom)
            ? $custom
            : (
                is_array($custom)
                ? (
                    $custom['description']
                    ?? "{$label} {$definition->title}"
                )
                : "{$label} {$definition->title}"
            );

        return new PermissionDefinition(
            name: $name,
            description: $description,
            guard: 'api',
            module: $definition->module,
            menuPath: $definition->permissionKey(),
            method: $definition->isAction()
                ? $definition->actionMethod()
                : null,
        );
    }

    /**
     * Sync menu permissions to Spatie.
     */
    public function syncPermissions(
        ?string $moduleName = null
    ): void {
        foreach (
            $this->getDefinitions($moduleName)
            as $definition
        ) {
            Permission::updateOrCreate(
                [
                    'name' => $definition->name,
                    'guard_name' => $definition->guard,
                ],
                [
                    'description' =>
                    $definition->description,
                ]
            );
        }

        app(
            PermissionRegistrar::class
        )->forgetCachedPermissions();
    }

    /**
     * Get all permissions.
     */
    public function getAllPermissions(
        ?string $moduleName = null,
        bool $labelValueFormat = false
    ): array {
        $definitions =
            $this->getDefinitions(
                $moduleName
            );

        if (!$labelValueFormat) {
            return array_map(
                fn(PermissionDefinition $permission) =>
                $permission->toArray(),
                $definitions
            );
        }

        return array_map(
            fn(PermissionDefinition $permission) => [
                'label' =>
                $permission->description,

                'value' =>
                $permission->name,
            ],
            $definitions
        );
    }

    /**
     * Get the permission required for a route.
     */
    public function getRequiredPermission(
        string $route,
        string $method
    ): ?string {
        return app(
            PermissionResolver::class
        )->resolve(
            $route,
            $method
        );
    }

    public function getRolePermissions(
        string $roleName
    ): Collection {
        $role = Role::where(
            'name',
            $roleName
        )->firstOrFail();

        return $role
            ->permissions()
            ->orderBy('name')
            ->get();
    }

    public function updateRolePermissions(
        string $roleName,
        array $permissionNames
    ): Role {
        $role = Role::where(
            'name',
            $roleName
        )->firstOrFail();

        $role->syncPermissions(
            Permission::whereIn(
                'name',
                $permissionNames
            )
                ->where(
                    'guard_name',
                    'api'
                )
                ->get()
        );

        return $role;
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
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;

class ControllerGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
        protected GeneratorSupport $support
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
        if (!$definition->hasController()) {
            return [
                'action' => 'skipped',
            ];
        }

        $path =
            $this->files->getControllerPath(
                $module,
                $definition->controllerRelativePath()
            );

        if (!$this->files->exists($path)) {
            return [
                'action' => 'missing',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (
            !GeneratedBlock::has(
                $document,
                'methods'
            )
        ) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'methods',
            $this->methods(
                $definition
            )
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
        if ($definition->hasController()) {
            $relative =
                $definition->controllerRelativePath();

            $path =
                $this->files->getControllerPath(
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
                $definition->controllerClass,

                'path' => $relative,

                'full_path' => $path,

                'action' =>
                $exists
                    ? 'skipped'
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
        if ($definition->hasController()) {
            $path =
                $this->files->getControllerPath(
                    $module,
                    $definition->controllerRelativePath()
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'keep'
                    : 'create',

                'file' => $path,

                'type' => 'controller',
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
        $module,
        MenuDefinition $definition
    ): string {
        return $this->stubs->renderFile(
            'controller.stub',
            [
                'NAMESPACE' => $this->support->controllerNamespace(
                    $module,
                    $definition
                ),

                'CLASS' => $definition->controllerClass,

                'IMPORTS' => $this->imports(
                    $module,
                    $definition
                ),

                'TRAIT_IMPORTS' => $this->support->traitImports(
                    'controller'
                ),

                'TRAITS' => $this->support->traitUsage(
                    'controller'
                ),

                'METHODS' => $this->methods($definition),
            ]
        );
    }


    protected function imports(
        $module,
        MenuDefinition $definition
    ): string {
        $imports = [];

        $actions = $definition->routeActions();

        if (
            in_array('store', $actions, true) ||
            in_array('update', $actions, true)
        ) {
            $imports[] = $this->support->requestNamespace(
                $module,
                $definition
            ) . '\\' . $definition->requestClass();
        }

        if ($definition->model) {
            $imports[] = $this->support->modelNamespace(
                $module
            ) . '\\' . $definition->modelClass();
        }

        if ($definition->hasResource()) {
            $imports[] = $this->support->resourceNamespace(
                $module,
                $definition
            ) . '\\' . $definition->resourceClass;
        }

        return $this->support->generatorImports(
            'controller',
            $imports
        );
    }

    protected function methods(
        MenuDefinition $definition
    ): string {
        $methods = [];

        foreach (
            $definition->routeActions()
            as $action
        ) {
            $methods[] =
                match ($action) {
                    'index' =>
                    $this->indexMethod(
                        $definition
                    ),

                    'store' =>
                    $this->storeMethod(
                        $definition
                    ),

                    'show' =>
                    $this->showMethod(
                        $definition
                    ),

                    'update' =>
                    $this->updateMethod(
                        $definition
                    ),

                    'destroy' =>
                    $this->destroyMethod(
                        $definition
                    ),

                    default => '',
                };
        }

        return implode(
            PHP_EOL . PHP_EOL,
            array_filter($methods)
        );
    }

    protected function indexMethod(
        MenuDefinition $definition
    ): string {

        if (!$definition->model) {
            return <<<'PHP'
                public function index()
                {
                    return response()->json([
                        'message' => 'Index not implemented',
                    ], 501);
                }
            PHP;
        }

        $paginationEnabled = $this->support->featureEnabled(
            'controller',
            'pagination'
        );

        $paginationConfig = $this->support->featureConfig(
            'controller',
            'pagination',
            []
        );

        $perPage = is_array($paginationConfig)
            ? filter_var(
                $paginationConfig['per_page'] ?? 20,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : 20;

        $perPage = $perPage ?: 20;

        $pagination = $paginationEnabled
            ? "paginate({$perPage})"
            : 'paginate()';

        $query = $definition->modelClass() . "::{$pagination}";

        if ($definition->hasResource()) {
            return <<<PHP
                public function index()
                {
                    return {$definition->resourceClass}::collection(
                        {$query}
                    );
                }
            PHP;
        }

        return <<<PHP
                    public function index()
                    {
                        return {$query};
                    }
                PHP;
    }

    protected function storeMethod(
        MenuDefinition $definition
    ): string {
        if (!$definition->model) {
            return <<<PHP
                public function store(
                    {$definition->requestClass()} \$request
                ) {
                    return response()->json([
                        'message' => 'Store not implemented',
                    ], 501);
                }
            PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . '($item);'
            : 'return $item;';

        return <<<PHP
            public function store(
                {$definition->requestClass()} \$request
            ) {
                \$item = {$definition->modelClass()}::create(
                    \$request->validated()
                );

                {$return}
            }
        PHP;
    }

    protected function showMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
                public function show(
                    \${$parameter}
                ) {
                    return response()->json([
                        'message' => 'Show not implemented',
                    ], 501);
                }
            PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . "(\${$parameter});"
            : "return \${$parameter};";

        return <<<PHP
                    public function show(
                        {$definition->modelClass()} \${$parameter}
                    ) {
                        {$return}
                    }
                PHP;
    }

    protected function updateMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
    public function update(
        {$definition->requestClass()} \$request,
        \${$parameter}
    ) {
        return response()->json([
            'message' => 'Update not implemented',
        ], 501);
    }
PHP;
        }

        $return =
            $definition->hasResource()
            ? 'return new '
            . $definition->resourceClass
            . "(\${$parameter});"
            : "return \${$parameter};";

        return <<<PHP
    public function update(
        {$definition->requestClass()} \$request,
        {$definition->modelClass()} \${$parameter}
    ) {
        \${$parameter}->update(
            \$request->validated()
        );

        {$return}
    }
PHP;
    }

    protected function destroyMethod(
        MenuDefinition $definition
    ): string {
        $parameter =
            $definition->parameterName();

        if (!$definition->model) {
            return <<<PHP
    public function destroy(
        \${$parameter}
    ) {
        return response()->json([
            'message' => 'Destroy not implemented',
        ], 501);
    }
PHP;
        }

        return <<<PHP
    public function destroy(
        {$definition->modelClass()} \${$parameter}
    ) {
        \${$parameter}->delete();

        return response()->noContent();
    }
PHP;
    }
}

```

### src/Generators/Menu/MenuBuilder.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuBuilder
{
    protected const TYPES = [
        'resource',
        'action',
    ];

    protected const ACTION_METHODS = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    public function build(array $menu): array
    {
        $module = $menu['module'] ?? null;

        if (!is_array($module)) {
            throw new InvalidArgumentException(
                'Menu configuration must contain a module array.'
            );
        }

        $moduleName = $module['name'] ?? null;

        if (!$moduleName) {
            throw new InvalidArgumentException(
                'Menu module name is required.'
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

    protected function buildNode(
        array $config,
        string $moduleName,
        array $parentPath,
        array $inheritedMiddleware,
    ): MenuDefinition {
        $name = $config['name'] ?? null;

        if (!$name) {
            throw new InvalidArgumentException(
                'Every menu item must have a name.'
            );
        }

        $path = [
            ...$parentPath,
            $name,
        ];

        /*
        |--------------------------------------------------------------------------
        | Type
        |--------------------------------------------------------------------------
        |
        | No type means group.
        |
        */

        $type = $config['type'] ?? 'group';

        if (!in_array(
            $type,
            ['group', ...self::TYPES],
            true
        )) {
            throw new InvalidArgumentException(
                "Invalid menu type [{$type}] "
                    . "for [{$name}]. "
                    . "Allowed types: resource, action."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Middleware
        |--------------------------------------------------------------------------
        */

        $middleware = $this->middleware(
            $inheritedMiddleware,
            $config['middleware'] ?? [],
            empty($inheritedMiddleware)
                && !isset($config['middleware'])
                ? ['api']
                : []
        );

        /*
        |--------------------------------------------------------------------------
        | Children
        |--------------------------------------------------------------------------
        */

        $children = [];

        foreach (
            ($config['children'] ?? [])
            as $child
        ) {
            if (!is_array($child)) {
                continue;
            }

            $children[] = $this->buildNode(
                $child,
                $moduleName,
                $path,
                $middleware
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Action Validation
        |--------------------------------------------------------------------------
        */

        if ($type === 'action') {
            if (!empty($children)) {
                throw new InvalidArgumentException(
                    "Action [{$name}] "
                        . "cannot contain children."
                );
            }

            $method = strtoupper(
                $config['method'] ?? 'POST'
            );

            if (!in_array(
                $method,
                self::ACTION_METHODS,
                true
            )) {
                throw new InvalidArgumentException(
                    "Invalid method [{$method}] "
                        . "for action [{$name}]. "
                        . "Allowed methods: "
                        . implode(
                            ', ',
                            self::ACTION_METHODS
                        )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Component Defaults
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Schema
        |--------------------------------------------------------------------------
        */

        $schemaName = null;

        if (
            ($config['model'] ?? false) === true
        ) {
            $schemaName =
                $config['schema']['name']
                ?? Str::studly($name);
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
        $controller = $config['controller'] ?? null;

        /*
        | Explicitly disabled.
        */
        if (
            is_array($controller)
            && ($controller['enabled'] ?? true) === false
        ) {
            return null;
        }

        /*
        | Explicit class.
        */
        if (
            is_array($controller)
            && !empty($controller['class'])
        ) {
            return $controller['class'];
        }

        /*
        | Groups don't get controllers automatically.
        */
        if ($type === 'group') {
            return null;
        }

        /*
        | Resource default.
        */
        if ($type === 'resource') {
            return Str::studly(
                $config['name']
            ) . 'Controller';
        }

        /*
        | Action default.
        |
        | We only generate the controller class.
        | No controller method is assumed.
        */
        if ($type === 'action') {
            return Str::studly(
                $config['name']
            ) . 'Controller';
        }

        return null;
    }

    protected function resourceClass(
        array $config,
        string $type
    ): ?string {
        /*
        | Actions and groups don't have API resources.
        */
        if (
            $type !== 'resource'
        ) {
            return null;
        }

        $resource = $config['resource'] ?? null;

        if (
            is_array($resource)
            && ($resource['enabled'] ?? true) === false
        ) {
            return null;
        }

        if (
            is_array($resource)
            && !empty($resource['class'])
        ) {
            return $resource['class'];
        }

        return Str::studly(
            $config['name']
        ) . 'Resource';
    }

    protected function middleware(
        array $inherited,
        array $current,
        array $defaults = []
    ): array {
        return array_values(
            array_unique([
                ...$inherited,
                ...$defaults,
                ...$current,
            ])
        );
    }

    protected function flattenNode(
        MenuDefinition $definition,
        array &$flat
    ): void {
        $flat[$definition->permissionKey()] = $definition;

        foreach (
            $definition->children
            as $child
        ) {
            $this->flattenNode(
                $child,
                $flat
            );
        }
    }
}

```

### src/Generators/Menu/MenuGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Menu;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Support\Str;
use RuntimeException;

class MenuGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate menu configuration file.
     */
    public function generate(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): void {
        $path = $module->getPath()
            . '/config/menu.php';

        try {
            $content = $this->getStubContents(
                $moduleName
            );

            $this->files->writeFile(
                $path,
                $content,
                false
            );

            $component?->info(
                "Generated menu config at: {$path}"
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'MenuGenerator failed: '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get rendered menu stub.
     */
    protected function getStubContents(
        string $moduleName
    ): string {
        return $this->stubs->renderFile(
            'Menu.stub',
            [
                'MODULE_NAME' => $moduleName,
                'TITLE' => Str::headline(
                    $moduleName
                ),
            ]
        );
    }
}
```

### src/Generators/Migration/MigrationGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Migration;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
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
```

### src/Generators/Model/ModelGenerator.php

```php
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

### src/Generators/Request/RequestGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Request;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;

class RequestGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
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

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        if (!$definition->hasResource()) {
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
        return $this->stubs->renderFile(
            'request.stub',
            [
                'NAMESPACE' => $this->support->requestNamespace(
                    $module,
                    $definition
                ),

                'CLASS' => $definition->requestClass(),

                'IMPORTS' => $this->support->generatorImports(
                    'request',
                    [
                        \Illuminate\Foundation\Http\FormRequest::class,
                    ]
                ),

                'TRAIT_IMPORTS' => $this->support->traitImports(
                    'request'
                ),

                'TRAITS' => $this->support->traitUsage(
                    'request'
                ),
            ]
        );
    }

    protected function buildRules(
        SchemaDefinition $schema
    ): string {

        if (!$this->support->featureEnabled('request', 'schema_rules')) {
            return '        return [];';
        }
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

```

### src/Generators/Resource/ResourceGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Resource;

use Bitsnio\AsasFlow\Foundation\Contracts\GeneratorInterface;
use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Schema\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;

class ResourceGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
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

    public function syncFromSchema(
        $module,
        MenuDefinition $definition,
        SchemaDefinition $schema
    ): array {
        if (!$definition->hasResource()) {
            return [
                'action' => 'skipped',
            ];
        }

        $path =
            $this->files->getResourcePath(
                $module,
                $definition->resourceRelativePath()
            );

        if (!$this->files->exists($path)) {
            return [
                'action' => 'missing',
                'path' => $path,
            ];
        }

        $document =
            $this->files->read($path);

        if (!GeneratedBlock::has(
            $document,
            'generated-fields'
        )) {
            return [
                'action' => 'skipped-no-marker',
                'path' => $path,
            ];
        }

        $this->files->updateGeneratedBlock(
            $path,
            'generated-fields',
            $this->buildFields($schema)
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
        if ($definition->hasResource()) {
            $relative =
                $definition->resourceRelativePath();

            $path =
                $this->files->getResourcePath(
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
                $definition->resourceClass,

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
        if ($definition->hasResource()) {
            $path =
                $this->files->getResourcePath(
                    $module,
                    $definition->resourceRelativePath()
                );

            $results[] = [
                'action' =>
                $this->files->exists($path)
                    ? 'keep'
                    : 'create',

                'file' => $path,

                'type' => 'resource',
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
                'NAMESPACE' => $this->support->resourceNamespace(
                    $module,
                    $definition
                ),

                'CLASS' => $definition->resourceClass,

                'IMPORTS' => $this->support->generatorImports(
                    'resource',
                    [
                        \Illuminate\Http\Request::class,
                        \Illuminate\Http\Resources\Json\JsonResource::class,
                    ]
                ),

                'TRAIT_IMPORTS' => $this->support->traitImports(
                    'resource'
                ),

                'TRAITS' => $this->support->traitUsage(
                    'resource'
                ),
            ]
        );
    }

    protected function buildFields(
        SchemaDefinition $schema
    ): string {

        if (!$this->support->featureEnabled('resource', 'schema_fields')) {
            return '        return [];';
        }
        $properties = $schema->properties();

        if (!$properties) {
            return '        return [];';
        }

        $lines = [
            '        return [',
        ];

        foreach (
            array_keys($properties)
            as $field
        ) {
            $lines[] =
                "            "
                . var_export(
                    $field,
                    true
                )
                . " => \$this->{$field},";
        }

        $lines[] = '        ];';

        return implode(
            PHP_EOL,
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
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Illuminate\Support\Str;

class RouteGenerator implements GeneratorInterface
{
    public function __construct(
        protected FileHandler $files,
        protected GeneratorSupport $support,
    ) {}

    public function generate(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $routeFile =
            $this->files->getRoutesPath(
                $module
            );

        $content =
            $this->buildRouteFile(
                $module,
                $definitions
            );

        $this->files->writeFile(
            $routeFile,
            $content,
            true
        );

        return [
            [
                'path' => $routeFile,
                'action' => 'created/updated',
            ],
        ];
    }

    public function preview(
        $module,
        array $definitions,
        array $options = []
    ): array {
        $routeFile =
            $this->files->getRoutesPath(
                $module
            );

        return [
            [
                'action' =>
                $this->files->exists($routeFile)
                    ? 'update'
                    : 'create',

                'file' => $routeFile,
                'type' => 'routes',
            ],
        ];
    }


    protected function buildRouteFile(
        $module,
        array $definitions
    ): string {
        $controllers = $this->collectControllers($definitions);

        $imports = [
            \Illuminate\Support\Facades\Route::class,
        ];

        foreach ($controllers as $definition) {
            $imports[] = $this->support->controllerNamespace(
                $module,
                $definition
            ) . '\\' . $definition->controllerClass;
        }

        $content = "<?php\n\n";

        $content .= $this->support->generatorImports(
            'route',
            $imports
        );

        $content .= "\n\n";

        foreach ($definitions as $definition) {
            $content .= $this->buildNode(
                $definition,
                [],
                0
            );
        }

        return $content;
    }

    protected function buildNode(
        MenuDefinition $definition,
        array $parentMiddleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $additionalMiddleware =
            array_values(
                array_diff(
                    $definition->middleware,
                    $parentMiddleware
                )
            );

        $content = '';

        /*
         * Resource
         */
        if (
            $definition->isResource() &&
            $definition->controllerClass
        ) {
            $content .=
                $this->buildResourceRoute(
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
         * Action
         */
        if ($definition->isAction()) {
            $content .=
                $this->buildActionRoute(
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
         * Children / group
         */
        if (
            !empty($definition->children)
        ) {
            $segment =
                Str::kebab(
                    $definition->name
                );

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
                    . ")";
            }

            $content .=
                "\n"
                . $indent
                . "    ->group(function () {\n";

            foreach (
                $definition->children
                as $child
            ) {
                $content .=
                    $this->buildNode(
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

    protected function buildResourceRoute(
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $actions =
            $definition->routeActions();

        $resource =
            "Route::apiResource("
            . "'"
            . Str::kebab(
                $definition->name
            )
            . "', "
            . $definition->controllerClass
            . "::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .=
                "->only("
                . $this->phpArray($actions)
                . ")";
        }

        $resource .= ";";

        if (!$middleware) {
            return
                $indent
                . $resource
                . "\n\n";
        }

        return
            $indent
            . "Route::middleware("
            . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent
            . "    "
            . $resource
            . "\n"
            . $indent
            . "});\n\n";
    }

    protected function buildActionRoute(
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {
        $indent =
            str_repeat('    ', $level);

        $method =
            strtolower(
                $definition->actionMethod()
            );

        $path =
            $definition->actionPath();

        $controller =
            $definition->controllerClass;

        if (!$controller) {
            throw new \InvalidArgumentException(
                "Action ["
                    . $definition->permissionKey()
                    . "] requires a controller."
            );
        }

        $route =
            "Route::{$method}("
            . "'{$path}', "
            . $controller
            . "::class)";

        if ($definition->config['controller_method'] ?? null) {
            $route =
                "Route::{$method}("
                . "'{$path}', ["
                . $controller
                . "::class, '"
                . $definition->config['controller_method']
                . "'])";
        }

        $route .= ";";

        if (!$middleware) {
            return
                $indent
                . $route
                . "\n\n";
        }

        return
            $indent
            . "Route::middleware("
            . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent
            . "    "
            . $route
            . "\n"
            . $indent
            . "});\n\n";
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

    /**
     * Convert an array of values into a PHP array expression.
     */
    protected function phpArray(array $values): string
    {
        if (empty($values)) {
            return '[]';
        }

        $items = array_map(
            fn($value) => var_export($value, true),
            $values
        );

        return '[' . implode(', ', $items) . ']';
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

        /*
         * URL hierarchy is NEVER shortened.
         *
         * Only route names are shortened when necessary.
         */
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
                    Str::substr(
                        $part,
                        0,
                        1
                    ),
                $parts
            )
        );

        $hash =
            substr(
                md5(
                    implode(
                        '|',
                        $parts
                    )
                ),
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
            'generated' =>
                $generatedName,

            'original_parts' =>
                $pathParts,

            'action' =>
                $action,

            'hash' =>
                substr(
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

### src/Generators/Schema/SchemaArtifactGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Generators\Migration\MigrationGenerator;
use Bitsnio\AsasFlow\Generators\Model\ModelGenerator;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;

class SchemaArtifactGenerator
{
    public function __construct(
        protected ModelGenerator $modelGenerator,
        protected MigrationGenerator $migrationGenerator,
        protected RequestGenerator $requestGenerator,
        protected ResourceGenerator $resourceGenerator,
        protected ControllerGenerator $controllerGenerator,
    ) {}

    public function generate(
        $module,
        array $definitions
    ): array {
        $results = [];

        foreach ($definitions as $definition) {
            $this->generateNode(
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
        array &$results
    ): void {
        if ($definition->model) {
            $schemaName =
                $definition->schemaName
                ?? $definition->modelClass();

            $schemaPath =
                $module->getPath()
                . '/schema/'
                . $schemaName
                . '.json';

            if (!is_file($schemaPath)) {
                $results[] = [
                    'type' => 'schema',
                    'action' => 'missing',
                    'name' => $schemaName,
                    'path' => $schemaPath,
                ];
            } else {
                $schema =
                    SchemaDefinition::fromFile(
                        $schemaPath
                    );

                $results['models'][] =
                    $this->modelGenerator->generate(
                        $module,
                        $definition,
                        $schema
                    );

                $results['migrations'][] =
                    $this->migrationGenerator->generate(
                        $module,
                        $definition,
                        $schema
                    );

                $results['requests'][] =
                    $this->requestGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );

                $results['resources'][] =
                    $this->resourceGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );

                $results['controllers'][] =
                    $this->controllerGenerator->syncFromSchema(
                        $module,
                        $definition,
                        $schema
                    );
            }
        }

        foreach ($definition->children as $child) {
            $this->generateNode(
                $module,
                $child,
                $results
            );
        }
    }
}
```

### src/Generators/Schema/SchemaDefinition.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Schema;

use InvalidArgumentException;

class SchemaDefinition
{
    public function __construct(
        protected array $schema
    ) {}

    public static function fromFile(
        string $path
    ): self {
        if (!is_file($path)) {
            throw new InvalidArgumentException(
                "Schema file not found: {$path}"
            );
        }

        $data = json_decode(
            file_get_contents($path),
            true
        );

        if (!is_array($data)) {
            throw new InvalidArgumentException(
                "Invalid JSON schema: {$path}"
            );
        }

        return new self($data);
    }

    public function title(): string
    {
        return $this->schema['title']
            ?? '';
    }

    public function properties(): array
    {
        return is_array(
            $this->schema['properties'] ?? null
        )
            ? $this->schema['properties']
            : [];
    }

    public function required(): array
    {
        return is_array(
            $this->schema['required'] ?? null
        )
            ? $this->schema['required']
            : [];
    }

    public function property(
        string $name
    ): array {
        return is_array(
            $this->properties()[$name] ?? null
        )
            ? $this->properties()[$name]
            : [];
    }

    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->properties() as $name => $property) {
            $rules[$name] =
                $this->rulesFor(
                    $name,
                    $property
                );
        }

        return $rules;
    }

    public function casts(): array
    {
        $casts = [];

        foreach ($this->properties() as $name => $property) {
            $type = $property['type'] ?? 'string';

            $cast = match ($type) {
                'integer' => 'integer',
                'number' => 'decimal:4',
                'boolean' => 'boolean',
                'array',
                'object' => 'array',
                default => null,
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }
        }

        return $casts;
    }

    public function migrationColumns(): array
    {
        $columns = [];

        foreach ($this->properties() as $name => $property) {
            $columns[$name] =
                $this->migrationColumn(
                    $name,
                    $property
                );
        }

        return $columns;
    }

    protected function rulesFor(
        string $name,
        array $property
    ): array {
        $rules = [];

        if (
            in_array(
                $name,
                $this->required(),
                true
            )
        ) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $type = $property['type'] ?? 'string';

        switch ($type) {
            case 'integer':
                $rules[] = 'integer';
                break;

            case 'number':
                $rules[] = 'numeric';
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'array':
                $rules[] = 'array';
                break;

            case 'object':
                $rules[] = 'array';
                break;

            case 'string':
            default:
                $rules[] = 'string';

                if (
                    isset($property['minLength'])
                ) {
                    $rules[] =
                        'min:'
                        . $property['minLength'];
                }

                if (
                    isset($property['maxLength'])
                ) {
                    $rules[] =
                        'max:'
                        . $property['maxLength'];
                }

                $format =
                    $property['format'] ?? null;

                if ($format === 'email') {
                    $rules[] = 'email';
                }

                if ($format === 'date') {
                    $rules[] = 'date';
                }

                if ($format === 'date-time') {
                    $rules[] = 'date';
                }

                if ($format === 'uri') {
                    $rules[] = 'url';
                }

                break;
        }

        if (
            isset($property['minimum'])
        ) {
            $rules[] =
                'min:'
                . $property['minimum'];
        }

        if (
            isset($property['maximum'])
        ) {
            $rules[] =
                'max:'
                . $property['maximum'];
        }

        if (
            isset($property['enum']) &&
            is_array($property['enum'])
        ) {
            $rules[] =
                'in:'
                . implode(
                    ',',
                    array_map(
                        'strval',
                        $property['enum']
                    )
                );
        }

        return $rules;
    }

    protected function migrationColumn(
        string $name,
        array $property
    ): array {
        $type = $property['type'] ?? 'string';

        return [
            'name' => $name,
            'type' => $type,
            'nullable' =>
            !in_array(
                $name,
                $this->required(),
                true
            ),
            'default' =>
            $property['default']
                ?? null,
            'enum' =>
            $property['enum']
                ?? null,
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
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
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
```

### src/Generators/Stubs/Controller.stub

```
<?php

namespace {{ NAMESPACE }};

use Illuminate\Routing\Controller;
{{ IMPORTS }}
{{ TRAIT_IMPORTS }}

class {{ CLASS }} extends Controller
{
{{ TRAITS }}
{{ METHODS }}
}
```

### src/Generators/Stubs/Menu.stub

```
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module
    |--------------------------------------------------------------------------
    */

    'module' => [
        'name' => '{{ MODULE_NAME }}',
        'title' => '{{ TITLE }}',

        'middleware' => [
            'api',
            'auth:api',
            'asasflow.permission',
        ],

        'children' => [

            /*
            |--------------------------------------------------------------------------
            | GROUP
            |--------------------------------------------------------------------------
            |
            | "type" is not required here.
            |
            | Any menu item without a type automatically becomes a group.
            |
            */

            [
                'name' => 'organization',
                'title' => 'Organization',

                'children' => [

                    /*
                    |--------------------------------------------------------------------------
                    | RESOURCE
                    |--------------------------------------------------------------------------
                    |
                    | Generates apiResource CRUD routes.
                    |
                    */

                    [
                        'name' => 'companies',
                        'title' => 'Companies',
                        'type' => 'resource',

                        'model' => true,

                        /*
                        | Optional. Full CRUD is the default.
                        */

                        // 'routes' => [
                        //     'index',
                        //     'show',
                        //     'store',
                        //     'update',
                        //     'destroy',
                        // ],

                        /*
                        | Optional permission descriptions.
                        */

                        'permissions' => [
                            'view' => 'View companies',
                            'create' => 'Create companies',
                            'update' => 'Update companies',
                            'delete' => 'Delete companies',
                        ],

                        'children' => [

                            /*
                            |--------------------------------------------------------------------------
                            | ACTION
                            |--------------------------------------------------------------------------
                            |
                            | Custom endpoint.
                            |
                            | No controller_method is required.
                            | The generated controller can be implemented later.
                            |
                            */

                            [
                                'name' => 'approve',
                                'title' => 'Approve Company',
                                'type' => 'action',

                                'method' => 'POST',
                                'path' => '{company}/approve',

                                'controller' => [
                                    'class' => 'CompanyActionController',
                                ],

                                'permissions' => [
                                    'execute' => 'Approve companies',
                                ],
                            ],

                        ],
                    ],

                    /*
                    |--------------------------------------------------------------------------
                    | ANOTHER RESOURCE
                    |--------------------------------------------------------------------------
                    */

                    [
                        'name' => 'sites',
                        'title' => 'Sites',
                        'type' => 'resource',
                        'model' => true,
                    ],
                ],
            ],
        ],
    ],
];
```

### src/Generators/Stubs/Model.stub

```
<?php

namespace {{ namespace }};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {{ class }} extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // @asasflow:generated-fillable:start
        // @asasflow:generated-fillable:end
    ];

    protected $casts = [
        // @asasflow:generated-casts:start
        // @asasflow:generated-casts:end
    ];
}
```

### src/Generators/Stubs/Request.stub

```
<?php

namespace {{ NAMESPACE }};

use Illuminate\Foundation\Http\FormRequest;

class {{ CLASS }} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // @asasflow:rules:start
        return [];
        // @asasflow:rules:end
    }
}
```

### src/Generators/Stubs/Resource.stub

```
<?php

namespace {{ NAMESPACE }};

{{ IMPORTS }}
{{ TRAIT_IMPORTS }}

class {{ CLASS }} extends JsonResource
{
{{ TRAITS }}
    public function toArray(Request $request): array
    {
        // @asasflow:generated-fields:start
        return parent::toArray($request);
        // @asasflow:generated-fields:end
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

