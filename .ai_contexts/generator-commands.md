# Laravel Project Context

**Task:** Generator commands

## Background and Purpose

building a package in laravel which provides modular approach of code generated through laravel-module package. this package generate basic strucure along with menu.php file which contain full information based on what routes and controller are generated. i have designe the package in a way that each function is created as feature.
---

## Directory Structure

```
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
src/Generators/Module
src/Generators/Request
src/Generators/Resource
src/Generators/Route
src/Generators/Schema
src/Generators/Stubs
src/Generators/Stubs/Module
src/Generators/Tests
```

---

## Project Files

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

### src/Foundation/Contracts/ModuleRepositoryInterface.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Contracts;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;

interface ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition;

    /** @return array<string, ModuleDefinition> */
    public function all(): array;
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
        return trim($module->namespace, '\\');
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
            "asasflow.generators.{$generator}.imports",
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
            "asasflow.generators.{$generator}.traits",
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
            "asasflow.generators.{$generator}.features.{$feature}.enabled",
            false
        );
    }

    public function featureConfig(
        string $generator,
        string $feature,
        mixed $default = []
    ): mixed {
        return config(
            "asasflow.generators.{$generator}.features.{$feature}",
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
        return Str::camel(
            $this->modelClass()
        );
    }

    /**
     * Custom actions belonging to this resource.
     *
     * @return array<string, array>
     */
    public function customActions(): array
    {
        $actions = $this->config['actions'] ?? [];

        if (!is_array($actions)) {
            return [];
        }

        return $actions;
    }

    /**
     * HTTP method for a custom action.
     */
    public function customActionMethod(string $name): string
    {
        $action = $this->customActions()[$name] ?? [];

        return strtoupper($action['method'] ?? 'POST');
    }

    /**
     * Human-readable permission label for a custom action.
     */
    public function customActionPermissionLabel(string $name): string
    {
        $action = $this->customActions()[$name] ?? [];

        return $action['permissionLabel']
            ?? Str::headline($name) . ' ' . $this->title;
    }

    /**
     * Preserve the existing action permission naming convention.
     *
     * Example:
     * organization.companies.approve.execute
     */
    public function customActionPermissionKey(string $name): string
    {
        return $this->permissionKey()
            . '.'
            . Str::kebab($name)
            . '.execute';
    }

    /**
     * Full custom action route path.
     */
    public function customActionRoutePath(string $name): string
    {
        return $this->routePath()
            . '/'
            . Str::kebab($name);
    }
}

```

### src/Foundation/Support/MenuService.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Generators\Menu\MenuBuilder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class MenuService
{
    public function __construct(
        protected ModuleRepositoryInterface $repository,
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

### src/Foundation/Support/ModuleDefinition.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

final readonly class ModuleDefinition
{
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
    ) {}

    /**
     * Backward-compatible accessors for existing generators.
     */

    public function appPath(string $path = ''): string
    {
        $base = $this->path . '/app';

        return $path === ''
            ? $base
            : $base . '/' . ltrim($path, '/');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function configPath(): string
    {
        return $this->path . '/config';
    }

    public function routesPath(): string
    {
        return $this->path . '/routes';
    }

    public function migrationsPath(): string
    {
        return $this->path . '/database/migrations';
    }

    /**
     * Matches the existing app/Providers directory convention.
     */
    public function providerClass(): string
    {
        return $this->namespace . '\\Providers\\' . $this->name . 'ServiceProvider';
    }
}

```

### src/Foundation/Support/ModuleDependencyResolver.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Composer\Semver\Semver;
use LogicException;

final class ModuleDependencyResolver
{
    /**
     * Validate enabled modules and return them in dependency order.
     *
     * @param array<string, array<string, mixed>> $modules
     * @return array<string, array<string, mixed>>
     */
    public function resolve(array $modules): array
    {
        $enabled = [];

        foreach ($modules as $name => $module) {
            if (($module['enabled'] ?? true) === true) {
                $enabled[$name] = $module;
            }
        }

        $ordered = [];
        $visiting = [];
        $visited = [];

        $visit = function (string $name) use (
            &$visit,
            &$ordered,
            &$visiting,
            &$visited,
            $enabled,
            $modules
        ): void {
            if (isset($visited[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                $cycle = implode(' -> ', [...array_keys($visiting), $name]);

                throw new LogicException(
                    "Circular module dependency detected: {$cycle}."
                );
            }

            $module = $enabled[$name] ?? null;

            if ($module === null) {
                throw new LogicException(
                    "Module [{$name}] is not enabled or installed."
                );
            }

            $visiting[$name] = true;

            foreach (($module['requires'] ?? []) as $alias => $constraint) {
                $dependency = $this->findByAlias($modules, (string) $alias);

                if ($dependency === null) {
                    throw new LogicException(
                        "Module [{$name}] requires missing module "
                            . "[{$alias}]. Install that module before enabling "
                            . "[{$name}]."
                    );
                }

                $dependencyName = $dependency['name'];

                if (! isset($enabled[$dependencyName])) {
                    throw new LogicException(
                        "Module [{$name}] requires [{$alias}], but that "
                            . "module is disabled."
                    );
                }

                $this->assertVersionCompatible(
                    $name,
                    (string) $alias,
                    $dependency,
                    $constraint
                );

                $visit($dependencyName);
            }

            unset($visiting[$name]);

            $visited[$name] = true;
            $ordered[$name] = $module;
        };

        foreach (array_keys($enabled) as $name) {
            $this->assertAsasFlowCompatible($name, $enabled[$name]);
            $visit($name);
        }

        return $ordered;
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     * @return array<string, mixed>|null
     */
    private function findByAlias(array $modules, string $alias): ?array
    {
        foreach ($modules as $module) {
            if (($module['alias'] ?? null) === $alias) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $dependency
     */
    private function assertVersionCompatible(
        string $moduleName,
        string $alias,
        array $dependency,
        mixed $constraint
    ): void {
        if (! is_string($constraint) || trim($constraint) === '') {
            throw new LogicException(
                "Invalid version constraint for dependency [{$alias}] "
                    . "declared by module [{$moduleName}]."
            );
        }

        $version = $dependency['version'] ?? null;

        if (! is_string($version) || trim($version) === '') {
            throw new LogicException(
                "Cannot validate [{$moduleName}]'s dependency on [{$alias}]: "
                    . "the dependency has no resolvable version. Set a version "
                    . "in its module.json or composer.json."
            );
        }

        if (! Semver::satisfies($version, $constraint)) {
            throw new LogicException(
                "Module [{$moduleName}] requires [{$alias}] {$constraint}, "
                    . "but version [{$version}] is installed."
            );
        }
    }

    private function assertAsasFlowCompatible(
        string $moduleName,
        array $module
    ): void {
        $constraint = $module['compatibility']['asasflow'] ?? null;

        // Compatibility is optional for older manifests.
        if ($constraint === null) {
            return;
        }

        if (! is_string($constraint) || trim($constraint) === '') {
            throw new LogicException(
                "Invalid AsasFlow compatibility constraint in [{$moduleName}]."
            );
        }

        $version = config('asasflow.version');

        if (! is_string($version) || trim($version) === '') {
            throw new LogicException(
                'Cannot validate module compatibility because '
                    . '[asasflow.version] is not configured.'
            );
        }

        if (! Semver::satisfies(ltrim($version, 'v'), $constraint)) {
            throw new LogicException(
                "Module [{$moduleName}] requires AsasFlow [{$constraint}], "
                    . "but the installed version is [{$version}]."
            );
        }
    }
}

```

### src/Foundation/Support/ModuleDiscovery.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final class ModuleDiscovery
{
    public function __construct(
        private readonly ModuleRepositoryInterface $modules,
    ) {}

    /**
     * @return array<string, array{
     *     name: string,
     *     alias: string,
     *     namespace: string,
     *     path: string,
     *     provider: class-string,
     *     version: string|null,
     *     enabled: bool,
     *     requires: array<string, string>,
     *     compatibility: array<string, mixed>
     * }>
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function all(): array
    {
        if (! config('asasflow.modules.enabled', true)) {
            return [];
        }

        $discovered = [];
        $aliases = [];

        foreach ($this->modules->all() as $module) {
            $manifestPath = $module->path
                . DIRECTORY_SEPARATOR
                . 'module.json';

            if (! File::isFile($manifestPath)) {
                throw new InvalidArgumentException(
                    "Module manifest not found: {$manifestPath}"
                );
            }

            $manifest = json_decode(
                File::get($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($manifest)) {
                throw new InvalidArgumentException(
                    "Invalid module manifest: {$manifestPath}"
                );
            }

            $name = $manifest['name'] ?? null;
            $alias = $manifest['alias'] ?? null;
            $namespace = $manifest['namespace'] ?? null;
            $provider = $manifest['provider'] ?? null;
            $enabled = $manifest['enabled'] ?? true;
            $requires = $manifest['requires'] ?? [];
            $compatibility = $manifest['compatibility'] ?? [];
            $version = $this->resolveVersion($manifest, $module->path);

            if (
                ! is_string($name)
                || $name !== $module->name
                || ! is_string($alias)
                || ! preg_match('/^[a-z][a-z0-9_-]*$/', $alias)
                || ! is_string($namespace)
                || trim($namespace, '\\') === ''
                || ! is_string($provider)
                || trim($provider) === ''
                || ! is_bool($enabled)
                || ! is_array($requires)
                || ! is_array($compatibility)
            ) {
                throw new InvalidArgumentException(
                    "Module manifest has missing or invalid fields: "
                    . $manifestPath
                );
            }

            if (isset($aliases[$alias])) {
                throw new InvalidArgumentException(
                    "Duplicate module alias [{$alias}] found in "
                    . "[{$aliases[$alias]}] and [{$name}]."
                );
            }

            foreach ($requires as $dependencyAlias => $constraint) {
                if (
                    ! is_string($dependencyAlias)
                    || ! preg_match(
                        '/^[a-z][a-z0-9_-]*$/',
                        $dependencyAlias
                    )
                    || ! is_string($constraint)
                    || trim($constraint) === ''
                ) {
                    throw new InvalidArgumentException(
                        "Invalid dependency declaration in [{$name}]."
                    );
                }
            }

            $aliases[$alias] = $name;

            $discovered[$name] = [
                'name' => $name,
                'alias' => $alias,
                'namespace' => trim($namespace, '\\'),
                'path' => $module->path,
                'provider' => $provider,
                'version' => $version,
                'enabled' => $enabled,
                'requires' => $requires,
                'compatibility' => $compatibility,
            ];
        }

        return $discovered;
    }

    /**
     * Local modules should declare their version in module.json.
     * composer.json is a fallback for Composer-managed module packages.
     *
     * @param array<string, mixed> $manifest
     */
    private function resolveVersion(array $manifest, string $path): ?string
    {
        $version = $manifest['version'] ?? null;

        if (is_string($version) && trim($version) !== '') {
            return ltrim(trim($version), 'v');
        }

        $composerPath = $path . DIRECTORY_SEPARATOR . 'composer.json';

        if (File::isFile($composerPath)) {
            $composer = json_decode(
                File::get($composerPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (
                is_array($composer)
                && is_string($composer['version'] ?? null)
                && trim($composer['version']) !== ''
            ) {
                return ltrim(trim($composer['version']), 'v');
            }
        }

        return null;
    }
}

```

### src/Foundation/Support/ModuleRegistrar.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class ModuleRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleDiscovery $discovery,
        private readonly ModuleDependencyResolver $dependencies,
    ) {}

    public function register(): void
    {
        $modules = $this->discovery->all();
 
        // Validate requirements and calculate provider registration order.
        $ordered = $this->dependencies->resolve($modules);

        // Validate all providers before registering any of them.
        foreach ($ordered as $module) {
            $provider = $module['provider'];

            if (! class_exists($provider)) {
                throw new LogicException(
                    "Service provider [{$provider}] for module "
                        . "[{$module['name']}] does not exist."
                );
            }

            if (! is_subclass_of($provider, ServiceProvider::class)) {
                throw new LogicException(
                    "Service provider [{$provider}] must extend "
                        . ServiceProvider::class . '.'
                );
            }
        }

        $loadedProviders = $this->app->getLoadedProviders();

        foreach ($ordered as $module) {
            $provider = $module['provider'];

            // Avoid registering the same provider twice.
            if (isset($loadedProviders[$provider])) {
                continue;
            }

            $this->app->register($provider);
            $loadedProviders[$provider] = true;
        }
    }
}

```

### src/Foundation/Support/ModuleReleaseInstaller.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Composer\Semver\Semver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

final class ModuleReleaseInstaller
{
    /**
     * Install a configured module.
     *
     * Development fallback is only attempted when explicitly allowed
     * and the release is unavailable, not when validation fails.
     */
    public function install(
        string $alias,
        array $definition,
        bool $allowDevFallback = false,
        bool $dryRun = false,
    ): array {
        $alias = strtolower(trim($alias));

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $alias)) {
            throw new RuntimeException("Invalid module alias [{$alias}].");
        }

        $release = $definition['release'] ?? null;
        $releaseUrl = is_array($release)
            ? ($release['url'] ?? null)
            : null;

        $devUrl = $definition['dev_url'] ?? null;

        if (! $this->validUrl($releaseUrl) && ! $this->validUrl($devUrl)) {
            throw new RuntimeException(
                "Module [{$alias}] must define release.url or dev_url."
            );
        }

        if ($this->validUrl($releaseUrl)) {
            try {
                return $this->downloadAndInstall(
                    alias: $alias,
                    url: $releaseUrl,
                    source: 'release',
                    versionConstraint: $release['version'] ?? null,
                    dryRun: $dryRun,
                );
            } catch (ReleaseUnavailableException $e) {
                if (! $allowDevFallback || ! $this->validUrl($devUrl)) {
                    throw $e;
                }

                // A failed checksum, invalid ZIP, invalid manifest or
                // incompatible version must never trigger fallback.
            }
        }

        if (!$this->validUrl($devUrl)) {
            throw new RuntimeException(
                "No usable release or development URL is configured for [{$alias}]."
            );
        }

        if ($this->validUrl($releaseUrl) && !$allowDevFallback) {
            throw new RuntimeException(
                "The release for [{$alias}] is unavailable. "
                . 'Use --allow-dev-fallback to permit development installation.'
            );
        }

        return $this->downloadAndInstall(
            alias: $alias,
            url: $devUrl,
            source: 'development',
            versionConstraint: null,
            dryRun: $dryRun,
        );
    }

    private function downloadAndInstall(
        string $alias,
        string $url,
        string $source,
        ?string $versionConstraint,
        bool $dryRun,
    ): array {
        $this->assertHttpsUrl($url);

        $workDir = storage_path(
            'framework/asasflow-module-install/' . bin2hex(random_bytes(12))
        );

        File::ensureDirectoryExists($workDir);

        try {
            $archivePath = $workDir . DIRECTORY_SEPARATOR . 'module.zip';

            try {
                $response = Http::timeout(120)
                    ->withOptions(['sink' => $archivePath])
                    ->get($url);
            } catch (ConnectionException $e) {
                throw new ReleaseUnavailableException(
                    "Could not connect to the {$source} URL for [{$alias}].",
                    previous: $e,
                );
            }

            if (in_array($response->status(), [404, 410], true)) {
                throw new ReleaseUnavailableException(
                    "The {$source} archive for [{$alias}] is unavailable "
                    . "(HTTP {$response->status()})."
                );
            }

            if (!$response->successful()) {
                throw new RuntimeException(
                    "The {$source} download for [{$alias}] failed "
                    . "(HTTP {$response->status()})."
                );
            }

            if (
                !File::exists($archivePath)
                || File::size($archivePath) === 0
            ) {
                throw new RuntimeException(
                    "The downloaded archive for [{$alias}] is empty."
                );
            }

            $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extracted';
            File::ensureDirectoryExists($extractDir);

            $moduleRoot = $this->extractModule($archivePath, $extractDir);
            $manifest = $this->readManifest($moduleRoot);

            $manifestAlias = strtolower((string) ($manifest['alias'] ?? ''));

            if ($manifestAlias !== $alias) {
                throw new RuntimeException(
                    "Module alias mismatch: expected [{$alias}], "
                    . "received [{$manifestAlias}]."
                );
            }

            $name = $manifest['name'] ?? null;

            if (
                !is_string($name)
                || $name === ''
                || basename($name) !== $name
                || in_array($name, ['.', '..'], true)
            ) {
                throw new RuntimeException(
                    "The module [{$alias}] has an invalid name in module.json."
                );
            }

            $version = $manifest['version'] ?? null;

            if ($source === 'release') {
                if (
                    !is_string($version)
                    || $version === ''
                    || !is_string($versionConstraint)
                    || $versionConstraint === ''
                ) {
                    throw new RuntimeException(
                        "Release module [{$alias}] must define a version "
                        . 'and a release.version constraint.'
                    );
                }

                if (!Semver::satisfies(ltrim($version, 'v'), $versionConstraint)) {
                    throw new RuntimeException(
                        "Module [{$alias}] version [{$version}] does not "
                        . "satisfy [{$versionConstraint}]."
                    );
                }
            }

            $modulesPath = rtrim(
                config('asasflow.modules.path', base_path('Modules')),
                DIRECTORY_SEPARATOR,
            );

            $destination = $modulesPath . DIRECTORY_SEPARATOR . $name;

            if (File::exists($destination)) {
                throw new RuntimeException(
                    "Module [{$name}] already exists at [{$destination}]. "
                    . 'Automatic overwrite is disabled.'
                );
            }

            if ($dryRun) {
                return [
                    'alias' => $alias,
                    'name' => $name,
                    'version' => $version,
                    'source' => $source,
                    'path' => $destination,
                    'installed' => false,
                    'dry_run' => true,
                ];
            }

            File::ensureDirectoryExists($modulesPath);

            // Stage under Modules/ so promotion stays on the same filesystem.
            $stagingPath = $modulesPath
                . DIRECTORY_SEPARATOR
                . '.asasflow-install-' . bin2hex(random_bytes(8));

            if (!File::copyDirectory($moduleRoot, $stagingPath)) {
                throw new RuntimeException(
                    "Could not stage module [{$alias}] for installation."
                );
            }

            try {
                // Check again immediately before promotion.
                if (File::exists($destination)) {
                    throw new RuntimeException(
                        "Module destination [{$destination}] already exists."
                    );
                }

                if (!@rename($stagingPath, $destination)) {
                    throw new RuntimeException(
                        "Could not promote module into [{$destination}]."
                    );
                }
            } catch (\Throwable $e) {
                File::deleteDirectory($stagingPath);
                throw $e;
            }

            return [
                'alias' => $alias,
                'name' => $name,
                'version' => $version,
                'source' => $source,
                'path' => $destination,
                'installed' => true,
                'dry_run' => false,
            ];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function readManifest(string $moduleRoot): array
    {
        $path = $moduleRoot . DIRECTORY_SEPARATOR . 'module.json';

        if (!File::exists($path)) {
            throw new RuntimeException('Module archive has no module.json.');
        }

        try {
            $manifest = json_decode(
                File::get($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'Module archive contains invalid module.json.',
                previous: $e,
            );
        }

        if (!is_array($manifest)) {
            throw new RuntimeException('Invalid module.json structure.');
        }

        return $manifest;
    }

    private function extractModule(string $archivePath, string $extractDir): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'PHP ext-zip is required to install module archives.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Downloaded file is not a valid ZIP archive.');
        }

        try {
            $root = $extractDir . DIRECTORY_SEPARATOR . 'archive';
            File::ensureDirectoryExists($root);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if (!is_string($entry) || $entry === '') {
                    throw new RuntimeException('Invalid ZIP entry.');
                }

                $normalized = str_replace('\\', '/', $entry);
                $segments = explode('/', $normalized);

                if (
                    str_starts_with($normalized, '/')
                    || preg_match('/^[a-zA-Z]:/', $normalized)
                    || in_array('..', $segments, true)
                ) {
                    throw new RuntimeException(
                        "Unsafe path in module archive: [{$entry}]."
                    );
                }

                // Reject Unix symbolic links where ZIP metadata exposes them.
                $opsys = 0;
                $attributes = 0;

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $unixMode = ($attributes >> 16) & 0170000;

                    if (
                        $opsys === ZipArchive::OPSYS_UNIX
                        && $unixMode === 0120000
                    ) {
                        throw new RuntimeException(
                            'Symbolic links are not allowed in module archives.'
                        );
                    }
                }

                $target = $root . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

                if (str_ends_with($normalized, '/')) {
                    File::ensureDirectoryExists($target);
                    continue;
                }

                File::ensureDirectoryExists(dirname($target));

                $input = $zip->getStream($entry);

                if ($input === false) {
                    throw new RuntimeException(
                        "Could not read archive entry [{$entry}]."
                    );
                }

                $output = fopen($target, 'wb');

                if ($output === false) {
                    fclose($input);

                    throw new RuntimeException(
                        "Could not write archive entry [{$entry}]."
                    );
                }

                try {
                    if (stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException(
                            "Could not extract archive entry [{$entry}]."
                        );
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }

            if (File::exists($root . DIRECTORY_SEPARATOR . 'module.json')) {
                return $root;
            }

            $matches = [];

            foreach (File::directories($root) as $directory) {
                if (File::exists($directory . DIRECTORY_SEPARATOR . 'module.json')) {
                    $matches[] = $directory;
                }
            }

            if (count($matches) !== 1) {
                throw new RuntimeException(
                    'Archive must contain module.json at its root or in '
                    . 'exactly one top-level directory.'
                );
            }

            return $matches[0];
        } finally {
            $zip->close();
        }
    }

    private function validUrl(mixed $url): bool
    {
        return is_string($url) && trim($url) !== '';
    }

    private function assertHttpsUrl(string $url): void
    {
        $parts = parse_url($url);

        if (
            !is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
        ) {
            throw new RuntimeException(
                'Module archive URLs must use HTTPS.'
            );
        }
    }
}

/**
 * Only this exception permits development fallback.
 */
final class ReleaseUnavailableException extends RuntimeException
{
}

```

### src/Foundation/Support/ModuleRepository.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ModuleRepository implements ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition
    {
        $name = $this->normalizeName($name);
        $path = $this->modulesPath() . DIRECTORY_SEPARATOR . $name;

        if (! File::isDirectory($path)) {
            return null;
        }

        return new ModuleDefinition(
            name: $name,
            path: $path,
            namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
        );
    }

    public function all(): array
    {
        $path = $this->modulesPath();
        if (! File::isDirectory($path)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($path) as $directory) {
            $name = basename($directory);
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $modules[$name] = new ModuleDefinition(
                name: $name,
                path: $directory,
                namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
            );
        }

        return $modules;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (
            $name === ''
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)
        ) {
            throw new InvalidArgumentException("Invalid module name [{$name}].");
        }

        $name = Str::studly($name);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid normalized module name [{$name}].");
        }

        return $name;
    }

    private function modulesPath(): string
    {
        return rtrim(
            (string) config('asasflow.modules.path', base_path('Modules')),
            DIRECTORY_SEPARATOR
        );
    }

    private function modulesNamespace(): string
    {
        return (string) config('asasflow.modules.namespace', 'Modules');
    }
}

```

### src/Foundation/Support/ModuleScaffolder.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ModuleScaffolder
{
    public function __construct(
        private readonly StubRenderer $stubs,
        private readonly FileHandler $files,
    ) {}

    /**
     * Create all enabled scaffold directories.
     */
    public function createDirectories(ModuleDefinition $module): void
    {
        $this->files->ensureDirectoryExists($module->path);

        foreach ($this->scaffold() as $name => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            $this->assertDirectoryDefinition($name, $definition);

            $relativePath = $this->resolvePath(
                (string) $definition['path'],
                $this->replacements($module),
            );

            $this->files->ensureDirectoryExists(
                $this->modulePath($module, $relativePath),
            );
        }
    }

    /**
     * Generate enabled starter files.
     *
     * @return array<int, array{path: string, action: string}>
     */
    public function createFiles(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        $this->createDirectories($module);

        $baseReplacements = $this->replacements($module);
        $results = [];

        foreach ($this->scaffold() as $name => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            $this->assertDirectoryDefinition($name, $definition);

            $stubDefinition = $definition['stub'] ?? [];

            if (! is_array($stubDefinition)) {
                throw new InvalidArgumentException(
                    "Invalid stub configuration for scaffold entry [{$name}].",
                );
            }

            if (! ($stubDefinition['enabled'] ?? false)) {
                continue;
            }

            $stubName = trim((string) ($stubDefinition['stub'] ?? ''));
            $configuredPath = trim((string) ($stubDefinition['path'] ?? ''));

            if ($stubName === '' || $configuredPath === '') {
                throw new InvalidArgumentException(
                    "Enabled stub for scaffold entry [{$name}] "
                    . 'must define both path and stub.',
                );
            }

            $this->assertSafeStubName($stubName);

            $relativePath = $this->resolvePath(
                $configuredPath,
                $baseReplacements,
            );

            $path = $this->modulePath($module, $relativePath);

            if ($this->files->exists($path) && ! $force) {
                $results[] = [
                    'path' => $path,
                    'action' => 'skipped-existing',
                ];

                continue;
            }

            $customReplacements = $stubDefinition['replacements'] ?? [];

            if (! is_array($customReplacements)) {
                throw new InvalidArgumentException(
                    "Replacements for scaffold entry [{$name}] must be an array.",
                );
            }

            $replacements = array_replace(
                $baseReplacements,
                $customReplacements,
            );

            $content = $this->stubs->renderFile(
                $stubName,
                $replacements,
            );

            $this->files->writeFile(
                $path,
                $content,
                force: $force,
            );

            $results[] = [
                'path' => $path,
                'action' => 'generated',
            ];
        }

        return $results;
    }

    /**
     * Return the configured scaffold entries.
     */
    private function scaffold(): array
    {
        $scaffold = config('asasflow.modules.scaffold', []);

        if (! is_array($scaffold)) {
            throw new RuntimeException(
                'The asasflow.modules.scaffold configuration must be an array.',
            );
        }

        return $scaffold;
    }

    /**
     * Validate the common directory-level structure.
     */
    private function assertDirectoryDefinition(
        string $name,
        array $definition,
    ): void {
        if (($definition['type'] ?? null) !== 'directory') {
            throw new InvalidArgumentException(
                "Scaffold entry [{$name}] must have type [directory].",
            );
        }

        if (
            ! isset($definition['path'])
            || ! is_string($definition['path'])
            || trim($definition['path']) === ''
        ) {
            throw new InvalidArgumentException(
                "Scaffold entry [{$name}] must define a directory path.",
            );
        }
    }

    /**
     * Resolve placeholders in a configured relative path.
     */
    private function resolvePath(
        string $path,
        array $replacements,
    ): string {
        foreach ($replacements as $key => $value) {
            $path = str_replace(
                [
                    '{{ ' . $key . ' }}',
                    '{{' . $key . '}}',
                ],
                (string) $value,
                $path,
            );
        }

        $path = str_replace('\\', '/', trim($path));

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path)
        ) {
            throw new InvalidArgumentException(
                "Scaffold path must be relative: [{$path}].",
            );
        }

        $segments = explode('/', $path);

        if (in_array('..', $segments, true)) {
            throw new InvalidArgumentException(
                "Scaffold path cannot traverse outside the module: [{$path}].",
            );
        }

        $segments = array_values(array_filter(
            $segments,
            static fn (string $segment): bool =>
                $segment !== '' && $segment !== '.',
        ));

        return implode('/', $segments);
    }

    /**
     * Resolve a validated relative path under the module root.
     */
    private function modulePath(
        ModuleDefinition $module,
        string $relativePath,
    ): string {
        if ($relativePath === '') {
            return rtrim($module->path, '/\\');
        }

        return rtrim($module->path, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath,
            );
    }

    /**
     * Prevent configured stub paths from escaping the stubs directory.
     */
    private function assertSafeStubName(string $stubName): void
    {
        $stubName = str_replace('\\', '/', $stubName);

        if (
            str_starts_with($stubName, '/')
            || preg_match('/^[A-Za-z]:/', $stubName)
            || in_array('..', explode('/', $stubName), true)
        ) {
            throw new InvalidArgumentException(
                "Stub path must be relative to the stubs directory: [{$stubName}].",
            );
        }
    }

    /**
     * Standard replacements available to every module stub.
     */
    private function replacements(ModuleDefinition $module): array
    {
        $name = $module->name;
        $lower = Str::kebab($name);
        $title = Str::headline($name);
        $provider = $module->providerClass();

        return [
            'MODULE' => $name,
            'MODULE_LOWER' => $lower,
            'MODULE_TITLE' => $title,
            'MODULE_NAMESPACE' => $module->namespace,

            'MODULE_COMPOSER_NAMESPACE' => str_replace(
                '\\',
                '\\\\',
                $module->namespace . '\\',
            ),

            'MODULE_PROVIDER' => $provider,

            'AUTHOR_NAME' => (string) config(
                'asasflow.modules.composer.author',
                'Bitsnio',
            ),

            'AUTHOR_EMAIL' => (string) config(
                'asasflow.modules.composer.email',
                '',
            ),

            'MODULE_NAMESPACE_JSON' => str_replace(
                '\\',
                '\\\\',
                $module->namespace,
            ),

            'MODULE_PROVIDER_JSON' => str_replace(
                '\\',
                '\\\\',
                $provider,
            ),
        ];
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
    public function __construct(protected MenuService $menuService) {}
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
        $uri = $this->normalizeUri($uri);
        $method = strtoupper($method);

        if ($uri === '') {
            return null;
        }

        /*
     * Custom actions must be resolved before the CRUD method map.
     */
        $customPermission = $this->resolveCustomAction(
            $uri,
            $method
        );

        if ($customPermission !== null) {
            return $customPermission;
        }

        $action = $this->actionForMethod($method);

        if (!$action) {
            return null;
        }

        $parts = collect(explode('/', $uri))
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

        return implode('.', $parts) . '.' . $action;
    }

    protected function resolveCustomAction(
        string $uri,
        string $method
    ): ?string {
        foreach ($this->menuService->getDefinitions() as $definition) {
            $permission = $this->findCustomActionPermission(
                $definition,
                $uri,
                $method
            );

            if ($permission !== null) {
                return $permission;
            }
        }

        return null;
    }

    protected function findCustomActionPermission(
        MenuDefinition $definition,
        string $uri,
        string $method
    ): ?string {
        if ($definition->isResource()) {
            foreach ($definition->customActions() as $name => $config) {
                if (
                    $definition->customActionRoutePath($name) === $uri
                    && $definition->customActionMethod($name) === $method
                ) {
                    return $definition->customActionPermissionKey($name);
                }
            }
        }

        foreach ($definition->children as $child) {
            $permission = $this->findCustomActionPermission(
                $child,
                $uri,
                $method
            );

            if ($permission !== null) {
                return $permission;
            }
        }

        return null;
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
        return $this->methodMap[strtoupper($method)] ?? null;
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
                $permissions[] = $this->makePermission(
                    $definition,
                    $action,
                    $label
                );
            }

            foreach ($definition->customActions() as $name => $actionConfig) {
                $permissions[] = new PermissionDefinition(
                    name: $definition->customActionPermissionKey($name),
                    description: $definition->customActionPermissionLabel($name),
                    guard: 'api',
                    module: $definition->module,
                    menuPath: $definition->permissionKey(),
                    method: $definition->customActionMethod($name),
                );
            }
        }

        /*
     * Keep legacy standalone action nodes supported.
     */
        // if ($definition->isAction()) {
        //     $permissions[] = $this->makePermission(
        //         $definition,
        //         'execute',
        //         'Execute'
        //     );
        // }

        foreach ($definition->children as $child) {
            $this->buildPermissions($child, $permissions);
        }
    }

    protected function makePermission(
        MenuDefinition $definition,
        string $action,
        string $label
    ): PermissionDefinition {
        $name = $definition->permissionKey() . '.' . $action;

        $custom = $definition->permissions[$action] ?? null;

        $description = is_string($custom)
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

### src/Foundation/Support/SchemaDefinition.php

```php
<?php

namespace Bitsnio\AsasFlow\Foundation\Support;

use InvalidArgumentException;
use RuntimeException;

class SchemaDefinition
{
    protected array $document;
    protected array $schema;
    protected array $modelDefaults = [];

    public function __construct(array $document)
    {
        $this->document = $document;

        // Support both a raw JSON Schema and the AsasFlow wrapper.
        $this->schema = is_array($document['schema'] ?? null)
            ? $document['schema']
            : $document;

        $this->modelDefaults = is_array($document['model'] ?? null)
            ? $document['model']
            : [];

        $this->schema = $this->expandSchema($this->schema);
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(
                "Schema file not found: {$path}"
            );
        }

        $document = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($document)) {
            throw new \InvalidArgumentException(
                "Invalid schema document: {$path}"
            );
        }

        return new self($document);
    }

    public function schema(): array
    {
        return $this->schema;
    }

    public function originalDocument(): array
    {
        return $this->document;
    }

    public function modelDefaults(): array
    {
        return $this->modelDefaults;
    }

    public function title(): string
    {
        return (string) ($this->schema['title'] ?? '');
    }

    public function properties(): array
    {
        return is_array($this->schema['properties'] ?? null)
            ? $this->schema['properties']
            : [];
    }

    public function required(): array
    {
        return is_array($this->schema['required'] ?? null)
            ? $this->schema['required']
            : [];
    }

    public function property(string $name): array
    {
        $property = $this->properties()[$name] ?? null;

        return is_array($property) ? $property : [];
    }

    /**
     * Return Laravel rules keyed by dot-notation paths.
     *
     * Example:
     * [
     *     'name' => ['required', 'string'],
     *     'address' => ['nullable', 'array'],
     *     'address.city' => ['required_with:address', 'string'],
     *     'contacts.*.email' => ['required', 'email'],
     * ]
     */
    public function validationRules(): array
    {
        $rules = [];

        $this->buildObjectRules(
            $this->schema,
            '',
            $rules,
            []
        );

        return $rules;
    }

    /**
     * Eloquent casts apply to top-level database columns.
     */
    public function casts(): array
    {
        $casts = [];

        foreach ($this->properties() as $name => $property) {
            $type = $this->primaryType($property);

            $cast = match ($type) {
                'integer' => 'integer',
                'number' => 'decimal:4',
                'boolean' => 'boolean',
                'array', 'object' => 'array',
                default => null,
            };

            if ($cast !== null) {
                $casts[$name] = $cast;
            }

            if (($property['format'] ?? null) === 'date-time') {
                $casts[$name] = 'datetime';
            } elseif (($property['format'] ?? null) === 'date') {
                $casts[$name] = 'date';
            }
        }

        return $casts;
    }

    /**
     * Return top-level columns only.
     *
     * Nested objects and arrays become JSON columns by default.
     */
    public function migrationColumns(): array
    {
        $columns = [];

        foreach ($this->properties() as $name => $property) {
            $columns[$name] = [
                'name' => $name,
                'type' => $this->primaryType($property),
                'nullable' => $this->isNullable($property)
                    || !in_array($name, $this->required(), true),
                'default' => $property['default'] ?? null,
                'enum' => $property['enum'] ?? null,
                'format' => $property['format'] ?? null,
            ];
        }

        return $columns;
    }

    protected function buildObjectRules(
        array $schema,
        string $path,
        array &$rules,
        array $ancestors
    ): void {
        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            return;
        }

        $required = is_array($schema['required'] ?? null)
            ? $schema['required']
            : [];

        foreach ($properties as $name => $property) {
            if (!is_array($property)) {
                continue;
            }

            $field = $path === ''
                ? (string) $name
                : $path . '.' . $name;

            $isRequired = in_array((string) $name, $required, true);

            $fieldRules = $this->rulesForProperty(
                $property,
                $isRequired,
                $path,
                $ancestors
            );

            $rules[$field] = $fieldRules;

            $type = $this->primaryType($property);

            if ($type === 'object') {
                $this->buildObjectRules(
                    $property,
                    $field,
                    $rules,
                    [...$ancestors, $field]
                );
            }

            if ($type === 'array' && is_array($property['items'] ?? null)) {
                $items = $property['items'];
                $itemPath = $field . '.*';

                $rules[$itemPath] = $this->rulesForProperty(
                    $items,
                    true,
                    $field,
                    [...$ancestors, $field]
                );

                if ($this->primaryType($items) === 'object') {
                    $this->buildObjectRules(
                        $items,
                        $itemPath,
                        $rules,
                        [...$ancestors, $field, $itemPath]
                    );
                }
            }
        }
    }


    protected function rulesForProperty(
        string $path,
        array $property,
        bool $required = false,
        ?string $parentPath = null
    ): array {
        $rules = [];

        /*
        * Required / nullable handling.
        */
        if ($required) {
            if ($parentPath !== null && $parentPath !== '') {
                $rules[] = 'required_with:' . $parentPath;
            } else {
                $rules[] = 'required';
            }
        } else {
            $rules[] = 'nullable';
        }

        /*
        * Resolve the property's type.
        * Supports both "type": "string" and
        * "type": ["string", "null"].
        */
        $rawType = $property['type'] ?? 'string';
        $nullable = false;

        if (is_array($rawType)) {
            $nullable = in_array('null', $rawType, true);

            $types = array_values(array_filter(
                $rawType,
                static fn($type) => $type !== 'null'
            ));

            $type = $types[0] ?? 'string';
        } else {
            $type = $rawType;
        }

        if (
            $nullable
            && !$required
            && !in_array('nullable', $rules, true)
        ) {
            $rules[] = 'nullable';
        }

        /*
        * Type-specific Laravel validation rules.
        */
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

                if (isset($property['minItems'])) {
                    $rules[] = 'min:' . $property['minItems'];
                }

                if (isset($property['maxItems'])) {
                    $rules[] = 'max:' . $property['maxItems'];
                }

                break;

            case 'object':
                $rules[] = 'array';

                if (isset($property['minProperties'])) {
                    $rules[] = 'min:' . $property['minProperties'];
                }

                if (isset($property['maxProperties'])) {
                    $rules[] = 'max:' . $property['maxProperties'];
                }

                break;

            case 'string':
            default:
                $rules[] = 'string';

                if (isset($property['minLength'])) {
                    $rules[] = 'min:' . $property['minLength'];
                }

                if (isset($property['maxLength'])) {
                    $rules[] = 'max:' . $property['maxLength'];
                }

                $format = $property['format'] ?? null;

                switch ($format) {
                    case 'email':
                        $rules[] = 'email';
                        break;

                    case 'date':
                        $rules[] = 'date';
                        break;

                    case 'date-time':
                        $rules[] = 'date';
                        break;

                    case 'uri':
                        $rules[] = 'url';
                        break;

                    case 'uuid':
                        $rules[] = 'uuid';
                        break;
                }

                if (isset($property['pattern'])) {
                    $pattern = str_replace(
                        '/',
                        '\\/',
                        $property['pattern']
                    );

                    $rules[] = 'regex:/^' . $pattern . '$/u';
                }

                break;
        }

        /*
     * Numeric constraints apply only to integer and number.
     */
        if (in_array($type, ['integer', 'number'], true)) {
            if (isset($property['minimum'])) {
                $rules[] = 'min:' . $property['minimum'];
            }

            if (isset($property['maximum'])) {
                $rules[] = 'max:' . $property['maximum'];
            }

            if (
                isset($property['exclusiveMinimum'])
                && is_numeric($property['exclusiveMinimum'])
            ) {
                $rules[] = 'gt:' . $property['exclusiveMinimum'];
            }

            if (
                isset($property['exclusiveMaximum'])
                && is_numeric($property['exclusiveMaximum'])
            ) {
                $rules[] = 'lt:' . $property['exclusiveMaximum'];
            }
        }

        /*
     * Enum and constant constraints.
     */
        if (isset($property['enum']) && is_array($property['enum'])) {
            $rules[] = 'in:' . implode(
                ',',
                array_map('strval', $property['enum'])
            );
        }

        if (array_key_exists('const', $property)) {
            $rules[] = 'in:' . (string) $property['const'];
        }

        return array_values(array_unique($rules));
    }

    protected function primaryType(array $schema): string
    {
        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $types = array_values(array_filter(
                $type,
                static fn($item) => $item !== 'null'
            ));

            return (string) ($types[0] ?? 'string');
        }

        if (is_string($type)) {
            return $type === 'null' ? 'string' : $type;
        }

        if (isset($schema['properties'])) {
            return 'object';
        }

        if (isset($schema['items'])) {
            return 'array';
        }

        return 'string';
    }

    protected function isNullable(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        return is_array($type) && in_array('null', $type, true)
            || ($schema['nullable'] ?? false) === true;
    }

    /**
     * Resolve local references and merge composition keywords for
     * structural generation. Conditional and branch-specific validation
     * is left to the optional standards-compliant validator.
     */
    protected function expandSchema(
        array $schema,
        array $refStack = [],
        int $depth = 0
    ): array {
        if ($depth > 40) {
            return $schema;
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $ref = $schema['$ref'];

            if (!in_array($ref, $refStack, true)) {
                $resolved = $this->resolveLocalReference($ref);

                if ($resolved !== null) {
                    $schema = array_replace_recursive(
                        $this->expandSchema(
                            $resolved,
                            [...$refStack, $ref],
                            $depth + 1
                        ),
                        array_diff_key($schema, ['$ref' => true])
                    );
                }
            }
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $composition) {
            if (!is_array($schema[$composition] ?? null)) {
                continue;
            }

            foreach ($schema[$composition] as $branch) {
                if (!is_array($branch)) {
                    continue;
                }

                $expanded = $this->expandSchema(
                    $branch,
                    $refStack,
                    $depth + 1
                );

                $schema = $this->mergeSchemaStructures(
                    $schema,
                    $expanded
                );
            }

            unset($schema[$composition]);
        }

        if (is_array($schema['properties'] ?? null)) {
            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property)) {
                    $schema['properties'][$name] = $this->expandSchema(
                        $property,
                        $refStack,
                        $depth + 1
                    );
                }
            }
        }

        if (is_array($schema['items'] ?? null)) {
            $schema['items'] = $this->expandSchema(
                $schema['items'],
                $refStack,
                $depth + 1
            );
        }

        return $schema;
    }

    protected function mergeSchemaStructures(
        array $base,
        array $branch
    ): array {
        if (isset($branch['properties']) && is_array($branch['properties'])) {
            $base['properties'] = array_replace(
                $base['properties'] ?? [],
                $branch['properties']
            );
        }

        if (isset($branch['required']) && is_array($branch['required'])) {
            $base['required'] = array_values(array_unique([
                ...($base['required'] ?? []),
                ...$branch['required'],
            ]));
        }

        foreach (
            [
                'type',
                'items',
                'enum',
                'const',
                'format',
                'minimum',
                'maximum',
                'minLength',
                'maxLength',
            ] as $key
        ) {
            if (!array_key_exists($key, $base) && array_key_exists($key, $branch)) {
                $base[$key] = $branch[$key];
            }
        }

        return $base;
    }

    protected function resolveLocalReference(string $ref): ?array
    {
        if (!str_starts_with($ref, '#/')) {
            // External references are deliberately handled by Opis at validation time.
            return null;
        }

        $segments = explode('/', substr($ref, 2));

        $candidates = [
            $this->schema,
            $this->document,
        ];

        foreach ($candidates as $candidate) {
            $value = $candidate;

            foreach ($segments as $segment) {
                $segment = str_replace(
                    ['~1', '~0'],
                    ['/', '~'],
                    $segment
                );

                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_array($value)) {
                return $value;
            }
        }

        return null;
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
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Foundation\Support\GeneratorSupport;
use Illuminate\Support\Str;

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


        if ($definition->customActions()) {
            $imports[] = \Illuminate\Http\Request::class;
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

        foreach (array_keys($definition->customActions()) as $actionName) {
            $methods[] = $this->customActionMethod($actionName);
        }


        return implode(
            PHP_EOL . PHP_EOL,
            array_filter($methods)
        );
    }


    protected function customActionMethod(string $actionName): string
    {
        $method = Str::camel($actionName);

        return <<<PHP
                public function {$method}(Request \$request)
                {
                    return response()->json([
                        'message' => '{$method} not implemented',
                    ], 501);
                }
                PHP;
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
        | Resource Custom Action Validation
        |--------------------------------------------------------------------------
        */

        if (isset($config['actions'])) {
            if ($type !== 'resource') {
                throw new InvalidArgumentException(
                    "Custom actions can only be defined on resources. "
                        . "Invalid item [{$name}]."
                );
            }

            if (!is_array($config['actions'])) {
                throw new InvalidArgumentException(
                    "Actions for resource [{$name}] must be an array."
                );
            }

            foreach ($config['actions'] as $actionName => $actionConfig) {
                if (
                    !is_string($actionName) ||
                    !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $actionName)
                ) {
                    throw new InvalidArgumentException(
                        "Invalid custom action name on resource [{$name}]."
                    );
                }

                if (!is_array($actionConfig)) {
                    throw new InvalidArgumentException(
                        "Configuration for action [{$actionName}] "
                            . "on resource [{$name}] must be an array."
                    );
                }

                $method = strtoupper($actionConfig['method'] ?? 'POST');

                if (!in_array($method, self::ACTION_METHODS, true)) {
                    throw new InvalidArgumentException(
                        "Invalid method [{$method}] for action "
                            . "[{$actionName}] on resource [{$name}]."
                    );
                }
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
        $path = $module->configPath() . '/menu.php';

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
        $migrationPath = $module->migrationsPath();

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

```

### src/Generators/Model/ModelGenerator.php

```php
<?php

namespace Bitsnio\AsasFlow\Generators\Model;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\GeneratedBlock;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;

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
        $path = $this->files->getModelPath(
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
        $namespace = "Modules\\{$module->getName()}\\Models";

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

        foreach (array_keys($schema->properties()) as $field) {
            $lines[] = '        ' . var_export($field, true) . ',';
        }

        return implode(PHP_EOL, $lines);
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

### src/Generators/Module/ModuleGenerator.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleScaffolder;
use InvalidArgumentException;

final class ModuleGenerator
{
    public function __construct(
        private readonly ModuleScaffolder $scaffolder,
    ) {}

    /**
     * @return array<int, array{path: string, action: string}>
     */
    public function generate(
        ModuleDefinition $module,
        bool $force = false,
    ): array {
        if (! preg_match(
            '/^[A-Za-z][A-Za-z0-9_]*$/',
            $module->name,
        )) {
            throw new InvalidArgumentException(
                "Invalid module name [{$module->name}]."
            );
        }

        return $this->scaffolder->createFiles(
            $module,
            $force,
        );
    }
}
```

### src/Generators/Module/ModuleSettingsGenerator-Obsolete.php

```php
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\FileHandler;
use Bitsnio\AsasFlow\Foundation\Support\StubRenderer;
use Illuminate\Console\View\Components\Factory as ComponentFactory;
use Illuminate\Support\Str;
use RuntimeException;

class ModuleSettingsGenerator
{
    public function __construct(
        protected FileHandler $files,
        protected StubRenderer $stubs,
    ) {}

    /**
     * Generate module settings configuration.
     *
     * Generates:
     *
     *     config/settings.php
     */
    public function generate(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): array {
        $moduleName = Str::studly($moduleName);

        try {
            return $this->generateSettingsConfig(
                $module,
                $moduleName,
                $component
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'ModuleSettingsGenerator failed: '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Generate module settings configuration.
     */
    protected function generateSettingsConfig(
        $module,
        string $moduleName,
        ?ComponentFactory $component = null
    ): array {
        $path = $this->getSettingsConfigPath($module);

        if ($this->files->exists($path)) {
            $component?->info(
                "Settings config already exists: {$path}"
            );

            return [
                'action' => 'skipped-existing',
                'path' => $path,
            ];
        }

        $content = $this->stubs->renderFile(
            'settings-config.stub',
            [
                'MODULE' => $moduleName,
                'KEY' => $this->getModuleConfigKey($moduleName),
            ]
        );

        $this->files->writeFile(
            $path,
            $content
        );

        $component?->info(
            "Settings config generated: {$path}"
        );

        return [
            'action' => 'generated',
            'path' => $path,
        ];
    }

    /**
     * Get module settings config path.
     */
    protected function getSettingsConfigPath(
        $module
    ): string {
        return $module->configPath() . '/settings.php';
    }

    /**
     * Get module config key.
     */
    protected function getModuleConfigKey(
        string $moduleName
    ): string {
        return Str::kebab($moduleName);
    }
}
```

### src/Generators/ModuleSettingsGenerator-obsolete.php

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

            $content = substr($content, 0, $position) . $method . substr($content, $position);
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
            $this->getModuleNamespace() . '\\Settings\\';

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
        ) . '/Settings/' . $this->getSettingsClassName() . '.php';
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
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
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

        if ($rules === []) {
            return '        return [];';
        }

        $lines = [
            '        return [',
        ];

        foreach ($rules as $field => $fieldRules) {
            $lines[] = '            '
                . var_export($field, true)
                . ' => '
                . var_export(array_values($fieldRules), true)
                . ',';
        }

        $lines[] = '        ];';

        return implode(PHP_EOL, $lines);
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
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
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
                $module,
                $definition,
                [],
                0
            );
        }

        return $content;
    }

    protected function buildNode(
        $module,
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
                    $module,
                    $definition,
                    $additionalMiddleware,
                    $level
                );
        }

        /*
     * Custom actions belong to this resource controller.
     */
        foreach ($definition->customActions() as $actionName => $config) {
            $content .= $this->buildResourceActionRoute(
                $module,
                $definition,
                $actionName,
                $definition->customActionMethod($actionName),
                $additionalMiddleware,
                $level
            );
        }

        // /*
        //  * Action
        //  */
        // if ($definition->isAction()) {
        //     $content .=
        //         $this->buildActionRoute(
        //             $definition,
        //             $additionalMiddleware,
        //             $level
        //         );
        // }

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
                        $module,
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
        $module,
        MenuDefinition $definition,
        array $middleware,
        int $level
    ): string {

        $indent = str_repeat('    ', $level);

        // $controller = '\\'
        //     . ltrim(
        //         $this->support->controllerNamespace(
        //             $module,
        //             $definition
        //         ) . '\\' . $definition->controllerClass,
        //         '\\'
        //     );

        $actions = $definition->routeActions();

        $resource = "Route::apiResource(" . var_export(Str::kebab($definition->name), true) . ", {$definition->controllerClass}::class)";

        $allActions = [
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ];

        if ($actions !== $allActions) {
            $resource .= '->only(' . $this->phpArray($actions) . ')';
        }

        $resource .= ';';

        if (!$middleware) {
            return $indent . $resource . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $resource . "\n"
            . $indent . "});\n\n";
    }


    protected function buildResourceActionRoute(
        $module,
        MenuDefinition $definition,
        string $actionName,
        string $method,
        array $middleware,
        int $level
    ): string {
        
        $indent = str_repeat('    ', $level);

        // // $controller = '\\'
        //     . ltrim(
        //         $this->support->controllerNamespace(
        //             $module,
        //             $definition
        //         ) . '\\' . $definition->controllerClass,
        //         '\\'
        //     );

        $uri = Str::kebab($definition->name) . '/' . Str::kebab($actionName);

        $route = 'Route::'
            . strtolower($method)
            . '('
            . var_export($uri, true)
            . ', ['
            . $definition->controllerClass
            . '::class, '
            . var_export(Str::camel($actionName), true)
            . ']);';

        if (!$middleware) {
            return $indent . $route . "\n\n";
        }

        return $indent
            . 'Route::middleware(' . $this->phpArray($middleware)
            . ")->group(function () {\n"
            . $indent . '    ' . $route . "\n"
            . $indent . "});\n\n";
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

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Schema;

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Controller\ControllerGenerator;
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
            $schemaName = $definition->schemaName ?? $definition->modelClass();

            $schemaPath = $module->getPath() . '/schema/' . $schemaName . '.json';

            if (! is_file($schemaPath)) {
                $results[] = [
                    'type' => 'schema',
                    'action' => 'missing',
                    'name' => $schemaName,
                    'path' => $schemaPath,
                ];
            } else {
                $schema = SchemaDefinition::fromFile($schemaPath);

                $results['models'][] = $this->modelGenerator->generate(
                    $module,
                    $definition,
                    $schema
                );

                $results['migrations'][] = $this->migrationGenerator->generate(
                    $module,
                    $definition,
                    $schema
                );

                $results['requests'][] = $this->requestGenerator->syncFromSchema(
                    $module,
                    $definition,
                    $schema
                );

                $results['resources'][] = $this->resourceGenerator->syncFromSchema(
                    $module,
                    $definition,
                    $schema
                );

                $results['controllers'][] = $this->controllerGenerator->syncFromSchema(
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

### src/Generators/Stubs/Menu-obsolete.stub

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

### src/Generators/Stubs/Module/api.stub

```
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('{{ MODULE_LOWER }}')->group(function (): void {
    // Add module API routes here.
});

```

### src/Generators/Stubs/Module/composer-json.stub

```
{
    "name": "modules/{{ MODULE_LOWER }}",
    "description": "{{ MODULE_TITLE }} module for AsasFlow",
    "type": "library",
    "autoload": {
        "psr-4": {
            "{{ MODULE_COMPOSER_NAMESPACE }}": "app/",
            "{{ MODULE_COMPOSER_NAMESPACE }}Database\\Factories\\": "database/factories/",
            "{{ MODULE_COMPOSER_NAMESPACE }}Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "{{ MODULE_COMPOSER_NAMESPACE }}Tests\\": "tests/"
        }
    }
}
```

### src/Generators/Stubs/Module/config.stub

```
<?php

return [
    // Module-specific configuration.
];

```

### src/Generators/Stubs/Module/menu.stub

```
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module
    |--------------------------------------------------------------------------
    */

    'module' => [
        'name' => '{{ MODULE }}',
        'title' => '{{ MODULE_TITLE }}',

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

### src/Generators/Stubs/Module/module-json.stub

```
{
    "name": "{{ MODULE }}",
    "alias": "{{ MODULE_LOWER }}",
    "namespace": "{{ MODULE_NAMESPACE_JSON }}",
    "provider": "{{ MODULE_PROVIDER_JSON }}",
    "version": "{{ MODULE_VERSION }}",
    "enabled": true,
    "requires": {},
    "compatibility": {
        "asasflow": "^1.0"
    }
}

```

### src/Generators/Stubs/Module/module-service-provider.stub

```
<?php

declare(strict_types=1);

namespace {{ MODULE_NAMESPACE }}\Providers;

use Bitsnio\AsasFlow\Providers\ModuleServiceProvider as BaseModuleServiceProvider;

class {{ MODULE }}ServiceProvider extends BaseModuleServiceProvider
{
    protected string $name = '{{ MODULE }}';

    protected string $nameLower = '{{ MODULE_LOWER }}';

    /**
     * Additional service providers required by this module.
     *
     * @var array<class-string>
     */
    protected array $providers = [
        // \Modules\{{ MODULE }}\Providers\ExampleServiceProvider::class,
    ];

    /**
     * Artisan commands provided by this module.
     *
     * @var array<class-string<\Illuminate\Console\Command>>
     */
    protected array $commands = [
        // \Modules\{{ MODULE }}\Console\ExampleCommand::class,
    ];
}

```

### src/Generators/Stubs/Module/queue-job.stub

```
<?php

declare(strict_types=1);

namespace {{ MODULE_NAMESPACE }}\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class {{ MODULE }}QueueJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $payload = [],
    ) {}

    public function handle(): void
    {
        // Scaffolding test: replace this with the actual job logic.
    }
}
```

### src/Generators/Stubs/Module/queue.stub

```
<?php

declare(strict_types=1);

namespace {{ MODULE_NAMESPACE }}\Jobs\Queues;

final class {{ MODULE }}Queue
{
    public const NAME = '{{ MODULE_LOWER }}';

    public static function name(): string
    {
        return self::NAME;
    }
}
```

### src/Generators/Stubs/Module/routes-custom.stub

```
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Custom Module API Routes
|--------------------------------------------------------------------------
|
| Routes in this file are not automatically wrapped in the module-wide
| auth:api middleware group. Apply the required middleware explicitly.
|
*/

Route::prefix('{{ MODULE_LOWER }}')->group(function (): void {
    // Example:
    //
    // Route::middleware('auth:api')->get(
    //     'reports/summary',
    //     [ReportController::class, 'summary']
    // );
});
```

### src/Generators/Stubs/Module/settings.stub

```
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Every setting is defined here.
    |
    | The actual value is stored in:
    |
    |     ModuleSettings::$values
    |
    | This file describes the setting and tells the frontend how it
    | should be rendered.
    |
    */

    'settings' => [

        /*
        |--------------------------------------------------------------------------
        | Example
        |--------------------------------------------------------------------------
        |
        | 'example' => [
        |     'label' => 'Example',
        |     'description' => 'Example setting.',
        |     'type' => 'string',
        |     'input' => 'text',
        |     'default' => '',
        |     'options' => null,
        |     'rules' => [
        |         'nullable',
        |         'string',
        |     ],
        | ],
        |
        */

    ],

];
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

### src/Generators/Stubs/settings-config-obsolete.stub

```
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Every setting is defined here.
    |
    | The actual value is stored in:
    |
    |     ModuleSettings::$values
    |
    | This file describes the setting and tells the frontend how it
    | should be rendered.
    |
    */

    'settings' => [

        /*
        |--------------------------------------------------------------------------
        | Example
        |--------------------------------------------------------------------------
        |
        | 'example' => [
        |     'label' => 'Example',
        |     'description' => 'Example setting.',
        |     'type' => 'string',
        |     'input' => 'text',
        |     'default' => '',
        |     'options' => null,
        |     'rules' => [
        |         'nullable',
        |         'string',
        |     ],
        | ],
        |
        */

    ],

];
```

### src/Generators/Tests/SchemaDefinitionTest.php

```php
<?php

namespace Tests;

use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use PHPUnit\Framework\TestCase;

class SchemaDefinitionTest extends TestCase
{
    public function test_it_reads_the_asasflow_schema_wrapper(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'title' => 'Organization',
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive'],
                    ],
                ],
                'required' => ['name', 'status'],
            ],
            'model' => [
                'name' => '',
                'status' => 'active',
            ],
        ]);

        $this->assertSame('Organization', $schema->title());
        $this->assertArrayHasKey('name', $schema->properties());
        $this->assertSame(['name', 'status'], $schema->required());
        $this->assertSame('active', $schema->modelDefaults()['status']);
    }

    public function test_it_generates_nested_validation_rules(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'address' => [
                        'type' => 'object',
                        'properties' => [
                            'city' => ['type' => 'string'],
                        ],
                        'required' => ['city'],
                    ],
                    'contacts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'email' => [
                                    'type' => 'string',
                                    'format' => 'email',
                                ],
                            ],
                            'required' => ['email'],
                        ],
                    ],
                ],
            ],
        ]);

        $rules = $schema->validationRules();

        $this->assertArrayHasKey('address.city', $rules);
        $this->assertArrayHasKey('contacts.*', $rules);
        $this->assertArrayHasKey('contacts.*.email', $rules);
        $this->assertContains('email', $rules['contacts.*.email']);
    }

    public function test_it_maps_json_types_to_array_casts(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'metadata' => ['type' => 'object'],
                    'items' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'enabled' => ['type' => 'boolean'],
                ],
            ],
        ]);

        $casts = $schema->casts();

        $this->assertSame('array', $casts['metadata']);
        $this->assertSame('array', $casts['items']);
        $this->assertSame('boolean', $casts['enabled']);
    }

    public function test_it_accepts_nullable_union_types(): void
    {
        $schema = new SchemaDefinition([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => ['string', 'null']],
                ],
            ],
        ]);

        $column = $schema->migrationColumns()['email'];

        $this->assertTrue($column['nullable']);
    }
}
```

### config/asasflow.php

```php
<?php

return [
    'version' => env('ASASFLOW_VERSION'),
    "modules" => [
        "enabled" => true,
        "path" => base_path("Modules"),
        "namespace" => "Modules",
        'scaffold' => [

            'app_providers' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Providers',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Providers/{{ MODULE }}ServiceProvider.php',
                    'stub' => 'Module/module-service-provider.stub',
                    'replacements' => [],
                ],
            ],

            'app_commands' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Console/Commands',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_controllers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Controllers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_requests' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Requests',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_resources' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Http/Resources',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_models' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Models',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_observers' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Observers',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_policies' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Policies',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_rules' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Rules',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_services' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Services',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_actions' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Actions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_dtos' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/DTOs',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_enums' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Enums',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_traits' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Traits',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_events' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Events',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_listeners' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Listeners',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_jobs' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs',
                'stub' => [
                    'enabled' => false,
                    'path' => 'app/Jobs/{{ MODULE }}QueueJob.php',
                    'stub' => 'Module/queue-job.stub',
                    'replacements' => [],
                ],
            ],

            'queue' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Jobs/Queues',
                'stub' => [
                    'enabled' => true,
                    'path' => 'app/Jobs/Queues/{{ MODULE }}Queue.php',
                    'stub' => 'Module/queue.stub',
                    'replacements' => [],
                ],
            ],

            'app_notifications' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'app/Notifications',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'app_exceptions' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'app/Exceptions',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'config' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/config.php',
                    'stub' => 'Module/config.stub',
                    'replacements' => [],
                ],
            ],

            'menu' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/menu.php',
                    'stub' => 'Module/menu.stub',
                    'replacements' => [],
                ],
            ],

            'settings' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'config',
                'stub' => [
                    'enabled' => true,
                    'path' => 'config/settings.php',
                    'stub' => 'Module/settings.stub',
                    'replacements' => [],
                ],
            ],

            'migrations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/migrations',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'factories' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/factories',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'seeders' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'database/seeders',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'translations' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'lang/en',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'views' => [
                'type' => 'directory',
                'enabled' => false,
                'path' => 'resources/views',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/api.php',
                    'stub' => 'Module/api.stub',
                    'replacements' => [],
                ],
            ],
            'custom_routes' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'routes',
                'stub' => [
                    'enabled' => true,
                    'path' => 'routes/custom.php',
                    'stub' => 'Module/rout.stub',
                    'replacements' => [],
                ],
            ],
            'tests_feature' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Feature',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'tests_unit' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => 'tests/Unit',
                'stub' => [
                    'enabled' => false,
                    'path' => '',
                    'stub' => '',
                    'replacements' => [],
                ],
            ],

            'manifest' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'module.json',
                    'stub' => 'Module/module-json.stub',
                    'replacements' => [],
                ],
            ],

            'composer' => [
                'type' => 'directory',
                'enabled' => true,
                'path' => '.',
                'stub' => [
                    'enabled' => true,
                    'path' => 'composer.json',
                    'stub' => 'Module/composer-json.stub',
                    'replacements' => [],
                ],
            ],

        ],
    ],
    "routes" => [
        "enabled" => true,
    ],
    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Enable/disable caching
        'enabled' => env('ASASFLOW_CACHE_ENABLED', true),

        // Cache store to use (from cache.php stores)
        'store' => env('ASASFLOW_CACHE_STORE', config('cache.default', 'redis')),

        // Key prefix for all asasflow cache keys
        'prefix' => env('ASASFLOW_CACHE_PREFIX', 'asasflow'),

        // Default TTL in seconds
        'default_ttl' => env('ASASFLOW_CACHE_TTL', 3600),

        // Strict tenant isolation (prefix all keys with tenant ID)
        'strict_isolation' => env('ASASFLOW_CACHE_STRICT_ISOLATION', true),

        // Cache authenticated requests
        'cache_authenticated' => env('ASASFLOW_CACHE_AUTH', false),

        // Use queue for cache warming
        'warm_via_queue' => env('ASASFLOW_CACHE_QUEUE', true),

        // Maintain key registry for non-tag drivers (file/database)
        'fallback_registry' => true,

        // Add debug headers to responses
        'debug_headers' => env('ASASFLOW_CACHE_DEBUG', false),

        // HTTP cache headers max-age
        'http_max_age' => env('ASASFLOW_CACHE_HTTP_MAX_AGE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Tenancy Configuration
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable multi-tenancy
        'enabled' => env('ASASFLOW_TENANCY_ENABLED', false),

        // Database strategy: 'single' (tenant_id column) or 'separate' (per-tenant DB)
        'database_strategy' => env('ASASFLOW_TENANCY_DB_STRATEGY', 'single'),

        // Central domains (landlord access)
        'central_domains' => explode(',', env('ASASFLOW_CENTRAL_DOMAINS', 'localhost,127.0.0.1')),

        // Routes that skip tenant resolution
        'central_routes' => [
            'api/central/*',
            'admin/*',
            'health',
            'tenants/*',
            'asasflow/*',
        ],

        // Tenant model class
        'tenant_model' => \Bitsnio\AsasFlow\Features\Tenancy\Models\Tenant::class,

        // Connection name for tenant databases (when using separate strategy)
        'tenant_connection' => 'tenant',

        // Central connection name
        'central_connection' => config('database.default', 'mysql'),

        // Auto-create tenant database on tenant creation
        'auto_create_db' => true,

        // Auto-run tenant migrations
        'auto_migrate' => true,

        // Cache tenant resolution
        'cache_resolution' => true,
        'cache_resolution_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | ASASFLOW Generator Configuration
    |--------------------------------------------------------------------------
    |
    | Defaults used when generating controllers, requests, and resources.
    | Individual generators decide which configured features apply.
    |
    */
    'generators' => [
        /*
        |--------------------------------------------------------------------------
        | Controller Generator
        |--------------------------------------------------------------------------
        */
        'controller' => [
            /*
         * Additional imports added to generated controllers.
         * Use fully qualified class names, without "use".
         */
            'imports' => [],

            /*
         * Traits imported and applied inside the generated class.
         * Use fully qualified trait names.
         */
            'traits' => [],

            'features' => [
                'pagination' => [
                    'enabled' => true,
                    'per_page' => 15,
                ],

                /*
             * Reserved for the filtering implementation.
             * Enabling this alone does not implement filtering.
             */
                'filtering' => [
                    'enabled' => false,
                ],

                /*
             * Global model scopes are applied by Eloquent automatically.
             * This flag does not attach scopes to a model.
             */
                'scopes' => [
                    'enabled' => false,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Request Generator
    |--------------------------------------------------------------------------
    */
        'request' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_rules' => [
                    'enabled' => true,
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Resource Generator
    |--------------------------------------------------------------------------
    */
        'resource' => [
            'imports' => [],
            'traits' => [],

            'features' => [
                'schema_fields' => [
                    'enabled' => true,
                ],
            ],
        ],
    ],
    'install' => [
        /*
     * Modules required for a usable AsasFlow installation.
     */
        'required_modules' => [
            'system' => [
                'version' => '^1.0',
                'dev_url' => env('ASASFLOW_SYSTEM_MODULE_SOURCE'),
                'release_url' => env('ASASFLOW_SYSTEM_MODULE_SHA256'),
            ],
        ],
    ],
];

```

